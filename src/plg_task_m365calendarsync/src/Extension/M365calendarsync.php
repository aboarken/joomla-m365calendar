<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.m365calendarsync
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Plugin\Task\M365calendarsync\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\M365calendar\Administrator\Extension\M365calendarComponent;
use Joomla\Component\M365calendar\Administrator\Service\SyncResult;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Task Scheduler routine "Sync Microsoft 365 calendars".
 *
 * Thin wrapper: all work is done by com_m365calendar's SyncService.
 *
 * @since  1.0.0
 */
final class M365calendarsync extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * Task routines of this plugin. The routine ID is unique: m365calendar.sync.
     *
     * @var    array<string, array<string, string>>
     * @since  1.0.0
     */
    protected const TASKS_MAP = [
        'm365calendar.sync' => [
            'langConstPrefix' => 'PLG_TASK_M365CALENDARSYNC_TASK_SYNC',
            'method'          => 'sync',
        ],
    ];

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Events this plugin listens to (Task Scheduler events only).
     *
     * @return  array<string, string>
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask'     => 'standardRoutineHandler',
        ];
    }

    /**
     * Run the sync.
     *
     * @param   ExecuteTaskEvent  $event  The onExecuteTask event.
     *
     * @return  integer  Status code.
     *
     * @since   1.0.0
     */
    protected function sync(ExecuteTaskEvent $event): int
    {
        $component = $this->getApplication()->bootComponent('com_m365calendar');

        if (!$component instanceof M365calendarComponent) {
            $this->logTask('com_m365calendar is not available.', 'error');

            return Status::KNOCKOUT;
        }

        $result = $component->getSyncService()->syncAll('scheduler');

        switch ($result->state) {
            case SyncResult::LOCKED:
                $this->logTask('Another sync is running; skipped.', 'notice');

                return Status::OK;

            case SyncResult::NOT_CONFIGURED:
                $this->logTask('Connection settings are missing or invalid (' . $result->reason . ').', 'error');

                return Status::KNOCKOUT;

            case SyncResult::FAILED:
                $this->logTask('Authentication failed (' . $result->reason . ').', 'error');

                return Status::KNOCKOUT;
        }

        $this->logTask(
            \sprintf('%d calendars synced, %d failed, %d events stored.', \count($result->getCalendars()), $result->failedCount(), $result->eventCount())
        );

        return $result->failedCount() > 0 ? Status::KNOCKOUT : Status::OK;
    }
}
