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
 * Persistence used by the SyncService.
 *
 * @since  0.1.0
 */
interface EventStoreInterface
{
    /**
     * Try to take the global sync lock (row 0 of #__m365calendar_sync).
     *
     * @param   string              $owner  Random owner ID of this run.
     * @param   \DateTimeImmutable  $now    Current time (UTC).
     * @param   \DateTimeImmutable  $until  Lock expiry (UTC).
     *
     * @return  boolean  True when the lock was acquired (or refreshed by the same owner).
     *
     * @since   0.1.0
     */
    public function acquireLock(string $owner, \DateTimeImmutable $now, \DateTimeImmutable $until): bool;

    /**
     * Release the lock held by an owner.
     *
     * @param   string  $owner  Owner ID.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function releaseLock(string $owner): void;

    /**
     * Published calendars.
     *
     * @return  CalendarRef[]
     *
     * @since   0.1.0
     */
    public function getPublishedCalendars(): array;

    /**
     * Replace the local events of one calendar with the given set (upsert by
     * Graph event ID + calendar ID, delete the rest). Atomic.
     *
     * @param   integer             $calendarId  Calendar ID.
     * @param   NormalizedEvent[]   $events      Events in the sync window.
     * @param   \DateTimeImmutable  $now         Sync time (UTC).
     *
     * @return  integer  Number of stored events.
     *
     * @since   0.1.0
     */
    public function replaceEvents(int $calendarId, array $events, \DateTimeImmutable $now): int;

    /**
     * Record the result of a calendar sync.
     *
     * @param   integer             $calendarId  Calendar ID.
     * @param   string              $status      "ok" or "error".
     * @param   string              $message     Technical message (status code / request-id), no secrets.
     * @param   integer|null        $count       Stored events, null to keep the previous value.
     * @param   \DateTimeImmutable  $now         Time (UTC).
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function recordStatus(int $calendarId, string $status, string $message, ?int $count, \DateTimeImmutable $now): void;

    /**
     * Delete feed tokens of users that no longer exist.
     *
     * @return  integer  Rows deleted.
     *
     * @since   0.1.0
     */
    public function deleteOrphanFeedTokens(): int;
}
