<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Modern SEF router.
 *
 * /{menu-alias}            Calendar page (list or month)
 * /{menu-alias}/{id}       Event detail, under the Calendar menu item it was opened from
 * /{menu-alias}/feed.ics   Subscription feed of that menu item
 *
 * @since  0.1.0
 */
class Router extends RouterView
{
    /**
     * Constructor.
     *
     * @param   SiteApplication  $app   The application object.
     * @param   AbstractMenu     $menu  The menu object to work with.
     *
     * @since   0.1.0
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        $calendar = new RouterViewConfiguration('calendar');
        $this->registerView($calendar);

        $event = new RouterViewConfiguration('event');
        $event->setKey('id')->setParent($calendar);
        $this->registerView($event);

        parent::__construct($app, $menu);

        $this->attachRule(new FeedRule((bool) $app->get('sef_suffix', false)));
        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Segment for an event.
     *
     * @param   string|int  $id     Event ID.
     * @param   array<string, mixed>  $query  The request that is built right now.
     *
     * @return  array<int, string>
     *
     * @since   0.1.0
     */
    public function getEventSegment($id, $query)
    {
        $id = (int) $id;

        return [$id => (string) $id];
    }

    /**
     * Event ID from a segment.
     *
     * @param   string  $segment  Segment.
     * @param   array<string, mixed>  $query    The request that is parsed right now.
     *
     * @return  integer|false
     *
     * @since   0.1.0
     */
    public function getEventId($segment, $query)
    {
        return ctype_digit((string) $segment) ? (int) $segment : false;
    }
}
