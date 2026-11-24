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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Default controller of the backend.
 *
 * @since  0.1.0
 */
class DisplayController extends BaseController
{
    /**
     * The default view.
     *
     * @var    string
     * @since  0.1.0
     */
    protected $default_view = 'events';

    /**
     * Method to display a view.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array<string, string>  $urlparams  An array of safe URL parameters and their variable types.
     *
     * @return  static|boolean  This object to support chaining or false on failure.
     *
     * @since   0.1.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_m365calendar')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $view   = $this->input->getCmd('view', $this->default_view);
        $layout = $this->input->getCmd('layout', 'default');
        $id     = $this->input->getInt('id');

        // Do not allow direct access to the edit form.
        if ($view === 'calendar' && $layout === 'edit' && !$this->checkEditId('com_m365calendar.edit.calendar', $id)) {
            if (!\count($this->app->getMessageQueue())) {
                $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id), 'error');
            }

            $this->setRedirect(Route::_('index.php?option=com_m365calendar&view=calendars', false));

            return false;
        }

        return parent::display($cachable, $urlparams);
    }
}
