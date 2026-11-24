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
 * An event as stored locally. Only these fields are ever kept: no body,
 * attendees or organizer.
 *
 * @since  1.0.0
 */
final class NormalizedEvent
{
    /**
     * Constructor.
     *
     * @param   string                   $graphId       Graph event ID.
     * @param   string                   $uidHash       SHA-256 hex of the Graph ID.
     * @param   string                   $subject       Subject (untrusted).
     * @param   string                   $location      Location display name (untrusted).
     * @param   \DateTimeImmutable       $startUtc      Start (UTC; midnight of the date for all-day events).
     * @param   \DateTimeImmutable       $endUtc        End (UTC, exclusive).
     * @param   boolean                  $allDay        All-day event.
     * @param   string                   $showAs        free, busy, tentative, oof, workingElsewhere, unknown.
     * @param   string[]                 $categories    Outlook category names.
     * @param   string                   $webLink       Outlook on the web link (https only) or empty.
     * @param   \DateTimeImmutable|null  $lastModified  Last modified (UTC).
     *
     * @since   1.0.0
     */
    public function __construct(
        public readonly string $graphId,
        public readonly string $uidHash,
        public readonly string $subject,
        public readonly string $location,
        public readonly \DateTimeImmutable $startUtc,
        public readonly \DateTimeImmutable $endUtc,
        public readonly bool $allDay,
        public readonly string $showAs,
        public readonly array $categories,
        public readonly string $webLink,
        public readonly ?\DateTimeImmutable $lastModified
    ) {
    }
}
