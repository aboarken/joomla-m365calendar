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

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\Component\M365calendar\Administrator\Filter\EffectiveFilter;
use Joomla\Component\M365calendar\Administrator\Filter\EventQueryBuilder;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Read access to the local event tables for the site (never calls Graph).
 *
 * @since  1.0.0
 */
class EventRepository
{
    /**
     * Allowed sort orders.
     *
     * @var    array<string, string[]>
     * @since  1.0.0
     */
    public const SORTS = [
        'start_asc'  => ['e.start_utc ASC', 'e.id ASC'],
        'start_desc' => ['e.start_utc DESC', 'e.id DESC'],
        'title_asc'  => ['e.subject ASC', 'e.start_utc ASC'],
    ];

    /**
     * Calendars loaded in this request.
     *
     * @var    object[]|null
     * @since  1.0.0
     */
    private ?array $calendars = null;

    /**
     * Constructor.
     *
     * @param   DatabaseInterface  $db  Database.
     *
     * @since   1.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Published calendars (id, title, color, access, allow_feeds, published).
     *
     * Not cached on purpose: access level and "Allow in feeds" must apply at once.
     *
     * @return  object[]
     *
     * @since   1.0.0
     */
    public function publishedCalendars(): array
    {
        if ($this->calendars !== null) {
            return $this->calendars;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'color', 'access', 'allow_feeds', 'published']))
            ->from($db->quoteName('#__m365calendar_calendars'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('title') . ' ASC');

        return $this->calendars = $db->setQuery($query, 0, 500)->loadObjectList();
    }

    /**
     * Published calendars a set of view levels may see.
     *
     * @param   integer[]  $viewLevels  View levels.
     *
     * @return  object[]
     *
     * @since   1.0.0
     */
    public function accessibleCalendars(array $viewLevels): array
    {
        $levels = array_map('intval', $viewLevels);

        return array_values(array_filter(
            $this->publishedCalendars(),
            static fn (object $c): bool => \in_array((int) $c->access, $levels, true)
        ));
    }

    /**
     * Events query for a filter, restricted to published calendars the view levels allow.
     *
     * @param   EffectiveFilter  $filter      Filter.
     * @param   integer[]        $viewLevels  View levels.
     * @param   string           $sort        Key of SORTS.
     *
     * @return  QueryInterface
     *
     * @since   1.0.0
     */
    public function eventsQuery(EffectiveFilter $filter, array $viewLevels, string $sort = 'start_asc'): QueryInterface
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select(
                $db->quoteName(
                    [
                        'e.id', 'e.calendar_id', 'e.uid_hash', 'e.subject', 'e.location', 'e.start_utc', 'e.end_utc',
                        'e.all_day', 'e.show_as', 'e.categories', 'e.web_link', 'e.last_modified_utc', 'e.synced_utc',
                        'c.title', 'c.color',
                    ],
                    [
                        'id', 'calendar_id', 'uid_hash', 'subject', 'location', 'start_utc', 'end_utc',
                        'all_day', 'show_as', 'categories', 'web_link', 'last_modified_utc', 'synced_utc',
                        'calendar_title', 'calendar_color',
                    ]
                )
            )
            ->from($db->quoteName('#__m365calendar_events', 'e'))
            ->join('INNER', $db->quoteName('#__m365calendar_calendars', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('e.calendar_id'))
            ->where($db->quoteName('c.published') . ' = 1')
            ->whereIn($db->quoteName('c.access'), $viewLevels !== [] ? array_map('intval', $viewLevels) : [-1]);

        (new EventQueryBuilder($db))->apply($query, $filter, 'e');

        foreach (self::SORTS[$sort] ?? self::SORTS['start_asc'] as $order) {
            [$column, $direction] = explode(' ', $order);
            $query->order($db->quoteName($column) . ' ' . $direction);
        }

        return $query;
    }

    /**
     * Load event rows.
     *
     * @param   EffectiveFilter  $filter      Filter.
     * @param   integer[]        $viewLevels  View levels.
     * @param   string           $sort        Sort.
     * @param   integer          $limit       Maximum rows.
     *
     * @return  object[]
     *
     * @since   1.0.0
     */
    public function events(EffectiveFilter $filter, array $viewLevels, string $sort, int $limit): array
    {
        return $this->db->setQuery($this->eventsQuery($filter, $viewLevels, $sort), 0, $limit)->loadObjectList();
    }

    /**
     * One event that matches a filter.
     *
     * @param   integer          $id          Event ID.
     * @param   EffectiveFilter  $filter      Filter (the menu item preset).
     * @param   integer[]        $viewLevels  View levels.
     *
     * @return  object|null
     *
     * @since   1.0.0
     */
    public function event(int $id, EffectiveFilter $filter, array $viewLevels): ?object
    {
        $query = $this->eventsQuery($filter, $viewLevels)
            ->where($this->db->quoteName('e.id') . ' = :eventid')
            ->bind(':eventid', $id, ParameterType::INTEGER);

        return $this->db->setQuery($query, 0, 1)->loadObject() ?: null;
    }

    /**
     * Distinct categories used by events of some calendars, cached.
     *
     * @param   integer[]  $calendarIds  Calendars.
     *
     * @return  string[]
     *
     * @since   1.0.0
     */
    public function categories(array $calendarIds): array
    {
        $calendarIds = array_values(array_unique(array_map('intval', $calendarIds)));
        sort($calendarIds);

        if ($calendarIds === []) {
            return [];
        }

        return $this->cached('categories.' . implode(',', $calendarIds), function () use ($calendarIds): array {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('ec.category'))
                ->from($db->quoteName('#__m365calendar_event_categories', 'ec'))
                ->join('INNER', $db->quoteName('#__m365calendar_events', 'e'), $db->quoteName('e.id') . ' = ' . $db->quoteName('ec.event_id'))
                ->whereIn($db->quoteName('e.calendar_id'), $calendarIds)
                ->order($db->quoteName('ec.category') . ' ASC');

            return $db->setQuery($query, 0, 200)->loadColumn();
        });
    }

    /**
     * Run a loader through Joomla's callback cache (honours the site's cache setting).
     *
     * @param   string    $key     Cache key.
     * @param   callable  $loader  Loader.
     *
     * @return  array<int, mixed>
     *
     * @since   1.0.0
     */
    private function cached(string $key, callable $loader): array
    {
        try {
            $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
                ->createCacheController('callback', ['defaultgroup' => 'com_m365calendar']);

            $result = $cache->get($loader, [], md5('m365calendar.' . $key));
        } catch (\Throwable) {
            $result = $loader();
        }

        return \is_array($result) ? $result : [];
    }
}
