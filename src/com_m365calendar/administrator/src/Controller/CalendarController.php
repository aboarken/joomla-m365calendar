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

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Calendar edit controller. ACL (core.create / core.edit) and CSRF checks
 * are done by FormController.
 *
 * @since  0.1.0
 */
class CalendarController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  0.1.0
     */
    protected $text_prefix = 'COM_M365CALENDAR_CALENDAR';

    /**
     * Batch process calendars (access level).
     *
     * @param   object|null  $model  The model.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function batch($model = null)
    {
        $this->checkToken();

        /** @var \Joomla\Component\M365calendar\Administrator\Model\CalendarModel $model */
        $model = $this->getModel('Calendar', 'Administrator', []);

        $this->setRedirect(Route::_('index.php?option=com_m365calendar&view=calendars' . $this->getRedirectToListAppend(), false));

        return parent::batch($model);
    }
}
