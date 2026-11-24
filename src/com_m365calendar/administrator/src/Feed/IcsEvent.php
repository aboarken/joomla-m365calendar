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
 * The only event data that may appear in a feed or "Add to my calendar" link.
 *
 * @since  1.0.0
 */
final class IcsEvent
{
    /**
     * Constructor.
     *
     * @param   string                   $uidHash       SHA-256 of the Graph event ID.
     * @param   string                   $subject       Subject.
     * @param   string                   $location      Location.
     * @param   \DateTimeImmutable       $startUtc      Start (UTC).
     * @param   \DateTimeImmutable       $endUtc        End (UTC, exclusive).
     * @param   boolean                  $allDay        All-day.
     * @param   string[]                 $categories    Categories.
     * @param   string                   $url           Absolute URL of the event page on this site.
     * @param   \DateTimeImmutable|null  $lastModified  Last modified.
     *
     * @since   1.0.0
     */
    public function __construct(
        public readonly string $uidHash,
        public readonly string $subject,
        public readonly string $location,
        public readonly \DateTimeImmutable $startUtc,
        public readonly \DateTimeImmutable $endUtc,
        public readonly bool $allDay,
        public readonly array $categories = [],
        public readonly string $url = '',
        public readonly ?\DateTimeImmutable $lastModified = null
    ) {
    }

    /**
     * Create from a database row of #__m365calendar_events.
     *
     * @param   object  $row  Row with uid_hash, subject, location, start_utc, end_utc, all_day, categories, last_modified_utc.
     * @param   string  $url  Event URL.
     *
     * @return  self
     *
     * @since   1.0.0
     */
    public static function fromRow(object $row, string $url = ''): self
    {
        $utc        = new \DateTimeZone('UTC');
        $categories = json_decode((string) ($row->categories ?? '[]'), true);

        return new self(
            (string) $row->uid_hash,
            (string) $row->subject,
            (string) $row->location,
            new \DateTimeImmutable((string) $row->start_utc, $utc),
            new \DateTimeImmutable((string) $row->end_utc, $utc),
            (bool) $row->all_day,
            \is_array($categories) ? array_values(array_filter($categories, 'is_string')) : [],
            $url,
            !empty($row->last_modified_utc) ? new \DateTimeImmutable((string) $row->last_modified_utc, $utc) : null
        );
    }
}
