<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Service;

use M365Graph\ConnectionSettings;
use M365Graph\Exception\AccessDeniedException;
use M365Graph\Exception\AuthenticationException;
use M365Graph\Exception\ConfigurationException;
use M365Graph\Exception\GraphException;
use M365Graph\Exception\NotFoundException;
use M365Graph\Exception\ThrottledException;
use M365Graph\GraphClientInterface;
use Psr\Log\LoggerInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Does all syncing work. Used by the "Sync now" button and the task plugin.
 *
 * @since  0.1.0
 */
final class SyncService
{
    /**
     * Log category.
     *
     * @var    string
     * @since  0.1.0
     */
    public const LOG_CATEGORY = 'com_m365calendar.sync';

    /**
     * Fields requested from Graph. Body, attendees and organizer are never requested.
     *
     * @var    string
     * @since  0.1.0
     */
    public const SELECT = 'id,subject,start,end,isAllDay,location,categories,sensitivity,showAs,isCancelled,webLink,lastModifiedDateTime';

    /**
     * Page size requested from Graph.
     *
     * @var    integer
     * @since  0.1.0
     */
    public const PAGE_SIZE = 100;

    /**
     * Factory creating a Graph client from settings.
     *
     * @var    \Closure(ConnectionSettings): GraphClientInterface
     * @since  0.1.0
     */
    private \Closure $clientFactory;

    /**
     * Normalizer.
     *
     * @var    EventNormalizer
     * @since  0.1.0
     */
    private EventNormalizer $normalizer;

    /**
     * Clock.
     *
     * @var    \Closure(): \DateTimeImmutable
     * @since  0.1.0
     */
    private \Closure $clock;

    /**
     * Constructor.
     *
     * @param   EventStoreInterface  $store          Persistence.
     * @param   \Closure             $clientFactory  fn (ConnectionSettings): GraphClientInterface.
     * @param   ConnectionSettings   $settings       Connection settings.
     * @param   SyncOptions          $options        Sync options.
     * @param   LoggerInterface      $logger         Logger (Joomla Log API delegate).
     * @param   EventNormalizer|null $normalizer     Normalizer.
     * @param   \Closure|null        $afterSync      Called after a run that stored data (cache cleaning).
     * @param   \Closure|null        $clock          Clock for tests.
     *
     * @since   0.1.0
     */
    public function __construct(
        private readonly EventStoreInterface $store,
        \Closure $clientFactory,
        private readonly ConnectionSettings $settings,
        private readonly SyncOptions $options,
        private readonly LoggerInterface $logger,
        ?EventNormalizer $normalizer = null,
        private readonly ?\Closure $afterSync = null,
        ?\Closure $clock = null
    ) {
        $this->clientFactory = $clientFactory;
        $this->normalizer    = $normalizer ?? new EventNormalizer();
        $this->clock         = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Sync all published calendars.
     *
     * @param   string  $trigger  "manual" or "scheduler" (for the log only).
     *
     * @return  SyncResult
     *
     * @since   0.1.0
     */
    public function syncAll(string $trigger = 'manual'): SyncResult
    {
        if (!$this->settings->isComplete()) {
            return new SyncResult(SyncResult::NOT_CONFIGURED, 'incomplete');
        }

        $now   = $this->now();
        $owner = bin2hex(random_bytes(16));

        if (!$this->store->acquireLock($owner, $now, $this->lockExpiry($now))) {
            $this->logger->info('Sync skipped (' . $trigger . '): another sync is running.', ['category' => self::LOG_CATEGORY]);

            return new SyncResult(SyncResult::LOCKED);
        }

        try {
            try {
                $client = $this->createClient();
                $client->authenticate();
            } catch (ConfigurationException $e) {
                $this->logger->error('Sync not started: invalid connection settings (' . $e->getReason() . ').', ['category' => self::LOG_CATEGORY]);

                return new SyncResult(SyncResult::NOT_CONFIGURED, $e->getReason());
            } catch (GraphException $e) {
                $this->logGraphError('Authentication failed', $e);

                return new SyncResult(SyncResult::FAILED, self::errorKind($e));
            }

            [$start, $end] = $this->window($now);
            $result        = new SyncResult(SyncResult::DONE);

            foreach ($this->store->getPublishedCalendars() as $calendar) {
                $result->add($this->syncCalendar($client, $calendar, $start, $end));
                $this->store->acquireLock($owner, $this->now(), $this->lockExpiry($this->now()));
            }

            $this->store->deleteOrphanFeedTokens();

            if ($this->afterSync !== null) {
                ($this->afterSync)();
            }

            $this->logger->info(
                \sprintf('Sync finished (%s): %d calendars, %d failed, %d events.', $trigger, \count($result->getCalendars()), $result->failedCount(), $result->eventCount()),
                ['category' => self::LOG_CATEGORY]
            );

            return $result;
        } finally {
            $this->store->releaseLock($owner);
        }
    }

    /**
     * Sync one calendar. On failure the existing local data is kept.
     *
     * @param   GraphClientInterface  $client    Graph client.
     * @param   CalendarRef           $calendar  Calendar.
     * @param   \DateTimeImmutable    $start     Window start (UTC).
     * @param   \DateTimeImmutable    $end       Window end (UTC).
     *
     * @return  CalendarSyncResult
     *
     * @since   0.1.0
     */
    public function syncCalendar(GraphClientInterface $client, CalendarRef $calendar, \DateTimeImmutable $start, \DateTimeImmutable $end): CalendarSyncResult
    {
        $now = $this->now();

        try {
            $events = [];
            $items  = $client->getCollection(
                $calendar->calendarViewPath(),
                [
                    'startDateTime' => $start->format('Y-m-d\TH:i:s\Z'),
                    'endDateTime'   => $end->format('Y-m-d\TH:i:s\Z'),
                    '$select'       => self::SELECT,
                    '$top'          => self::PAGE_SIZE,
                ],
                ['Prefer' => 'outlook.timezone="' . $this->options->timezone . '"']
            );

            foreach ($items as $item) {
                $event = $this->normalizer->normalize($item, $this->options->timezone);

                if ($event !== null) {
                    $events[$event->uidHash] = $event;
                }
            }

            $count = $this->store->replaceEvents($calendar->id, array_values($events), $now);
            $this->store->recordStatus($calendar->id, 'ok', '', $count, $now);

            return new CalendarSyncResult($calendar->id, $calendar->title, true, $count);
        } catch (GraphException $e) {
            $this->logGraphError('Calendar ' . $calendar->id . ' sync failed', $e);
            $this->store->recordStatus($calendar->id, 'error', self::statusMessage($e), null, $now);

            return new CalendarSyncResult($calendar->id, $calendar->title, false, 0, self::errorKind($e), $e->getStatusCode(), $e->getRequestId());
        } catch (\RuntimeException $e) {
            // Database errors: keep the old data (the store rolls back).
            $this->logger->error('Calendar ' . $calendar->id . ' could not be stored.', ['category' => self::LOG_CATEGORY]);
            $this->store->recordStatus($calendar->id, 'error', 'storage', null, $now);

            return new CalendarSyncResult($calendar->id, $calendar->title, false, 0, 'storage');
        }
    }

    /**
     * Test the connection: get a token and read each published calendar's metadata.
     *
     * @return  SyncResult
     *
     * @since   0.1.0
     */
    public function testConnection(): SyncResult
    {
        if (!$this->settings->isComplete()) {
            return new SyncResult(SyncResult::NOT_CONFIGURED, 'incomplete');
        }

        try {
            $client = $this->createClient();
            $client->authenticate();
        } catch (ConfigurationException $e) {
            return new SyncResult(SyncResult::NOT_CONFIGURED, $e->getReason());
        } catch (GraphException $e) {
            $this->logGraphError('Connection test: authentication failed', $e);

            return new SyncResult(SyncResult::FAILED, self::errorKind($e));
        }

        $result = new SyncResult(SyncResult::DONE);

        foreach ($this->store->getPublishedCalendars() as $calendar) {
            try {
                $client->get($calendar->calendarPath(), ['$select' => 'id']);
                $result->add(new CalendarSyncResult($calendar->id, $calendar->title, true));
            } catch (GraphException $e) {
                $this->logGraphError('Connection test: calendar ' . $calendar->id, $e);
                $result->add(new CalendarSyncResult($calendar->id, $calendar->title, false, 0, self::errorKind($e), $e->getStatusCode(), $e->getRequestId()));
            }
        }

        return $result;
    }

    /**
     * The sync window in UTC.
     *
     * @param   \DateTimeImmutable  $now  Now.
     *
     * @return  array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     *
     * @since   0.1.0
     */
    public function window(\DateTimeImmutable $now): array
    {
        $today = $now->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);

        return [
            $today->modify('-' . $this->options->daysBack . ' days'),
            $today->modify('+' . ($this->options->daysAhead + 1) . ' days'),
        ];
    }

