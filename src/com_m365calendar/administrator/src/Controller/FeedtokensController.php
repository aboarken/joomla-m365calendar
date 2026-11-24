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
use Joomla\Component\M365calendar\Administrator\Helper\ActionLogHelper;
use Joomla\Utilities\ArrayHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Revokes users' personal feed tokens.
 *
 * @since  1.0.0
 */
class FeedtokensController extends BaseController
{
    /**
     * Revoke the selected tokens (each user has at most one).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function revoke(): void
    {
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.manage', 'com_m365calendar') || !$user->authorise('core.delete', 'com_m365calendar')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $ids = array_values(array_filter(ArrayHelper::toInteger((array) $this->input->get('cid', [], 'array'))));

        $this->setRedirect(Route::_('index.php?option=com_m365calendar&view=feedtokens', false));

        if ($ids === []) {
            $this->setMessage(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 'warning');

            return;
        }

        /** @var \Joomla\Component\M365calendar\Administrator\Model\FeedtokenModel $model */
        $model = $this->getModel('Feedtoken', 'Administrator', ['ignore_request' => true]);

        if (!$model->delete($ids)) {
            $this->setMessage($model->getError() ?: Text::_('COM_M365CALENDAR_FEEDTOKENS_REVOKE_FAILED'), 'error');

            return;
        }

        ActionLogHelper::log('COM_M365CALENDAR_ACTIONLOG_TOKENS_REVOKED', ['count' => \count($ids)]);

        $this->setMessage(Text::plural('COM_M365CALENDAR_FEEDTOKENS_N_REVOKED', \count($ids)));
    }
}
