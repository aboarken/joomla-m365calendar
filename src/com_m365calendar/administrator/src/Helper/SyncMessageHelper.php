<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Helper;

use Joomla\CMS\Language\Text;
use Joomla\Component\M365calendar\Administrator\Service\SyncResult;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Turns sync/test results into translated admin messages.
 *
 * @since  0.1.0
 */
final class SyncMessageHelper
{
    /**
     * Messages for a result.
     *
     * @param   SyncResult  $result  Result.
     * @param   boolean     $test    Connection test instead of sync.
     *
     * @return  array<int, array{0: string, 1: string}>  [message, type] pairs.
     *
     * @since   0.1.0
     */
    public static function messages(SyncResult $result, bool $test = false): array
    {
        switch ($result->state) {
            case SyncResult::LOCKED:
                return [[Text::_('COM_M365CALENDAR_SYNC_LOCKED'), 'warning']];

            case SyncResult::NOT_CONFIGURED:
                return [[Text::_('COM_M365CALENDAR_SYNC_NOT_CONFIGURED') . ' ' . self::reason($result->reason), 'error']];

            case SyncResult::FAILED:
                return [[Text::_('COM_M365CALENDAR_SYNC_ERROR_' . strtoupper($result->reason ?: 'http')), 'error']];
        }

        $messages = [];

        foreach ($result->getCalendars() as $calendar) {
            if ($calendar->success) {
                $messages[] = [
                    $test
                        ? Text::sprintf('COM_M365CALENDAR_TEST_CALENDAR_OK', $calendar->title)
                        : Text::sprintf('COM_M365CALENDAR_SYNC_CALENDAR_OK', $calendar->title, $calendar->count),
                    'message',
                ];
                continue;
            }

            $messages[] = [
                Text::sprintf(
                    'COM_M365CALENDAR_SYNC_CALENDAR_FAILED',
                    $calendar->title,
                    Text::_('COM_M365CALENDAR_SYNC_ERROR_' . strtoupper($calendar->errorKind ?: 'http')),
                    $calendar->statusCode,
                    $calendar->requestId !== '' ? $calendar->requestId : '-'
                ),
                'error',
            ];
        }

        if ($messages === []) {
            $messages[] = [Text::_($test ? 'COM_M365CALENDAR_TEST_TOKEN_OK_NO_CALENDARS' : 'COM_M365CALENDAR_SYNC_NO_CALENDARS'), 'warning'];
        } elseif ($test) {
            array_unshift($messages, [Text::_('COM_M365CALENDAR_TEST_TOKEN_OK'), 'message']);
        }

        return $messages;
    }

    /**
     * Short untranslated summary for the action log.
     *
     * @param   SyncResult  $result  Result.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function summary(SyncResult $result): string
    {
        if ($result->state !== SyncResult::DONE) {
            return $result->state . ($result->reason !== '' ? ' (' . $result->reason . ')' : '');
        }

        return \sprintf('%d calendars, %d failed, %d events', \count($result->getCalendars()), $result->failedCount(), $result->eventCount());
    }

    /**
     * Translate a configuration reason.
     *
     * @param   string  $reason  Reason.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    private static function reason(string $reason): string
    {
        $reason = preg_replace('/[^a-z_]/', '', strtolower($reason)) ?: 'incomplete';

        return Text::sprintf('COM_M365CALENDAR_CONFIG_PATH_ERROR_' . strtoupper($reason), Text::_('COM_M365CALENDAR_CONFIG_CONNECTION_LABEL'));
    }
}
