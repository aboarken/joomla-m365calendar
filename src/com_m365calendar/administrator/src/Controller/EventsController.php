<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Component\M365calendar\Administrator\Extension\M365calendarComponent;
use Joomla\Component\M365calendar\Administrator\Helper\ActionLogHelper;
use Joomla\Component\M365calendar\Administrator\Helper\SyncMessageHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * "Sync now" and "Test connection" actions (POST, CSRF token, m365calendar.sync).
 *
 * @since  1.0.0
 */
class EventsController extends BaseController
{
    /**
     * Run a sync now.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function sync(): void
    {
        $this->checkToken();
        $this->authoriseSync();

        $result = $this->getComponent()->getSyncService()->syncAll('manual');

        foreach (SyncMessageHelper::messages($result) as [$message, $type]) {
            $this->app->enqueueMessage($message, $type);
        }

        ActionLogHelper::log('COM_M365CALENDAR_ACTIONLOG_SYNC', ['result' => SyncMessageHelper::summary($result)]);

        $this->setRedirect(Route::_('index.php?option=com_m365calendar&view=events', false));
    }

    /**
     * Test the connection.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function test(): void
    {
        $this->checkToken();
        $this->authoriseSync();

        $result = $this->getComponent()->getSyncService()->testConnection();

        foreach (SyncMessageHelper::messages($result, true) as [$message, $type]) {
            $this->app->enqueueMessage($message, $type);
        }

        ActionLogHelper::log('COM_M365CALENDAR_ACTIONLOG_TEST', ['result' => SyncMessageHelper::summary($result)]);

        $this->setRedirect(Route::_('index.php?option=com_m365calendar&view=events', false));
    }

    /**
     * Require the m365calendar.sync permission.
     *
     * @return  void
     *
     * @throws  \Exception
     *
     * @since   1.0.0
     */
    private function authoriseSync(): void
    {
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.manage', 'com_m365calendar') || !$user->authorise('m365calendar.sync', 'com_m365calendar')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * The booted component.
     *
     * @return  M365calendarComponent
     *
     * @since   1.0.0
     */
    private function getComponent(): M365calendarComponent
    {
        /** @var M365calendarComponent $component */
        $component = $this->app->bootComponent('com_m365calendar');

        return $component;
    }
}
