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
 * Decides which calendars may appear in a feed.
 *
 * Rules: only published calendars with "Allow in feeds" switched on; public
 * links only include calendars whose access level is one of the guest view
 * levels; personal links only include calendars the user can view; and the
 * menu item's preset calendar list (if any) further restricts the set.
 *
 * @since  0.1.0
 */
final class FeedCalendarResolver
{
    /**
     * Public link mode.
     *
     * @var    string
     * @since  0.1.0
     */
    public const MODE_PUBLIC = 'public';

    /**
     * Personal link mode.
     *
     * @var    string
     * @since  0.1.0
     */
    public const MODE_PERSONAL = 'personal';

    /**
     * Resolve the calendar IDs allowed in a feed.
     *
     * @param   iterable<object>  $calendars        Rows with id, access, published, allow_feeds.
     * @param   string            $mode             MODE_PUBLIC or MODE_PERSONAL.
     * @param   integer[]         $guestViewLevels  View levels of a guest.
     * @param   integer[]         $userViewLevels   View levels of the token's user (personal mode).
     * @param   integer[]         $presetIds        Menu item preset calendars (empty = all).
     *
     * @return  integer[]
     *
     * @since   0.1.0
     */
    public static function resolve(iterable $calendars, string $mode, array $guestViewLevels, array $userViewLevels, array $presetIds): array
    {
        if (!\in_array($mode, [self::MODE_PUBLIC, self::MODE_PERSONAL], true)) {
            return [];
        }

        $levels    = array_map('intval', $mode === self::MODE_PUBLIC ? $guestViewLevels : $userViewLevels);
        $presetIds = array_map('intval', $presetIds);
        $allowed   = [];

        foreach ($calendars as $calendar) {
            $id = (int) $calendar->id;

            if ((int) $calendar->published !== 1 || (int) $calendar->allow_feeds !== 1) {
                continue;
            }

            if (!\in_array((int) $calendar->access, $levels, true)) {
                continue;
            }

            if ($presetIds !== [] && !\in_array($id, $presetIds, true)) {
                continue;
            }

            $allowed[] = $id;
        }

        return $allowed;
    }
}
