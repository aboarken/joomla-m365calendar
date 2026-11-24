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
 * Result for one calendar.
 *
 * @since  1.0.0
 */
final class CalendarSyncResult
{
    /**
     * Constructor.
     *
     * @param   integer  $calendarId  Calendar ID.
     * @param   string   $title       Calendar title.
     * @param   boolean  $success     Success.
     * @param   integer  $count       Stored events.
     * @param   string   $errorKind   auth, access, notfound, throttled, http, transport, config, storage.
     * @param   integer  $statusCode  HTTP status code.
     * @param   string   $requestId   Graph request-id.
     *
     * @since   1.0.0
     */
    public function __construct(
        public readonly int $calendarId,
        public readonly string $title,
        public readonly bool $success,
        public readonly int $count = 0,
        public readonly string $errorKind = '',
        public readonly int $statusCode = 0,
        public readonly string $requestId = ''
    ) {
    }
}
