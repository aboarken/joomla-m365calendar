<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Site\Helper;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\M365calendar\Administrator\Feed\AddToCalendarLinks;
use Joomla\Component\M365calendar\Administrator\Feed\IcsEvent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * View helpers for events: URLs, date display, "Add to calendar" links and JSON-LD.
 *
 * @since  0.1.0
 */
final class EventHelper
{
    /**
     * URL of the event page under a Calendar menu item.
     *
     * @param   integer  $id        Event ID.
     * @param   integer  $itemId    Menu item ID.
     * @param   boolean  $absolute  Absolute URL.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function eventUrl(int $id, int $itemId, bool $absolute = false): string
    {
        return Route::_('index.php?option=com_m365calendar&view=event&id=' . $id . '&Itemid=' . $itemId, !$absolute, Route::TLS_IGNORE, $absolute);
    }

    /**
     * URL of the Calendar page.
     *
     * @param   integer               $itemId    Menu item ID.
     * @param   array<string, mixed>  $vars      Extra query variables.
     * @param   boolean               $absolute  Absolute URL.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function calendarUrl(int $itemId, array $vars = [], bool $absolute = false): string
    {
        $url = 'index.php?option=com_m365calendar&view=calendar&Itemid=' . $itemId;

        if ($vars !== []) {
            $url .= '&' . http_build_query($vars, '', '&', PHP_QUERY_RFC3986);
        }

        return Route::_($url, !$absolute, Route::TLS_IGNORE, $absolute);
    }

    /**
     * Public link to the page of an event (event page if enabled, else the calendar page).
     *
     * @param   object   $row         Event row.
     * @param   integer  $itemId      Menu item ID.
     * @param   boolean  $detailPage  Whether the event page is enabled.
     *
     * @return  string  Absolute URL.
     *
     * @since   0.1.0
     */
    public static function publicUrl(object $row, int $itemId, bool $detailPage): string
    {
        return $detailPage ? self::eventUrl((int) $row->id, $itemId, true) : self::calendarUrl($itemId, [], true);
    }

    /**
     * Value object with only the fields allowed in feeds and links.
     *
     * @param   object  $row  Event row.
     * @param   string  $url  Event URL.
     *
     * @return  IcsEvent
     *
     * @since   0.1.0
     */
    public static function icsEvent(object $row, string $url): IcsEvent
    {
        return IcsEvent::fromRow($row, $url);
    }

    /**
     * "Add to my calendar" links, built on the server.
     *
     * @param   object   $row         Event row.
     * @param   integer  $itemId      Menu item ID.
     * @param   boolean  $detailPage  Event page enabled.
     *
     * @return  array{outlook: string, google: string, ics: string}
     *
     * @since   0.1.0
     */
    public static function addToCalendarLinks(object $row, int $itemId, bool $detailPage): array
    {
        $event = self::icsEvent($row, self::publicUrl($row, $itemId, $detailPage));

        return [
            'outlook' => AddToCalendarLinks::outlook($event),
            'google'  => AddToCalendarLinks::google($event),
            'ics'     => Route::_('index.php?option=com_m365calendar&task=feed.event&format=ics&id=' . (int) $row->id . '&Itemid=' . $itemId),
        ];
    }

    /**
     * Formatted date range plus machine-readable values for <time>.
     *
     * Timed events are shown in the user's (or site's) time zone, all-day
     * events by their date only.
     *
     * @param   object  $row         Event row (start_utc, end_utc, all_day).
     * @param   string  $dateFormat  Language key of the date format.
     *
     * @return  array{start: string, end: string, startIso: string, endIso: string, allDay: bool, sameDay: bool}
     *
     * @since   0.1.0
     */
    public static function dateRange(object $row, string $dateFormat = 'DATE_FORMAT_LC1'): array
    {
        $date   = Text::_($dateFormat);
        $time   = Text::_('COM_M365CALENDAR_TIME_FORMAT');
        $allDay = (int) $row->all_day === 1;

        if ($allDay) {
            $lastDay = (new \DateTimeImmutable((string) $row->end_utc, new \DateTimeZone('UTC')))->modify('-1 day');
            $first   = (new \DateTimeImmutable((string) $row->start_utc, new \DateTimeZone('UTC')));

            if ($lastDay < $first) {
                $lastDay = $first;
            }

            $sameDay = $lastDay->format('Y-m-d') === $first->format('Y-m-d');

            return [
                'start'    => HTMLHelper::_('date', $first->format('Y-m-d H:i:s'), $date, 'UTC'),
                'end'      => $sameDay ? '' : HTMLHelper::_('date', $lastDay->format('Y-m-d H:i:s'), $date, 'UTC'),
                'startIso' => $first->format('Y-m-d'),
                'endIso'   => $lastDay->format('Y-m-d'),
                'allDay'   => true,
                'sameDay'  => $sameDay,
            ];
        }

        $sameDay = HTMLHelper::_('date', $row->start_utc, 'Y-m-d') === HTMLHelper::_('date', $row->end_utc, 'Y-m-d');

        return [
            'start'    => HTMLHelper::_('date', $row->start_utc, $date) . ' ' . HTMLHelper::_('date', $row->start_utc, $time),
            'end'      => $sameDay
                ? HTMLHelper::_('date', $row->end_utc, $time)
                : HTMLHelper::_('date', $row->end_utc, $date) . ' ' . HTMLHelper::_('date', $row->end_utc, $time),
            'startIso' => HTMLHelper::_('date', $row->start_utc, 'c'),
            'endIso'   => HTMLHelper::_('date', $row->end_utc, 'c'),
            'allDay'   => false,
            'sameDay'  => $sameDay,
        ];
    }

    /**
     * Decoded categories of a row.
     *
     * @param   object  $row  Event row.
     *
     * @return  string[]
     *
     * @since   0.1.0
     */
    public static function categories(object $row): array
    {
        $categories = json_decode((string) ($row->categories ?? '[]'), true);

        return \is_array($categories) ? array_values(array_filter($categories, 'is_string')) : [];
    }

    /**
     * schema.org Event data for rows.
     *
     * @param   object[]  $rows        Event rows.
     * @param   integer   $itemId      Menu item ID.
     * @param   boolean   $detailPage  Event page enabled.
     *
     * @return  string  JSON, safe to place inside a script element.
     *
     * @since   0.1.0
     */
    public static function jsonLd(array $rows, int $itemId, bool $detailPage): string
    {
        $events = [];

        foreach ($rows as $row) {
            $range = self::dateRange($row);
            $event = [
                '@type'       => 'Event',
                'name'        => (string) $row->subject,
                'startDate'   => $range['startIso'],
                'endDate'     => $range['allDay']
                    ? (new \DateTimeImmutable((string) $row->end_utc, new \DateTimeZone('UTC')))->format('Y-m-d')
                    : $range['endIso'],
                'eventStatus' => 'https://schema.org/EventScheduled',
                'url'         => self::publicUrl($row, $itemId, $detailPage),
            ];

            if ((string) $row->location !== '') {
                $event['location'] = ['@type' => 'Place', 'name' => (string) $row->location];
            }

            $events[] = $event;
        }

        $data = \count($events) === 1
            ? ['@context' => 'https://schema.org'] + $events[0]
            : ['@context' => 'https://schema.org', '@graph' => $events];

        return (string) json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}
