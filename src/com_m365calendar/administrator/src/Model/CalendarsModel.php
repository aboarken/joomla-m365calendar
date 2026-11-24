<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Model;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Calendars list model.
 *
 * @since  1.0.0
 */
class CalendarsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array                     $config   An optional associative array of configuration settings.
     * @param   MVCFactoryInterface|null  $factory  The factory.
     *
     * @since   1.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'mailbox', 'a.mailbox',
                'published', 'a.published',
                'access', 'a.access', 'access_level',
                'allow_feeds', 'a.allow_feeds',
                'ordering', 'a.ordering',
                'last_run', 's.last_run',
            ];
        }

        parent::__construct($config, $factory);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function populateState($ordering = 'a.title', $direction = 'ASC')
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . $this->getState('filter.access');
        $id .= ':' . $this->getState('filter.allow_feeds');

        return parent::getStoreId($id);
    }

    /**
     * Build the list query.
     *
     * @return  QueryInterface
     *
     * @since   1.0.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(
                $db->quoteName(
                    [
                        'a.id', 'a.title', 'a.mailbox', 'a.graph_calendar_id', 'a.color', 'a.allow_feeds',
                        'a.published', 'a.access', 'a.ordering', 'a.checked_out', 'a.checked_out_time',
                        'l.title', 'uc.name', 's.last_run', 's.status', 's.event_count',
                    ],
                    [
                        'id', 'title', 'mailbox', 'graph_calendar_id', 'color', 'allow_feeds',
                        'published', 'access', 'ordering', 'checked_out', 'checked_out_time',
                        'access_level', 'editor', 'last_run', 'sync_status', 'event_count',
                    ]
                )
            )
            ->from($db->quoteName('#__m365calendar_calendars', 'a'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'l'), $db->quoteName('l.id') . ' = ' . $db->quoteName('a.access'))
            ->join('LEFT', $db->quoteName('#__users', 'uc'), $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out'))
            ->join('LEFT', $db->quoteName('#__m365calendar_sync', 's'), $db->quoteName('s.calendar_id') . ' = ' . $db->quoteName('a.id'));

        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $published = (int) $published;
            $query->where($db->quoteName('a.published') . ' = :published')->bind(':published', $published, ParameterType::INTEGER);
        } elseif ($published === '') {
            $query->whereIn($db->quoteName('a.published'), [0, 1]);
        }

        if ($access = (int) $this->getState('filter.access')) {
            $query->where($db->quoteName('a.access') . ' = :access')->bind(':access', $access, ParameterType::INTEGER);
        }

        $feeds = (string) $this->getState('filter.allow_feeds');

        if (is_numeric($feeds)) {
            $feeds = (int) $feeds;
            $query->where($db->quoteName('a.allow_feeds') . ' = :feeds')->bind(':feeds', $feeds, ParameterType::INTEGER);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $search) . '%';
                $query->where('(' . $db->quoteName('a.title') . ' LIKE :s1 OR ' . $db->quoteName('a.mailbox') . ' LIKE :s2)')
                    ->bind(':s1', $search)
                    ->bind(':s2', $search);
            }
        }

        $orderCol  = (string) $this->state->get('list.ordering', 'a.title');
        $orderDirn = strtoupper((string) $this->state->get('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        if ($orderCol === 'access_level') {
            $orderCol = 'l.title';
        }

        $query->order($db->escape($orderCol) . ' ' . $orderDirn);

        return $query;
    }
}
