<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Unit\Feed;

use Joomla\Component\M365calendar\Administrator\Feed\AddToCalendarLinks;
use Joomla\Component\M365calendar\Administrator\Feed\IcsEvent;
use Joomla\Component\M365calendar\Administrator\Feed\IcsWriter;
use PHPUnit\Framework\TestCase;

/**
 * RFC 5545 output.
 */
final class IcsWriterTest extends TestCase
{
    private static function event(string $subject = 'Board meeting', bool $allDay = false, string $location = 'Room 1'): IcsEvent
    {
        $utc = new \DateTimeZone('UTC');

        return new IcsEvent(
            hash('sha256', 'graph-id-1'),
            $subject,
            $location,
            new \DateTimeImmutable($allDay ? '2026-12-24 00:00:00' : '2026-12-01 08:00:00', $utc),
            new \DateTimeImmutable($allDay ? '2026-12-26 00:00:00' : '2026-12-01 09:30:00', $utc),
            $allDay,
            ['Board', 'Finance, Q4'],
            'https://www.example.org/calendar/42',
            new \DateTimeImmutable('2026-11-20 07:00:00', $utc)
        );
    }

    private static function unfold(string $ics): string
    {
        return str_replace("\r\n ", '', $ics);
    }

    public function testEscapesTextValues(): void
    {
        $this->assertSame('a\\\\b\\;c\\,d\\ne\\nf', IcsWriter::escapeText("a\\b;c,d\ne\r\nf"));
    }

    public function testStripsControlCharacters(): void
    {
        $this->assertSame('ab\\nc', IcsWriter::escapeText("a\x00\x07b\x1B\rc\x7F"));
    }

    public function testTitleWithNewlinesSemicolonsAndCommas(): void
    {
        $ics = (new IcsWriter('www.example.org'))->calendar('Cal', [self::event("Budget; Q4, final\nreview\r\nBEGIN:VEVENT")], new \DateTimeImmutable('2026-11-24 12:00:00'));

        $this->assertStringContainsString('SUMMARY:Budget\\; Q4\\, final\\nreview\\nBEGIN:VEVENT', self::unfold($ics));
        $this->assertSame(1, substr_count($ics, "\r\nBEGIN:VEVENT\r\n"), 'Injected lines cannot start a new component.');
        $this->assertStringContainsString('CATEGORIES:Board,Finance\\, Q4', $ics);
    }

    public function testFoldsLongLinesAt75Octets(): void
    {
        $subject = str_repeat('Ü€', 60) . ' and a long ascii tail ' . str_repeat('x', 100);
        $ics     = (new IcsWriter('www.example.org'))->calendar('Cal', [self::event($subject)], new \DateTimeImmutable('2026-11-24 12:00:00'));

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertLessThanOrEqual(75, \strlen($line), 'Line too long: ' . $line);
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'A UTF-8 sequence was split.');
        }

        $this->assertStringContainsString('SUMMARY:' . IcsWriter::escapeText($subject), self::unfold($ics));
    }

    public function testFoldContinuationStartsWithSpace(): void
    {
        $folded = IcsWriter::fold('SUMMARY:' . str_repeat('a', 200));
        $lines  = explode("\r\n", $folded);

        $this->assertGreaterThan(2, \count($lines));

        foreach (\array_slice($lines, 1) as $line) {
            $this->assertSame(' ', $line[0]);
        }
    }

    public function testCalendarStructure(): void
    {
        $ics = (new IcsWriter('www.example.org'))->calendar('Board; Meetings', [self::event()], new \DateTimeImmutable('2026-11-24 12:00:00', new \DateTimeZone('UTC')));

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringNotContainsString("\n", str_replace("\r\n", '', $ics), 'Only CRLF line breaks.');
        $this->assertStringContainsString("X-WR-CALNAME:Board\\; Meetings\r\n", $ics);
        $this->assertStringContainsString("REFRESH-INTERVAL;VALUE=DURATION:PT1H\r\n", $ics);
        $this->assertStringContainsString("X-PUBLISHED-TTL:PT1H\r\n", $ics);
        $this->assertStringContainsString("DTSTAMP:20261124T120000Z\r\n", $ics);
        $this->assertStringContainsString("DTSTART:20261201T080000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20261201T093000Z\r\n", $ics);
        $this->assertStringContainsString("LAST-MODIFIED:20261120T070000Z\r\n", $ics);
        $this->assertStringContainsString("UID:" . hash('sha256', 'graph-id-1') . "@www.example.org\r\n", self::unfold($ics));
        $this->assertStringContainsString("URL:https://www.example.org/calendar/42\r\n", $ics);
    }

    public function testOnlyAllowedProperties(): void
    {
        $ics   = (new IcsWriter('www.example.org'))->calendar('Cal', [self::event()], new \DateTimeImmutable('2026-11-24'));
        $props = [];

        preg_match_all('/^([A-Z-]+)[;:]/m', self::unfold($ics), $m);

        foreach ($m[1] as $prop) {
            $props[$prop] = true;
        }

        $this->assertSame(
            ['BEGIN', 'VERSION', 'PRODID', 'CALSCALE', 'METHOD', 'X-WR-CALNAME', 'REFRESH-INTERVAL', 'X-PUBLISHED-TTL', 'UID', 'DTSTAMP', 'DTSTART', 'DTEND', 'SUMMARY', 'LOCATION', 'CATEGORIES', 'URL', 'LAST-MODIFIED', 'END'],
            array_keys($props)
        );
    }

    public function testAllDayUsesValueDate(): void
    {
        $ics = (new IcsWriter('www.example.org'))->calendar('Cal', [self::event('Holiday', true)], new \DateTimeImmutable('2026-11-24'));

        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261224\r\n", $ics);
        $this->assertStringContainsString("DTEND;VALUE=DATE:20261226\r\n", $ics);
    }

    public function testUidIsStable(): void
    {
        $writer = new IcsWriter('www.example.org');

        $this->assertSame($writer->uid(self::event('A')), $writer->uid(self::event('B')));
        $this->assertNotSame($writer->uid(self::event()), (new IcsWriter('other.example'))->uid(self::event()));
    }

    public function testUnsafeUrlIsDropped(): void
    {
        $utc   = new \DateTimeZone('UTC');
        $event = new IcsEvent('ab', 'S', '', new \DateTimeImmutable('2026-01-01', $utc), new \DateTimeImmutable('2026-01-02', $utc), false, [], "javascript:alert(1)\r\nX:1");
        $ics   = (new IcsWriter('example.org'))->calendar('Cal', [$event], new \DateTimeImmutable());

        $this->assertStringNotContainsString('URL:', $ics);
        $this->assertStringNotContainsString('LOCATION:', $ics);
    }

    public function testAddToCalendarLinksAreEncoded(): void
    {
        $event = self::event('A&B <c> "d"');

        $outlook = AddToCalendarLinks::outlook($event);
        $google  = AddToCalendarLinks::google($event);

        $this->assertStringStartsWith('https://outlook.office.com/calendar/0/deeplink/compose?', $outlook);
        $this->assertStringContainsString('subject=A%26B%20%3Cc%3E%20%22d%22', $outlook);
        $this->assertStringContainsString('startdt=2026-12-01T08%3A00%3A00Z', $outlook);
        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?action=TEMPLATE', $google);
        $this->assertStringContainsString('dates=20261201T080000Z%2F20261201T093000Z', $google);

        $allDay = AddToCalendarLinks::google(self::event('H', true));
        $this->assertStringContainsString('dates=20261224%2F20261226', $allDay);
    }
}
