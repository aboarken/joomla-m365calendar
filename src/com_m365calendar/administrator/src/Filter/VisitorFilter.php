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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Filters chosen by a visitor (GET, sticky per menu item). Untrusted.
 *
 * @since  1.0.0
 */
final class VisitorFilter
{
    /**
     * Constructor.
     *
     * @param   string   $search      Keyword in subject or location.
     * @param   string   $dateFrom    From (Y-m-d, visitor time zone).
     * @param   string   $dateTo      To (Y-m-d, inclusive).
     * @param   integer  $calendarId  Calendar (0 = any allowed).
     * @param   string   $category    Category ('' = any allowed).
     * @param   string   $location    Location contains.
     * @param   string   $eventType   all, allday, timed.
     * @param   string   $timeRange   all, upcoming, past.
     *
     * @since   1.0.0
     */
    public function __construct(
        public readonly string $search = '',
        public readonly string $dateFrom = '',
        public readonly string $dateTo = '',
        public readonly int $calendarId = 0,
        public readonly string $category = '',
        public readonly string $location = '',
        public readonly string $eventType = 'all',
        public readonly string $timeRange = 'all'
    ) {
    }

    /**
     * Build from raw (already type-filtered) input values, normalizing them.
     *
     * @param   array<string, mixed>  $values  Values.
     *
     * @return  self
     *
     * @since   1.0.0
     */
    public static function fromArray(array $values): self
    {
        $text = static fn ($v, int $max): string => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $v) ?? ''), 0, $max);

        return new self(
            $text($values['search'] ?? '', 100),
            PresetFilter::date((string) ($values['from'] ?? '')),
            PresetFilter::date((string) ($values['to'] ?? '')),
            max(0, (int) ($values['calendar'] ?? 0)),
            $text($values['category'] ?? '', 191),
            $text($values['location'] ?? '', 100),
            PresetFilter::oneOf((string) ($values['type'] ?? 'all'), ['all', 'allday', 'timed'], 'all'),
            PresetFilter::oneOf((string) ($values['time'] ?? 'all'), ['all', 'upcoming', 'past'], 'all')
        );
    }
}
