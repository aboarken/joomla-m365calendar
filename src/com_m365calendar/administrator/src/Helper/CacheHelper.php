<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Helper;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cleans the component's site caches (feeds, category lists) from any
 * application (site, administrator, CLI scheduler).
 *
 * @since  0.1.0
 */
final class CacheHelper
{
    /**
     * Cache group of generated feeds.
     *
     * @var    string
     * @since  0.1.0
     */
    public const GROUP_FEED = 'com_m365calendar_feed';

    /**
     * Cache group of query results.
     *
     * @var    string
     * @since  0.1.0
     */
    public const GROUP_DATA = 'com_m365calendar';

    /**
     * Clean both groups in the site's cache folder.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function cleanSiteCaches(): void
    {
        $app       = Factory::getApplication();
        $cachebase = (string) $app->get('cache_path', '') ?: JPATH_SITE . '/cache';

        foreach ([self::GROUP_FEED, self::GROUP_DATA] as $group) {
            try {
                Factory::getContainer()->get(CacheControllerFactoryInterface::class)
                    ->createCacheController('callback', ['defaultgroup' => $group, 'cachebase' => $cachebase])
                    ->clean($group);
            } catch (\Throwable) {
                // A cache that cannot be cleaned expires on its own (15 minutes).
            }
        }
    }
}