    /**
     * Map an exception to a short error kind.
     *
     * @param   GraphException  $e  Exception.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function errorKind(GraphException $e): string
    {
        return match (true) {
            $e instanceof AuthenticationException => 'auth',
            $e instanceof AccessDeniedException   => 'access',
            $e instanceof NotFoundException       => 'notfound',
            $e instanceof ThrottledException      => 'throttled',
            $e->getStatusCode() === 0             => 'transport',
            default                               => 'http',
        };
    }

    /**
     * Status text for #__m365calendar_sync (no secrets).
     *
     * @param   GraphException  $e  Exception.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function statusMessage(GraphException $e): string
    {
        $message = self::errorKind($e) . ' HTTP ' . $e->getStatusCode();

        if ($e->getErrorCode() !== '') {
            $message .= ' ' . preg_replace('/[^A-Za-z0-9_. -]/', '', $e->getErrorCode());
        }

        if ($e->getRequestId() !== '') {
            $message .= ' request-id ' . preg_replace('/[^A-Za-z0-9-]/', '', $e->getRequestId());
        }

        return mb_substr($message, 0, 1024);
    }

    /**
     * Log a Graph error with status code and request-id only.
     *
     * @param   string          $prefix  Message prefix.
     * @param   GraphException  $e       Exception.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function logGraphError(string $prefix, GraphException $e): void
    {
        $this->logger->error(
            \sprintf(
                '%s: HTTP %d, request-id %s',
                $prefix,
                $e->getStatusCode(),
                $e->getRequestId() !== '' ? preg_replace('/[^A-Za-z0-9-]/', '', $e->getRequestId()) : '-'
            ),
            ['category' => self::LOG_CATEGORY]
        );
    }

    /**
     * Create the Graph client.
     *
     * @return  GraphClientInterface
     *
     * @since   0.1.0
     */
    private function createClient(): GraphClientInterface
    {
        return ($this->clientFactory)($this->settings);
    }

    /**
     * Current time.
     *
     * @return  \DateTimeImmutable
     *
     * @since   0.1.0
     */
    private function now(): \DateTimeImmutable
    {
        return ($this->clock)();
    }

    /**
     * Lock expiry for a time.
     *
     * @param   \DateTimeImmutable  $now  Now.
     *
     * @return  \DateTimeImmutable
     *
     * @since   0.1.0
     */
    private function lockExpiry(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify('+' . $this->options->lockMinutes . ' minutes');
    }
}
