<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Filter;

use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Preset filters of a Calendar menu item. Read only from the menu item's
 * params, never from the request.
 *
 * @since  1.0.0
 */
final class PresetFilter
{
    /**
     * Constructor.
     *
     * @param   integer[]  $calendarIds   Allowed calendars (empty = all accessible).
     * @param   string[]   $categories    Allowed categories (empty = all).
     * @param   string     $location      "Location contains".
     * @param   string     $keyword       "Keyword in subject".
     * @param   string     $timeRange     all, upcoming, past.
     * @param   string     $dateMode      none, next, last, fixed.
     * @param   integer    $days          N for next/last N days.
     * @param   string     $dateFrom      Fixed from (Y-m-d, site time zone).
     * @param   string     $dateTo        Fixed to (Y-m-d, inclusive).
     * @param   string     $eventType     all, allday, timed.
     *
     * @since   1.0.0
     */
    public function __construct(
        public readonly array $calendarIds = [],
        public readonly array $categories = [],
        public readonly string $location = '',
        public readonly string $keyword = '',
        public readonly string $timeRange = 'all',
        public readonly string $dateMode = 'none',
        public readonly int $days = 0,
        public readonly string $dateFrom = '',
        public readonly string $dateTo = '',
        public readonly string $eventType = 'all'
    ) {
    }

    /**
     * Build from the active menu item's params.
     *
     * @param   Registry  $params  Menu item params (not merged with the request).
     *
     * @return  self
     *
     * @since   1.0.0
     */
    public static function fromParams(Registry $params): self
    {
        $calendars  = array_values(array_unique(array_filter(array_map('intval', (array) $params->get('preset_calendars', [])))));
        $categories = array_values(array_unique(array_filter(array_map(
            static fn ($c): string => trim((string) $c),
            (array) $params->get('preset_categories', [])
        ), static fn (string $c): bool => $c !== '')));

        return new self(
            $calendars,
            $categories,
            trim((string) $params->get('preset_location', '')),
            trim((string) $params->get('preset_keyword', '')),
            self::oneOf((string) $params->get('preset_time_range', 'all'), ['all', 'upcoming', 'past'], 'all'),
            self::oneOf((string) $params->get('preset_date_mode', 'none'), ['none', 'next', 'last', 'fixed'], 'none'),
            max(0, min(3650, (int) $params->get('preset_days', 30))),
            self::date((string) $params->get('preset_date_from', '')),
            self::date((string) $params->get('preset_date_to', '')),
            self::oneOf((string) $params->get('preset_event_type', 'all'), ['all', 'allday', 'timed'], 'all')
        );
    }

    /**
     * Whitelist a value.
     *
     * @param   string    $value    Value.
     * @param   string[]  $allowed  Allowed values.
     * @param   string    $default  Default.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function oneOf(string $value, array $allowed, string $default): string
    {
        return \in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Accept Y-m-d (also the date part of a datetime).
     *
     * @param   string  $value  Value.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function date(string $value): string
    {
        $value = substr(trim($value), 0, 10);

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return '';
        }

        return $value;
    }
}
