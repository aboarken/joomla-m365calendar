<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Filter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Combines the menu item's preset filters with the visitor's filters.
 *
 * The result is always the AND of both: visitor input can narrow the result
 * but never widen it. Visitor values outside the preset (a calendar or
 * category that the preset does not allow) are ignored.
 *
 * @since  0.1.0
 */
final class FilterResolver
{
    /**
     * Resolve the effective filter.
     *
     * @param   PresetFilter        $preset        Menu item preset (trusted, admin-defined).
     * @param   VisitorFilter       $visitor       Visitor input (untrusted).
     * @param   integer[]           $accessibleIds Published calendars the visitor's view levels allow.
     * @param   \DateTimeImmutable  $now           Now.
     * @param   \DateTimeZone       $siteTz        Zone for fixed preset dates.
     * @param   \DateTimeZone       $visitorTz     Zone for visitor dates.
     *
     * @return  EffectiveFilter
     *
     * @since   0.1.0
     */
    public static function resolve(
        PresetFilter $preset,
        VisitorFilter $visitor,
        array $accessibleIds,
        \DateTimeImmutable $now,
        \DateTimeZone $siteTz,
        \DateTimeZone $visitorTz
    ): EffectiveFilter {
        $accessibleIds = array_values(array_unique(array_map('intval', $accessibleIds)));

        // Calendars: preset ∩ accessible, then optionally the one the visitor picked from that set.
        $allowed = $preset->calendarIds === []
            ? $accessibleIds
            : array_values(array_intersect(array_map('intval', $preset->calendarIds), $accessibleIds));

        $calendarIds = $allowed;

        if ($visitor->calendarId > 0 && \in_array($visitor->calendarId, $allowed, true)) {
            $calendarIds = [$visitor->calendarId];
        }

        // Categories: preset list (null = any), optionally narrowed to the visitor's pick.
        $categories = $preset->categories === [] ? null : array_values($preset->categories);

        if ($visitor->category !== '' && ($categories === null || \in_array($visitor->category, $categories, true))) {
            $categories = [$visitor->category];
        }

        $subjectTerms  = $preset->keyword !== '' ? [$preset->keyword] : [];
        $locationTerms = array_values(array_filter([$preset->location, $visitor->location], static fn (string $v): bool => $v !== ''));

        // Time bounds.
        $endAfter    = null;
        $startBefore = null;
        $endBefore   = null;

        foreach ([$preset->timeRange, $visitor->timeRange] as $range) {
            if ($range === 'upcoming') {
                $endAfter = self::later($endAfter, $now);
            } elseif ($range === 'past') {
                $endBefore = self::earlier($endBefore, $now);
            }
        }

        switch ($preset->dateMode) {
            case 'next':
                $endAfter    = self::later($endAfter, $now);
                $startBefore = self::earlier($startBefore, $now->modify('+' . $preset->days . ' days'));
                break;

            case 'last':
                $endAfter    = self::later($endAfter, $now->modify('-' . $preset->days . ' days'));
                $startBefore = self::earlier($startBefore, $now);
                break;

            case 'fixed':
                if ($preset->dateFrom !== '') {
                    $endAfter = self::later($endAfter, self::dayStart($preset->dateFrom, $siteTz));
                }

                if ($preset->dateTo !== '') {
                    $startBefore = self::earlier($startBefore, self::dayStart($preset->dateTo, $siteTz)->modify('+1 day'));
                }
                break;
        }

        if ($visitor->dateFrom !== '') {
            $endAfter = self::later($endAfter, self::dayStart($visitor->dateFrom, $visitorTz));
        }

        if ($visitor->dateTo !== '') {
            $startBefore = self::earlier($startBefore, self::dayStart($visitor->dateTo, $visitorTz)->modify('+1 day'));
        }

        // Event type.
        $impossible = false;
        $allDay     = match ($preset->eventType) {
            'allday' => true,
            'timed'  => false,
            default  => null,
        };

        if ($visitor->eventType !== 'all') {
            $wanted = $visitor->eventType === 'allday';

            if ($allDay === null) {
                $allDay = $wanted;
            } elseif ($allDay !== $wanted) {
                $impossible = true;
            }
        }

        return new EffectiveFilter(
            $calendarIds,
            $categories,
            $subjectTerms,
            $locationTerms,
            $visitor->search,
            $endAfter,
            $startBefore,
            $endBefore,
            $allDay,
            $impossible
        );
    }

    /**
     * The later of two optional dates.
     *
     * @param   \DateTimeImmutable|null  $a  First.
     * @param   \DateTimeImmutable       $b  Second.
     *
     * @return  \DateTimeImmutable
     *
     * @since   0.1.0
     */
    public static function later(?\DateTimeImmutable $a, \DateTimeImmutable $b): \DateTimeImmutable
    {
        return $a === null || $b > $a ? $b : $a;
    }

    /**
     * The earlier of two optional dates.
     *
     * @param   \DateTimeImmutable|null  $a  First.
     * @param   \DateTimeImmutable       $b  Second.
     *
     * @return  \DateTimeImmutable
     *
     * @since   0.1.0
     */
    public static function earlier(?\DateTimeImmutable $a, \DateTimeImmutable $b): \DateTimeImmutable
    {
        return $a === null || $b < $a ? $b : $a;
    }

    /**
     * Midnight of a Y-m-d date in a zone, as UTC.
     *
     * @param   string         $date  Y-m-d.
     * @param   \DateTimeZone  $tz    Zone.
     *
     * @return  \DateTimeImmutable
     *
     * @since   0.1.0
     */
    public static function dayStart(string $date, \DateTimeZone $tz): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($date . ' 00:00:00', $tz))->setTimezone(new \DateTimeZone('UTC'));
    }
}
