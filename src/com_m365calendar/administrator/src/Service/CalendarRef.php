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
 * A configured calendar to sync.
 *
 * @since  0.1.0
 */
final class CalendarRef
{
    /**
     * Constructor.
     *
     * @param   integer  $id               Local calendar ID.
     * @param   string   $title            Title.
     * @param   string   $mailbox          Mailbox (validated e-mail address).
     * @param   string   $graphCalendarId  Graph calendar ID, empty for the default calendar.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $mailbox,
        public readonly string $graphCalendarId = ''
    ) {
    }

    /**
     * Graph path of the calendar resource, URL-encoded per segment.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function calendarPath(): string
    {
        $path = '/users/' . rawurlencode($this->mailbox);

        return $this->graphCalendarId === ''
            ? $path . '/calendar'
            : $path . '/calendars/' . rawurlencode($this->graphCalendarId);
    }

    /**
     * Graph path of the calendarView collection.
     *
     * Default calendar: /users/{mailbox}/calendarView,
     * specific calendar: /users/{mailbox}/calendars/{id}/calendarView.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public function calendarViewPath(): string
    {
        $path = '/users/' . rawurlencode($this->mailbox);

        return $this->graphCalendarId === ''
            ? $path . '/calendarView'
            : $path . '/calendars/' . rawurlencode($this->graphCalendarId) . '/calendarView';
    }
}
