<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Site\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Site display controller.
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
    protected $default_view = 'calendar';

    /**
     * Method to display a view.
     *
     * Pages depend on per-visitor filter state, so they are not page-cached.
     *
     * @param   boolean  $cachable   If true, the view output will be cached.
     * @param   array<string, string>  $urlparams  An array of safe URL parameters and their variable types.
     *
     * @return  static
     *
     * @since   0.1.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->getCmd('view', $this->default_view);

        if (!\in_array($view, ['calendar', 'event'], true)) {
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $menu = $this->app->getMenu()->getActive();

        // The Calendar page and event pages only exist under a Calendar menu item.
        if (!$menu || $menu->component !== 'com_m365calendar' || ($menu->query['view'] ?? '') !== 'calendar') {
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $this->input->set('view', $view);

        return parent::display(false, $urlparams);
    }
}
