<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Unit\Service;

use Joomla\Component\M365calendar\Administrator\Service\CalendarRef;
use Joomla\Component\M365calendar\Administrator\Service\NormalizedEvent;
use Joomla\Component\M365calendar\Administrator\Service\SyncOptions;
use Joomla\Component\M365calendar\Administrator\Service\SyncResult;
use Joomla\Component\M365calendar\Administrator\Service\SyncService;
use M365Calendar\Tests\Support\ArrayLogger;
use M365Calendar\Tests\Support\FakeGraphClient;
use M365Calendar\Tests\Support\InMemoryEventStore;
use M365Graph\ConnectionSettings;
use M365Graph\Exception\AccessDeniedException;
use M365Graph\Exception\AuthenticationException;
use M365Graph\Exception\ConfigurationException;
use M365Graph\Exception\ThrottledException;
use PHPUnit\Framework\TestCase;

/**
 * SyncService with a mocked Graph client and an in-memory store.
 */
final class SyncServiceTest extends TestCase
{
    private InMemoryEventStore $store;

    private FakeGraphClient $graph;

    private ArrayLogger $logger;

    private int $afterSync = 0;

    protected function setUp(): void
    {
        $this->store = new InMemoryEventStore();
        $this->graph = new FakeGraphClient();
        $this->store->calendars = [
            new CalendarRef(1, 'Board', 'board+room@example.com'),
            new CalendarRef(2, 'Training', 'training@example.com', 'AAMk/abc+def=='),
        ];
    }

