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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\M365calendar\Administrator\Filter\EffectiveFilter;
use Joomla\Component\M365calendar\Administrator\Filter\FilterResolver;
use Joomla\Component\M365calendar\Administrator\Filter\PresetFilter;
use Joomla\Component\M365calendar\Administrator\Filter\VisitorFilter;
use Joomla\Component\M365calendar\Site\Service\EventRepository;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Model of the "Calendar" menu item: list and month display.
 *
 * Preset filters come only from the active menu item's params; visitor
 * filters (GET, sticky per Itemid) are ANDed with them and can never widen
 * the result. Only local tables are queried.
 *
 * @since  1.0.0
 */
class CalendarModel extends ListModel
{
    /**
     * Visitor filter keys and their input filters.
     *
     * @var    array<string, string>
     * @since  1.0.0
     */
    public const VISITOR_FILTERS = [
        'search'   => 'string',
        'from'     => 'string',
        'to'       => 'string',
        'calendar' => 'uint',
        'category' => 'string',
        'location' => 'string',
        'type'     => 'cmd',
        'time'     => 'cmd',
    ];

    /**
     * Menu item param that shows each visitor filter.
     *
     * @var    array<string, string>
     * @since  1.0.0
     */
    public const VISITOR_FILTER_SWITCHES = [
        'search'   => 'filter_show_search',
        'from'     => 'filter_show_dates',
        'to'       => 'filter_show_dates',
        'calendar' => 'filter_show_calendar',
        'category' => 'filter_show_category',
        'location' => 'filter_show_location',
        'type'     => 'filter_show_type',
        'time'     => 'filter_show_time',
    ];

    /**
     * Rows shown at most in the month view.
     *
     * @var    integer
     * @since  1.0.0
     */
    public const MONTH_LIMIT = 1000;

    /**
     * Cached effective filter.
     *
     * @var    EffectiveFilter|null
     * @since  1.0.0
     */
    private ?EffectiveFilter $effective = null;

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   Unused.
     * @param   string  $direction  Unused.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app    = Factory::getApplication();
        $input  = $app->getInput();
        $menu   = $app->getMenu()->getActive();
        $itemId = $menu && $menu->component === 'com_m365calendar' ? (int) $menu->id : 0;

        // Display options: component options merged with the menu item.
        $params = $app->getParams('com_m365calendar');
        // Preset filters: the menu item's own params only.
        $menuParams = $itemId ? $menu->getParams() : new Registry();

        $this->setState('params', $params);
        $this->setState('menu.params', $menuParams);
        $this->setState('menu.id', $itemId);

        $context       = 'com_m365calendar.calendar.' . $itemId;
        $this->context = $context;

        if ($input->getBool('filter_reset')) {
            foreach (array_keys(self::VISITOR_FILTERS) as $key) {
                $app->setUserState($context . '.filter.' . $key, null);
            }

            $app->setUserState($context . '.list.sort', null);
        }

        $visitor = [];

        if ((int) $params->get('show_filter_bar', 1) === 1) {
            foreach (self::VISITOR_FILTERS as $key => $filter) {
                if ((int) $params->get(self::VISITOR_FILTER_SWITCHES[$key], 1) !== 1) {
                    continue;
                }

                $visitor[$key] = $app->getUserStateFromRequest($context . '.filter.' . $key, 'filter_' . $key, '', $filter);
            }
        }

        $this->setState('filter.visitor', VisitorFilter::fromArray($visitor));

        // List or month, with an optional toggle.
        $mode = (string) $params->get('display_mode', 'list');

        if ((int) $params->get('show_view_toggle', 1) === 1) {
            $mode = (string) $app->getUserStateFromRequest($context . '.mode', 'mode', $mode, 'cmd');
        }

        $this->setState('display.mode', \in_array($mode, ['list', 'month'], true) ? $mode : 'list');

        $month = $input->getString('month', '');

