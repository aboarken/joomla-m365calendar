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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Writes User Action Log entries via com_actionlogs' ActionlogModel.
 *
 * Calendar create/edit/delete/publish and Options changes are logged by the
 * core "Action Log - Joomla" plugin (the component is registered for it at
 * install). This helper logs the component's own actions: manual sync,
 * connection test and feed token revocation.
 *
 * @since  1.0.0
 */
final class ActionLogHelper
{
    /**
     * Add a log entry for the current user.
     *
     * @param   string                $messageKey  Language key, e.g. COM_M365CALENDAR_ACTIONLOG_SYNC.
     * @param   array<string, mixed>  $extra       Extra placeholders.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function log(string $messageKey, array $extra = []): void
    {
        if (!ComponentHelper::isEnabled('com_actionlogs')) {
            return;
        }

        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if ($user === null || !$user->id) {
            return;
        }

        $message = array_merge(
            [
                'action'      => 'm365calendar',
                'type'        => 'COM_M365CALENDAR_ACTIONLOG_TYPE_COMPONENT',
                'id'          => (int) $user->id,
                'title'       => $user->name,
                'itemlink'    => 'index.php?option=com_m365calendar&view=events',
                'userid'      => (int) $user->id,
                'username'    => $user->username,
                'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . (int) $user->id,
            ],
            $extra
        );

        try {
            $component = $app->bootComponent('com_actionlogs');

            if (!$component instanceof MVCFactoryServiceInterface) {
                return;
            }

            $model = $component->getMVCFactory()->createModel('Actionlog', 'Administrator', ['ignore_request' => true]);

            if ($model instanceof \Joomla\Component\Actionlogs\Administrator\Model\ActionlogModel) {
                $model->addLog([$message], $messageKey, 'com_m365calendar', (int) $user->id);
            }
        } catch (\Throwable) {
            // Logging must never break the action itself.
        }
    }
}
