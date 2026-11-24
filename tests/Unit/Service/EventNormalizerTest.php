<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Unit\Service;

use Joomla\Component\M365calendar\Administrator\Service\EventNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Graph event -> local event.
 */
final class EventNormalizerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     *
     * @return array<string, mixed>
     */
    private function item(array $overrides = []): array
    {
        return $overrides + [
            'id'                   => 'AAMkAGI2TAAA=',
            'subject'              => 'Board meeting',
            'start'                => ['dateTime' => '2026-12-01T09:00:00.0000000', 'timeZone' => 'Europe/Berlin'],
            'end'                  => ['dateTime' => '2026-12-01T10:30:00.0000000', 'timeZone' => 'Europe/Berlin'],
            'isAllDay'             => false,
            'location'             => ['displayName' => 'Room 1'],
            'categories'           => ['Board', 'Board', 'Finance'],
            'sensitivity'          => 'normal',
            'showAs'               => 'busy',
            'isCancelled'          => false,
            'webLink'              => 'https://outlook.office365.com/owa/?itemid=x',
            'lastModifiedDateTime' => '2026-11-20T08:00:00Z',
            'body'                 => ['content' => 'SECRET BODY'],
            'attendees'            => [['emailAddress' => ['address' => 'x@y.z']]],
        ];
    }

    public function testConvertsToUtc(): void
    {
        $event = (new EventNormalizer())->normalize($this->item());

        $this->assertNotNull($event);
        $this->assertSame('2026-12-01 08:00:00', $event->startUtc->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 09:30:00', $event->endUtc->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $event->startUtc->getTimezone()->getName());
        $this->assertSame(hash('sha256', 'AAMkAGI2TAAA='), $event->uidHash);
        $this->assertSame(['Board', 'Finance'], $event->categories);
        $this->assertSame('Room 1', $event->location);
    }

    public function testNeverKeepsBodyOrAttendees(): void
    {
        $event = (new EventNormalizer())->normalize($this->item());
        $dump  = var_export($event, true);

        $this->assertStringNotContainsString('SECRET BODY', $dump);
        $this->assertStringNotContainsString('x@y.z', $dump);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function skipped(): array
    {
        return [
            'private'      => [['sensitivity' => 'private']],
            'personal'     => [['sensitivity' => 'personal']],
            'confidential' => [['sensitivity' => 'confidential']],
            'cancelled'    => [['isCancelled' => true]],
            'no id'        => [['id' => '']],
            'bad start'    => [['start' => ['dateTime' => 'yesterday']]],
        ];
    }

    /**
     * @dataProvider skipped
     *
     * @param  array<string, mixed>  $overrides
     */
    public function testSkips(array $overrides): void
    {
        $this->assertNull((new EventNormalizer())->normalize($this->item($overrides)));
    }

    public function testAllDayKeepsDates(): void
    {
        $event = (new EventNormalizer())->normalize($this->item([
            'isAllDay' => true,
            'start'    => ['dateTime' => '2026-12-24T00:00:00.0000000', 'timeZone' => 'Pacific/Auckland'],
            'end'      => ['dateTime' => '2026-12-26T00:00:00.0000000', 'timeZone' => 'Pacific/Auckland'],
        ]));

        $this->assertNotNull($event);
        $this->assertTrue($event->allDay);
        $this->assertSame('2026-12-24 00:00:00', $event->startUtc->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-26 00:00:00', $event->endUtc->format('Y-m-d H:i:s'));
    }

    public function testUnknownTimeZoneFallsBack(): void
    {
        $event = (new EventNormalizer())->normalize(
            $this->item(['start' => ['dateTime' => '2026-12-01T09:00:00', 'timeZone' => 'W. Europe Standard Time'], 'end' => ['dateTime' => '2026-12-01T10:00:00', 'timeZone' => 'W. Europe Standard Time']]),
            'Europe/London'
        );

        $this->assertSame('2026-12-01 09:00:00', $event?->startUtc->format('Y-m-d H:i:s'));
    }

    public function testCleansUntrustedText(): void
    {
        $event = (new EventNormalizer())->normalize($this->item([
            'subject'  => "Line1\r\nLine2\x00\x07 <script>",
            'webLink'  => 'javascript:alert(1)',
            'location' => ['displayName' => str_repeat('a', 400)],
        ]));

        $this->assertSame('Line1 Line2 <script>', $event?->subject, 'Control characters removed; HTML is escaped on output.');
        $this->assertSame('', $event?->webLink);
        $this->assertSame(255, mb_strlen((string) $event?->location));
    }
}
