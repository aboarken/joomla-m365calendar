<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Feed;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * RFC 5545 iCalendar writer.
 *
 * Escapes TEXT values, strips control characters, folds lines at 75 octets
 * (never inside a UTF-8 sequence), writes UTC times or VALUE=DATE for all-day
 * events and a stable UID per event.
 *
 * @link   https://www.rfc-editor.org/rfc/rfc5545
 * @since  0.1.0
 */
final class IcsWriter
{
    /**
     * Line break required by RFC 5545.
     *
     * @var    string
     * @since  0.1.0
     */
    public const CRLF = "\r\n";

    /**
     * Product identifier.
     *
     * @var    string
     * @since  0.1.0
     */
    public const PRODID = '-//M365 Calendar for Joomla//com_m365calendar//EN';

    /**
     * Constructor.
     *
     * @param   string  $host  Site domain used in UIDs.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly string $host)
    {
    }

    /**
     * Build a complete calendar.
     *
     * @param   string              $name     Calendar name (X-WR-CALNAME).
     * @param   iterable<IcsEvent>  $events   Events.
     * @param   \DateTimeImmutable  $now      Generation time (DTSTAMP).
     * @param   string              $refresh  Refresh interval, ISO 8601 duration.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function calendar(string $name, iterable $events, \DateTimeImmutable $now, string $refresh = 'PT1H'): string
    {
        $refresh = preg_match('/^P(T\d+[HMS]|\d+D)$/', $refresh) ? $refresh : 'PT1H';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escapeText($name),
            'REFRESH-INTERVAL;VALUE=DURATION:' . $refresh,
            'X-PUBLISHED-TTL:' . $refresh,
        ];

        foreach ($events as $event) {
            array_push($lines, ...$this->eventLines($event, $now));
        }

        $lines[] = 'END:VCALENDAR';

        return implode(self::CRLF, array_map([self::class, 'fold'], $lines)) . self::CRLF;
    }

    /**
     * Lines of one VEVENT (unfolded).
     *
     * @param   IcsEvent            $event  Event.
     * @param   \DateTimeImmutable  $now    DTSTAMP.
     *
     * @return  string[]
     *
     * @since   0.1.0
     */
    public function eventLines(IcsEvent $event, \DateTimeImmutable $now): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $this->uid($event),
            'DTSTAMP:' . self::utc($now),
        ];

        if ($event->allDay) {
            $end = $event->endUtc > $event->startUtc ? $event->endUtc : $event->startUtc->modify('+1 day');

            $lines[] = 'DTSTART;VALUE=DATE:' . $event->startUtc->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
        } else {
            $lines[] = 'DTSTART:' . self::utc($event->startUtc);
            $lines[] = 'DTEND:' . self::utc($event->endUtc >= $event->startUtc ? $event->endUtc : $event->startUtc);
        }

        $lines[] = 'SUMMARY:' . self::escapeText($event->subject);

        if ($event->location !== '') {
            $lines[] = 'LOCATION:' . self::escapeText($event->location);
        }

        if ($event->categories !== []) {
            $lines[] = 'CATEGORIES:' . implode(',', array_map([self::class, 'escapeText'], $event->categories));
        }

        $url = self::cleanUri($event->url);

        if ($url !== '') {
            $lines[] = 'URL:' . $url;
        }

        if ($event->lastModified !== null) {
            $lines[] = 'LAST-MODIFIED:' . self::utc($event->lastModified);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * Stable UID: hash of the Graph event ID plus the site domain.
     *
     * @param   IcsEvent  $event  Event.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function uid(IcsEvent $event): string
    {
        $host = strtolower(preg_replace('/[^A-Za-z0-9.-]/', '', $this->host) ?: 'localhost');

        return preg_replace('/[^a-f0-9]/', '', $event->uidHash) . '@' . $host;
    }

    /**
     * Escape a TEXT value (RFC 5545, 3.3.11).
     *
     * Backslash, semicolon and comma are escaped, line breaks become "\n",
     * other control characters are removed.
     *
     * @param   string  $text  Text.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function escapeText(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $text) ?? '';

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);
    }

    /**
     * Fold a content line at 75 octets without splitting UTF-8 sequences.
     *
     * @param   string  $line  Unfolded line.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function fold(string $line): string
    {
        if (\strlen($line) <= 75) {
            return $line;
        }

        $parts   = [];
        $current = '';
        $limit   = 75;

        foreach (mb_str_split($line, 1, 'UTF-8') as $char) {
            if (\strlen($current) + \strlen($char) > $limit) {
                $parts[] = $current;
                $current = '';
                // Continuation lines start with a space, which counts toward the 75 octets.
                $limit = 74;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return implode(self::CRLF . ' ', $parts);
    }

    /**
     * UTC date-time value.
     *
     * @param   \DateTimeImmutable  $date  Date.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function utc(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /**
     * Accept only absolute http(s) URIs without whitespace or control characters.
     *
     * @param   string  $uri  URI.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function cleanUri(string $uri): string
    {
        if ($uri === '' || preg_match('/[\x00-\x20\x7F]/', $uri) || !preg_match('#^https?://#i', $uri)) {
            return '';
        }

        return $uri;
    }
}
