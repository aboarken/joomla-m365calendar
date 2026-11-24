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
 * Result of a sync run or a connection test.
 *
 * @since  0.1.0
 */
final class SyncResult
{
    /**
     * Run completed (per-calendar results may contain errors).
     *
     * @var    string
     * @since  0.1.0
     */
    public const DONE = 'done';

    /**
     * Another run holds the lock.
     *
     * @var    string
     * @since  0.1.0
     */
    public const LOCKED = 'locked';

    /**
     * Connection settings missing or invalid.
     *
     * @var    string
     * @since  0.1.0
     */
    public const NOT_CONFIGURED = 'not_configured';

    /**
     * Authentication failed before any calendar was processed.
     *
     * @var    string
     * @since  0.1.0
     */
    public const FAILED = 'failed';

    /**
     * Per-calendar results.
     *
     * @var    CalendarSyncResult[]
     * @since  0.1.0
     */
    private array $calendars = [];

    /**
     * Constructor.
     *
     * @param   string  $state   One of the class constants.
     * @param   string  $reason  Machine-readable reason (e.g. ConfigurationException reason or error kind).
     * @param   string  $detail  Technical detail for administrators: HTTP status, service error
     *                           code (e.g. AADSTS number) and request-id. Never contains secrets.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly string $state = self::DONE,
        public readonly string $reason = '',
        public readonly string $detail = ''
    ) {
    }

    /**
     * Add a calendar result.
     *
     * @param   CalendarSyncResult  $result  Result.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function add(CalendarSyncResult $result): void
    {
        $this->calendars[] = $result;
    }

    /**
     * Per-calendar results.
     *
     * @return  CalendarSyncResult[]
     *
     * @since   0.1.0
     */
    public function getCalendars(): array
    {
        return $this->calendars;
    }

    /**
     * Whether the run completed and every calendar succeeded.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function isSuccess(): bool
    {
        if ($this->state !== self::DONE) {
            return false;
        }

        foreach ($this->calendars as $calendar) {
            if (!$calendar->success) {
                return false;
            }
        }

        return true;
    }

    /**
     * Number of failed calendars.
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public function failedCount(): int
    {
        return \count(array_filter($this->calendars, static fn (CalendarSyncResult $r): bool => !$r->success));
    }

    /**
     * Total stored events.
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public function eventCount(): int
    {
        return array_sum(array_map(static fn (CalendarSyncResult $r): int => $r->count, $this->calendars));
    }
}
