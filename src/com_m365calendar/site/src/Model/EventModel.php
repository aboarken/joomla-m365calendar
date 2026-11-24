<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Component\M365calendar\Administrator\Filter\FilterResolver;
use Joomla\Component\M365calendar\Administrator\Filter\PresetFilter;
use Joomla\Component\M365calendar\Administrator\Filter\VisitorFilter;
use Joomla\Component\M365calendar\Site\Service\EventRepository;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single event, reachable only through a Calendar menu item and only if it
 * matches that menu item's preset filters and the visitor's access levels.
 *
 * @since  0.1.0
 */
class EventModel extends ItemModel
{
    /**
     * Model context string.
     *
     * @var    string
     * @since  0.1.0
     */
    protected $_context = 'com_m365calendar.event';

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    protected function populateState()
    {
        $app    = Factory::getApplication();
        $menu   = $app->getMenu()->getActive();
        $isOurs = $menu && $menu->component === 'com_m365calendar' && ($menu->query['view'] ?? '') === 'calendar';

        $this->setState('event.id', $app->getInput()->getUint('id', 0));
        $this->setState('params', $app->getParams('com_m365calendar'));
        $this->setState('menu.params', $isOurs ? $menu->getParams() : new Registry());
        $this->setState('menu.id', $isOurs ? (int) $menu->id : 0);
    }

    /**
     * Get the event.
     *
     * @param   integer|null  $pk  Event ID.
     *
     * @return  object|null
     *
     * @since   0.1.0
     */
    public function getItem($pk = null)
    {
        $pk = (int) ($pk ?: $this->getState('event.id'));

        if ($pk <= 0 || !$this->getState('menu.id')) {
            return null;
        }

        if (!isset($this->_item[$pk])) {
            $user       = Factory::getApplication()->getIdentity();
            $levels     = array_map('intval', $user ? $user->getAuthorisedViewLevels() : [1]);
            $repository = new EventRepository($this->getDatabase());
            $accessible = array_map(static fn (object $c): int => (int) $c->id, $repository->accessibleCalendars($levels));

            $filter = FilterResolver::resolve(
                PresetFilter::fromParams($this->getState('menu.params')),
                new VisitorFilter(),
                $accessible,
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                new \DateTimeZone(Factory::getApplication()->get('offset', 'UTC')),
                $user ? $user->getTimezone() : new \DateTimeZone('UTC')
            );

            $this->_item[$pk] = $repository->event($pk, $filter, $levels);
        }

        return $this->_item[$pk];
    }
}