    private function service(?ConnectionSettings $settings = null, ?\Closure $factory = null): SyncService
    {
        $this->logger = new ArrayLogger();

        return new SyncService(
            $this->store,
            $factory ?? fn (): FakeGraphClient => $this->graph,
            $settings ?? new ConnectionSettings('tenant.example.com', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', '/x.pem', '/x.key'),
            new SyncOptions(30, 365, 'Europe/Berlin', 15),
            $this->logger,
            null,
            function (): void {
                $this->afterSync++;
            },
            static fn (): \DateTimeImmutable => new \DateTimeImmutable('2026-11-24 12:00:00', new \DateTimeZone('UTC'))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function graphEvent(string $id, string $sensitivity = 'normal', bool $cancelled = false): array
    {
        return [
            'id'          => $id,
            'subject'     => 'Event ' . $id,
            'start'       => ['dateTime' => '2026-12-01T09:00:00.0000000', 'timeZone' => 'Europe/Berlin'],
            'end'         => ['dateTime' => '2026-12-01T10:00:00.0000000', 'timeZone' => 'Europe/Berlin'],
            'sensitivity' => $sensitivity,
            'isCancelled' => $cancelled,
        ];
    }

    public function testRequestsCalendarViewWithEncodedPathsAndHeaders(): void
    {
        $result = $this->service()->syncAll();

        $this->assertTrue($result->isSuccess());
        $this->assertSame('/users/board%2Broom%40example.com/calendarView', $this->graph->calls[0]['path']);
        $this->assertSame('/users/training%40example.com/calendars/AAMk%2Fabc%2Bdef%3D%3D/calendarView', $this->graph->calls[1]['path']);

        $query = $this->graph->calls[0]['query'];
        $this->assertSame('2026-10-25T00:00:00Z', $query['startDateTime']);
        $this->assertSame('2027-11-25T00:00:00Z', $query['endDateTime']);
        $this->assertSame(SyncService::SELECT, $query['$select']);
        $this->assertStringNotContainsString('body', $query['$select']);
        $this->assertStringNotContainsString('attendees', $query['$select']);
        $this->assertStringNotContainsString('organizer', $query['$select']);
        $this->assertSame('outlook.timezone="Europe/Berlin"', $this->graph->calls[0]['headers']['Prefer']);
    }

    public function testStoresOnlyNormalNotCancelledEvents(): void
    {
        $this->graph->collections['/users/board%2Broom%40example.com/calendarView'] = [
            self::graphEvent('a'),
            self::graphEvent('b', 'private'),
            self::graphEvent('c', 'normal', true),
            self::graphEvent('a'),
        ];

        $result = $this->service()->syncAll();

        $this->assertSame(1, $result->getCalendars()[0]->count);
        $this->assertCount(1, $this->store->events[1]);
        $this->assertSame('a', $this->store->events[1][0]->graphId);
        $this->assertSame('ok', $this->store->status[1]['status']);
        $this->assertSame(1, $this->afterSync);
        $this->assertSame(1, $this->store->orphanCleanups);
    }

    public function testFailureKeepsExistingDataAndRecordsStatus(): void
    {
        $path = '/users/board%2Broom%40example.com/calendarView';
        $old = new NormalizedEvent('old', hash('sha256', 'old'), 'Old', '', new \DateTimeImmutable('2026-12-01'), new \DateTimeImmutable('2026-12-01'), false, '', [], '', null);
        $this->store->events[1] = [$old];
        $this->graph->collections[$path] = [self::graphEvent('a'), self::graphEvent('b')];
        $this->graph->failures[$path]    = new ThrottledException('HTTP 429', 429, 'req-42');

        $result = $this->service()->syncAll();

        $this->assertFalse($result->isSuccess());
        $this->assertSame([$old], $this->store->events[1], 'Partial pages are never written.');
        $this->assertSame('error', $this->store->status[1]['status']);
        $this->assertNull($this->store->status[1]['count']);
        $this->assertStringContainsString('request-id req-42', $this->store->status[1]['message']);
        $this->assertSame('throttled', $result->getCalendars()[0]->errorKind);

        // The other calendar still syncs.
        $this->assertSame('ok', $this->store->status[2]['status']);

        // Logged: status code and request-id only, in the dedicated category.
        $errors = array_values(array_filter($this->logger->logs, static fn (array $l): bool => $l['level'] === 'error'));
        $this->assertCount(1, $errors);
        $this->assertSame('Calendar 1 sync failed: HTTP 429, request-id req-42', $errors[0]['message']);
        $this->assertSame(SyncService::LOG_CATEGORY, $errors[0]['context']['category']);
    }

    public function testOnlyOneSyncAtATime(): void
    {
        $this->store->lockOwner   = 'someone-else';
        $this->store->lockedUntil = new \DateTimeImmutable('2026-11-24 12:10:00', new \DateTimeZone('UTC'));

        $result = $this->service()->syncAll();

        $this->assertSame(SyncResult::LOCKED, $result->state);
        $this->assertSame([], $this->graph->calls);
    }

    public function testExpiredLockIsTakenOverAndReleased(): void
    {
        $this->store->lockOwner   = 'crashed-run';
        $this->store->lockedUntil = new \DateTimeImmutable('2026-11-24 11:00:00', new \DateTimeZone('UTC'));

        $result = $this->service()->syncAll();

        $this->assertSame(SyncResult::DONE, $result->state);
        $this->assertNull($this->store->lockOwner, 'Lock released after the run.');
    }

    public function testLockReleasedWhenAuthenticationFails(): void
    {
        $this->graph->authFailure = new AuthenticationException('denied', 400, 'rid-9', 'unauthorized_client AADSTS700016');

        $result = $this->service()->syncAll();

        $this->assertSame(SyncResult::FAILED, $result->state);
        $this->assertSame('auth', $result->reason);
        $this->assertSame('HTTP 400 unauthorized_client AADSTS700016 request-id rid-9', $result->detail, 'Admins get the status, AADSTS code and request-id.');
        $this->assertNull($this->store->lockOwner);
        $this->assertSame([], $this->store->status, 'Nothing is overwritten when no calendar was processed.');
    }

    public function testInvalidSettingsAreReported(): void
    {
        $result = $this->service(null, static function (): never {
            throw new ConfigurationException('inside web root', 'path_in_webroot');
        })->syncAll();

        $this->assertSame(SyncResult::NOT_CONFIGURED, $result->state);
        $this->assertSame('path_in_webroot', $result->reason);
        $this->assertNull($this->store->lockOwner);
    }

    public function testIncompleteSettingsDoNotTouchGraph(): void
    {
        $result = $this->service(new ConnectionSettings('', '', '', ''))->syncAll();

        $this->assertSame(SyncResult::NOT_CONFIGURED, $result->state);
        $this->assertSame([], $this->graph->calls);
    }

    public function testConnectionTestReadsCalendarMetadata(): void
    {
        $this->graph->failures['/users/training%40example.com/calendars/AAMk%2Fabc%2Bdef%3D%3D'] = new AccessDeniedException('no', 403, 'r1');

        $result = $this->service()->testConnection();

        $this->assertSame('/users/board%2Broom%40example.com/calendar', $this->graph->calls[0]['path']);
        $this->assertTrue($result->getCalendars()[0]->success);
        $this->assertFalse($result->getCalendars()[1]->success);
        $this->assertSame('access', $result->getCalendars()[1]->errorKind);
        $this->assertSame([], $this->store->events, 'A test never writes events.');
    }
}
