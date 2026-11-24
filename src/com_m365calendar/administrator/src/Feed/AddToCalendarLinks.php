<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Feed;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds "Add to my calendar" links on the server, with URL-encoded values.
 *
 * @since  1.0.0
 */
final class AddToCalendarLinks
{
    /**
     * Outlook on the web compose deeplink.
     *
     * @param   IcsEvent  $event  Event.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function outlook(IcsEvent $event): string
    {
        $params = [
            'path'     => '/calendar/action/compose',
            'rru'      => 'addevent',
            'subject'  => self::plain($event->subject),
            'location' => self::plain($event->location),
            'body'     => $event->url,
        ];

        if ($event->allDay) {
            $params['allday']  = 'true';
            $params['startdt'] = $event->startUtc->format('Y-m-d');
            $params['enddt']   = $event->endUtc->format('Y-m-d');
        } else {
            $params['startdt'] = $event->startUtc->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            $params['enddt']   = $event->endUtc->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }

        return 'https://outlook.office.com/calendar/0/deeplink/compose?' . http_build_query(array_filter($params, 'strlen'), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Google Calendar template link.
     *
     * @param   IcsEvent  $event  Event.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function google(IcsEvent $event): string
    {
        $dates = $event->allDay
            ? $event->startUtc->format('Ymd') . '/' . $event->endUtc->format('Ymd')
            : IcsWriter::utc($event->startUtc) . '/' . IcsWriter::utc($event->endUtc);

        $params = [
            'action'   => 'TEMPLATE',
            'text'     => self::plain($event->subject),
            'dates'    => $dates,
            'location' => self::plain($event->location),
            'details'  => $event->url,
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query(array_filter($params, 'strlen'), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Remove control characters.
     *
     * @param   string  $text  Text.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private static function plain(string $text): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '');
    }
}
