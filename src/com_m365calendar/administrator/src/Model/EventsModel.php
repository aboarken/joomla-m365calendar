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

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\M365calendar\Administrator\Filter\EventQueryBuilder;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Read-only list of synced events, plus per-calendar sync status.
 *
 * @since  1.0.0
 */
class EventsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array<string, mixed>      $config   An optional associative array of configuration settings.
     * @param   MVCFactoryInterface|null  $factory  The factory.
     *
     * @since   1.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'e.id',
                'subject', 'e.subject',
                'location', 'e.location',
                'start_utc', 'e.start_utc',
                'end_utc', 'e.end_utc',
                'calendar_title', 'c.title',
                'calendar_id', 'all_day', 'time', 'category',
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
    protected function populateState($ordering = 'e.start_utc', $direction = 'ASC')
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
        foreach (['search', 'calendar_id', 'all_day', 'time', 'category'] as $filter) {
            $id .= ':' . $this->getState('filter.' . $filter);
        }

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
                    ['e.id', 'e.subject', 'e.location', 'e.start_utc', 'e.end_utc', 'e.all_day', 'e.show_as', 'e.categories', 'e.synced_utc', 'c.title', 'c.color'],
                    ['id', 'subject', 'location', 'start_utc', 'end_utc', 'all_day', 'show_as', 'categories', 'synced_utc', 'calendar_title', 'calendar_color']
                )
            )
            ->from($db->quoteName('#__m365calendar_events', 'e'))
            ->join('INNER', $db->quoteName('#__m365calendar_calendars', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('e.calendar_id'));

        if ($calendarId = (int) $this->getState('filter.calendar_id')) {
            $query->where($db->quoteName('e.calendar_id') . ' = :cid')->bind(':cid', $calendarId, ParameterType::INTEGER);
        }

        $allDay = (string) $this->getState('filter.all_day');

        if ($allDay === '0' || $allDay === '1') {
            $query->where($db->quoteName('e.all_day') . ' = ' . (int) $allDay);
        }

        $now  = Factory::getDate()->toSql();
        $time = (string) $this->getState('filter.time');

        if ($time === 'upcoming') {
            $query->where($db->quoteName('e.end_utc') . ' > :now')->bind(':now', $now);
        } elseif ($time === 'past') {
            $query->where($db->quoteName('e.end_utc') . ' <= :now')->bind(':now', $now);
        }

        $category = trim((string) $this->getState('filter.category'));

        if ($category !== '') {
            $query->where(
                'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__m365calendar_event_categories', 'ec')
                . ' WHERE ' . $db->quoteName('ec.event_id') . ' = ' . $db->quoteName('e.id')
                . ' AND ' . $db->quoteName('ec.category') . ' = :category)'
            )->bind(':category', $category);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            $like = EventQueryBuilder::likePattern($search);
            $query->where(
                '(LOWER(' . $db->quoteName('e.subject') . ') LIKE :s1 OR LOWER(' . $db->quoteName('e.location') . ') LIKE :s2)'
            )
                ->bind(':s1', $like)
                ->bind(':s2', $like);
        }

        $orderCol  = (string) $this->state->get('list.ordering', 'e.start_utc');
        $orderDirn = strtoupper((string) $this->state->get('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $query->order($db->escape($orderCol) . ' ' . $orderDirn);

        return $query;
    }

    /**
     * Last sync time and status per calendar (one query).
     *
     * @return  object[]
     *
     * @since   1.0.0
     */
    public function getSyncStatus(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(
                $db->quoteName(
                    ['c.id', 'c.title', 'c.mailbox', 'c.published', 's.last_run', 's.last_success', 's.status', 's.message', 's.event_count'],
                    ['id', 'title', 'mailbox', 'published', 'last_run', 'last_success', 'status', 'message', 'event_count']
                )
            )
            ->from($db->quoteName('#__m365calendar_calendars', 'c'))
            ->join('LEFT', $db->quoteName('#__m365calendar_sync', 's'), $db->quoteName('s.calendar_id') . ' = ' . $db->quoteName('c.id'))
            ->whereIn($db->quoteName('c.published'), [0, 1])
            ->order($db->quoteName('c.ordering') . ' ASC, ' . $db->quoteName('c.title') . ' ASC');

        return $db->setQuery($query, 0, 200)->loadObjectList();
    }

    /**
     * The global lock row.
     *
     * @return  object|null
     *
     * @since   1.0.0
     */
    public function getLock(): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['locked_until', 'lock_owner']))
            ->from($db->quoteName('#__m365calendar_sync'))
            ->where($db->quoteName('calendar_id') . ' = 0');

        return $db->setQuery($query)->loadObject() ?: null;
    }
}
