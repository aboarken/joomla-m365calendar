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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Converts a Graph event resource into a NormalizedEvent.
 *
 * Skips private/personal/confidential and cancelled events, converts all
 * times to UTC and strips control characters from untrusted text.
 *
 * @since  0.1.0
 */
final class EventNormalizer
{
    /**
     * Maximum categories kept per event.
     *
     * @var    integer
     * @since  0.1.0
     */
    public const MAX_CATEGORIES = 25;

    /**
     * Normalize one Graph event.
     *
     * @param   array<string, mixed>  $item        Graph event.
     * @param   string                $fallbackTz  Time zone used when Graph's is missing or unknown.
     *
     * @return  NormalizedEvent|null  Null when the event must not be stored.
     *
     * @since   0.1.0
     */
    public function normalize(array $item, string $fallbackTz = 'UTC'): ?NormalizedEvent
    {
        $id = $item['id'] ?? null;

        if (!\is_string($id) || $id === '' || \strlen($id) > 1024) {
            return null;
        }

        if (!empty($item['isCancelled'])) {
            return null;
        }

        $sensitivity = strtolower(\is_string($item['sensitivity'] ?? null) ? $item['sensitivity'] : 'normal');

        if ($sensitivity !== 'normal') {
            return null;
        }

        $allDay = !empty($item['isAllDay']);
        $start  = self::parseDateTime($item['start'] ?? null, $fallbackTz, $allDay);
        $end    = self::parseDateTime($item['end'] ?? null, $fallbackTz, $allDay);

        if ($start === null || $end === null) {
            return null;
        }

        if ($allDay && $end <= $start) {
            $end = $start->modify('+1 day');
        } elseif ($end < $start) {
            $end = $start;
        }

        $location = '';

        if (\is_array($item['location'] ?? null) && \is_string($item['location']['displayName'] ?? null)) {
            $location = self::cleanText($item['location']['displayName'], 255);
        }

        $categories = [];

        foreach ((array) ($item['categories'] ?? []) as $category) {
            if (!\is_string($category)) {
                continue;
            }

            $category = self::cleanText($category, 191);

            if ($category !== '' && !\in_array($category, $categories, true)) {
                $categories[] = $category;
            }

            if (\count($categories) >= self::MAX_CATEGORIES) {
                break;
            }
        }

        $lastModified = null;

        if (\is_string($item['lastModifiedDateTime'] ?? null)) {
            try {
                $lastModified = (new \DateTimeImmutable($item['lastModifiedDateTime']))->setTimezone(new \DateTimeZone('UTC'));
            } catch (\Exception) {
                $lastModified = null;
            }
        }

        return new NormalizedEvent(
            $id,
            hash('sha256', $id),
            self::cleanText(\is_string($item['subject'] ?? null) ? $item['subject'] : '', 255),
            $location,
            $start,
            $end,
            $allDay,
            self::cleanText(\is_string($item['showAs'] ?? null) ? $item['showAs'] : '', 32),
            $categories,
            self::cleanUrl(\is_string($item['webLink'] ?? null) ? $item['webLink'] : ''),
            $lastModified
        );
    }

    /**
     * Parse a Graph dateTimeTimeZone into UTC.
     *
     * @param   mixed    $value       {dateTime, timeZone}.
     * @param   string   $fallbackTz  Fallback zone.
     * @param   boolean  $allDay      Keep only the date (at 00:00 UTC).
     *
     * @return  \DateTimeImmutable|null
     *
     * @since   0.1.0
     */
    public static function parseDateTime(mixed $value, string $fallbackTz, bool $allDay): ?\DateTimeImmutable
    {
        if (!\is_array($value) || !\is_string($value['dateTime'] ?? null)) {
            return null;
        }

        $utc = new \DateTimeZone('UTC');

        // Graph sends 7 fractional digits, e.g. 2026-12-01T09:00:00.0000000.
        $dateTime = preg_replace('/\.\d+/', '', $value['dateTime']) ?? '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?)?Z?$/', $dateTime)) {
            return null;
        }

        try {
            if ($allDay) {
                return new \DateTimeImmutable(substr($dateTime, 0, 10) . ' 00:00:00', $utc);
            }

            return (new \DateTimeImmutable(rtrim($dateTime, 'Z'), self::zone($value['timeZone'] ?? null, $fallbackTz)))
                ->setTimezone($utc);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Resolve a time zone name.
     *
     * @param   mixed   $name      Zone name from Graph.
     * @param   string  $fallback  Fallback zone.
     *
     * @return  \DateTimeZone
     *
     * @since   0.1.0
     */
    private static function zone(mixed $name, string $fallback): \DateTimeZone
    {
        foreach ([$name, $fallback, 'UTC'] as $candidate) {
            if (\is_string($candidate) && $candidate !== '') {
                try {
                    return new \DateTimeZone($candidate);
                } catch (\Exception) {
                    continue;
                }
            }
        }

        return new \DateTimeZone('UTC');
    }

    /**
     * Strip control characters, collapse whitespace and truncate.
     *
     * @param   string   $text    Text.
     * @param   integer  $length  Maximum characters.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function cleanText(string $text, int $length): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s{2,}/u', ' ', $text) ?? '');

        return mb_substr($text, 0, $length);
    }

    /**
     * Allow only absolute https links.
     *
     * @param   string  $url  URL.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function cleanUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || \strlen($url) > 2048 || !str_starts_with(strtolower($url), 'https://') || preg_match('/[\x00-\x20\x7F]/', $url)) {
            return '';
        }

        return filter_var($url, FILTER_VALIDATE_URL) === false ? '' : $url;
    }
}