        if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month) || (int) substr($month, 0, 4) < 1970 || (int) substr($month, 0, 4) > 2200) {
            $month = (new \DateTimeImmutable('now', $this->userTimezone()))->format('Y-m');
        }

        $this->setState('display.month', $month);

        // Sorting.
        $sort = (string) $params->get('default_ordering', 'start_asc');

        if ((int) $params->get('show_filter_bar', 1) === 1 && (int) $params->get('filter_show_sort', 1) === 1) {
            $sort = (string) $app->getUserStateFromRequest($context . '.list.sort', 'list_sort', $sort, 'cmd');
        }

        $this->setState('list.sort', isset(EventRepository::SORTS[$sort]) ? $sort : 'start_asc');

        $limit = max(1, min(100, (int) $params->get('events_per_page', 20)));
        $this->setState('list.limit', $limit);
        $this->setState('list.start', $input->getUint('limitstart', 0));
    }

    /**
     * Store ID.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('menu.id');
        $id .= ':' . serialize($this->getState('filter.visitor'));
        $id .= ':' . $this->getState('display.mode') . ':' . $this->getState('display.month');
        $id .= ':' . $this->getState('list.sort');

        return parent::getStoreId($id);
    }

    /**
     * In month mode all events of the visible grid are loaded (bounded).
     *
     * @return  integer
     *
     * @since   1.0.0
     */
    public function getStart()
    {
        return $this->getState('display.mode') === 'month' ? 0 : parent::getStart();
    }

    /**
     * Get the items (month mode: without pagination).
     *
     * @return  object[]
     *
     * @since   1.0.0
     */
    public function getItems()
    {
        if ($this->getState('display.mode') === 'month') {
            $store = $this->getStoreId('month');

            if (!isset($this->cache[$store])) {
                $this->cache[$store] = $this->getDatabase()->setQuery($this->getListQuery(), 0, self::MONTH_LIMIT)->loadObjectList();
            }

            return $this->cache[$store];
        }

        return parent::getItems() ?: [];
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
        return $this->getRepository()->eventsQuery($this->getEffectiveFilter(), $this->viewLevels(), (string) $this->getState('list.sort'));
    }

    /**
     * The effective filter: preset AND visitor (AND the month range in month mode).
     *
     * @return  EffectiveFilter
     *
     * @since   1.0.0
     */
    public function getEffectiveFilter(): EffectiveFilter
    {
        if ($this->effective !== null) {
            return $this->effective;
        }

        $filter = FilterResolver::resolve(
            PresetFilter::fromParams($this->getState('menu.params')),
            $this->getState('filter.visitor') ?? new VisitorFilter(),
            array_map(static fn (object $c): int => (int) $c->id, $this->getAccessibleCalendars()),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            new \DateTimeZone(Factory::getApplication()->get('offset', 'UTC')),
            $this->userTimezone()
        );

        if ($this->getState('display.mode') === 'month') {
            [$from, $to] = $this->monthRange();
            $filter      = $filter->withRange($from, $to);
        }

        return $this->effective = $filter;
    }

    /**
     * Published calendars the visitor may view.
     *
     * @return  object[]
     *
     * @since   1.0.0
     */
    public function getAccessibleCalendars(): array
    {
        return $this->getRepository()->accessibleCalendars($this->viewLevels());
    }

    /**
     * Calendars a visitor may pick: accessible AND in the menu item preset.
     *
     * @return  object[]
     *
     * @since   1.0.0
     */
    public function getVisitorCalendarOptions(): array
    {
        $preset = PresetFilter::fromParams($this->getState('menu.params'));

        return array_values(array_filter(
            $this->getAccessibleCalendars(),
            static fn (object $c): bool => $preset->calendarIds === [] || \in_array((int) $c->id, $preset->calendarIds, true)
        ));
    }

    /**
     * Categories a visitor may pick: the preset list, or the categories used
     * in the calendars allowed by the preset.
     *
     * @return  string[]
     *
     * @since   1.0.0
     */
    public function getCategoryOptions(): array
    {
        $preset = PresetFilter::fromParams($this->getState('menu.params'));

        if ($preset->categories !== []) {
            return $preset->categories;
        }

        return $this->getRepository()->categories(
            array_map(static fn (object $c): int => (int) $c->id, $this->getVisitorCalendarOptions())
        );
    }

    /**
     * Month grid with the loaded events placed on each day they cover.
     *
     * @param   object[]  $items  Events.
     *
     * @return  array{month: string, prev: string, next: string, first: string, weekdays: int[], weeks: array<int, array<int, array{date: string, day: int, inMonth: bool, today: bool, events: object[]}>>}
     *
     * @since   1.0.0
     */
    public function getMonthGrid(array $items): array
    {
        $tz        = $this->userTimezone();
        $month     = (string) $this->getState('display.month');
        $first     = new \DateTimeImmutable($month . '-01 00:00:00', $tz);
        $weekStart = (int) ComponentHelper::getParams('com_m365calendar')->get('week_start', 1);
        [$gridStart, $gridEnd] = $this->monthRange();
        $gridStart = $gridStart->setTimezone($tz);
        $gridEnd   = $gridEnd->setTimezone($tz);
        $today     = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');

        // Index events per local date.
        $byDay = [];

        foreach ($items as $item) {
            foreach ($this->eventDays($item, $tz) as $day) {
                $byDay[$day][] = $item;
            }
        }

        $weeks = [];
        $week  = [];

        for ($day = $gridStart; $day < $gridEnd; $day = $day->modify('+1 day')) {
            $key    = $day->format('Y-m-d');
            $week[] = [
                'date'    => $key,
                'day'     => (int) $day->format('j'),
                'inMonth' => $day->format('Y-m') === $month,
                'today'   => $key === $today,
                'events'  => $byDay[$key] ?? [],
            ];

            if (\count($week) === 7) {
                $weeks[] = $week;
                $week    = [];
            }
        }

        $weekdays = [];

        for ($i = 0; $i < 7; $i++) {
            $weekdays[] = ($weekStart + $i) % 7;
        }

        return [
            'month'    => $month,
            'prev'     => $first->modify('-1 month')->format('Y-m'),
            'next'     => $first->modify('+1 month')->format('Y-m'),
            'first'    => $first->format('Y-m-d H:i:s'),
            'weekdays' => $weekdays,
            'weeks'    => $weeks,
        ];
    }

    /**
     * Local dates an event covers.
     *
     * @param   object         $item  Event row.
     * @param   \DateTimeZone  $tz    Display zone.
     *
     * @return  string[]
     *
     * @since   1.0.0
     */
    private function eventDays(object $item, \DateTimeZone $tz): array
    {
        $utc = new \DateTimeZone('UTC');

        if ((int) $item->all_day === 1) {
            // All-day dates are stored as UTC midnights: use the dates as they are.
            $start = new \DateTimeImmutable(substr((string) $item->start_utc, 0, 10), $utc);
            $end   = new \DateTimeImmutable(substr((string) $item->end_utc, 0, 10), $utc);
        } else {
            $start = (new \DateTimeImmutable((string) $item->start_utc, $utc))->setTimezone($tz);
            $end   = (new \DateTimeImmutable((string) $item->end_utc, $utc))->setTimezone($tz);
            // An event ending exactly at midnight does not cover the next day.
            $end   = $end->modify('-1 second');
            $start = $start->setTime(0, 0);
            $end   = $end->setTime(0, 0)->modify('+1 day');
        }

        $days = [];

        for ($d = $start; $d < $end && \count($days) < 62; $d = $d->modify('+1 day')) {
            $days[] = $d->format('Y-m-d');
        }

        return $days ?: [$start->format('Y-m-d')];
    }

    /**
     * UTC range of the visible month grid (whole weeks).
     *
     * @return  array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     *
     * @since   1.0.0
     */
    public function monthRange(): array
    {
        $tz        = $this->userTimezone();
        $first     = new \DateTimeImmutable($this->getState('display.month') . '-01 00:00:00', $tz);
        $weekStart = (int) ComponentHelper::getParams('com_m365calendar')->get('week_start', 1);
        $offset    = ((int) $first->format('w') - $weekStart + 7) % 7;
        $start     = $first->modify('-' . $offset . ' days');
        $last      = $first->modify('last day of this month');
        $endOffset = (6 - ((int) $last->format('w') - $weekStart + 7) % 7);
        $end       = $last->modify('+' . ($endOffset + 1) . ' days')->setTime(0, 0);
        $utc       = new \DateTimeZone('UTC');

        return [$start->setTimezone($utc), $end->setTimezone($utc)];
    }

    /**
     * The visitor's time zone (user setting or site default).
     *
     * @return  \DateTimeZone
     *
     * @since   1.0.0
     */
    public function userTimezone(): \DateTimeZone
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user !== null) {
            return $user->getTimezone();
        }

        return new \DateTimeZone(Factory::getApplication()->get('offset', 'UTC'));
    }

    /**
     * View levels of the current user.
     *
     * @return  integer[]
     *
     * @since   1.0.0
     */
    private function viewLevels(): array
    {
        $user = Factory::getApplication()->getIdentity();

        return array_values(array_unique(array_map('intval', $user ? $user->getAuthorisedViewLevels() : [1])));
    }

    /**
     * Repository.
     *
     * @return  EventRepository
     *
     * @since   1.0.0
     */
    public function getRepository(): EventRepository
    {
        return new EventRepository($this->getDatabase());
    }
}
