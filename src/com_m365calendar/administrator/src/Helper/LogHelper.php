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

use Joomla\CMS\Log\Log;
use Psr\Log\LoggerInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Registers the component's dedicated log categories with Joomla's Log API.
 *
 * Log entries never contain tokens, feed URLs or event content: only status
 * codes, request-ids, calendar IDs, menu item IDs and user IDs.
 *
 * @since  0.1.0
 */
final class LogHelper
{
    /**
     * Category for sync messages.
     *
     * @var    string
     * @since  0.1.0
     */
    public const CATEGORY_SYNC = 'com_m365calendar.sync';

    /**
     * Category for feed messages.
     *
     * @var    string
     * @since  0.1.0
     */
    public const CATEGORY_FEED = 'com_m365calendar.feed';

    /**
     * Whether the logger is registered.
     *
     * @var    boolean
     * @since  0.1.0
     */
    private static bool $registered = false;

    /**
     * Register the text file logger for the component categories (once).
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        Log::addLogger(
            ['text_file' => 'com_m365calendar.php', 'text_entry_format' => '{DATETIME} {PRIORITY} {CATEGORY} {MESSAGE}'],
            Log::ALL,
            [self::CATEGORY_SYNC, self::CATEGORY_FEED]
        );

        self::$registered = true;
    }

    /**
     * PSR-3 logger delegating to Joomla's Log API.
     *
     * Pass the category in the context: ['category' => LogHelper::CATEGORY_SYNC].
     *
     * @return  LoggerInterface
     *
     * @since   0.1.0
     */
    public static function logger(): LoggerInterface
    {
        self::register();

        return Log::createDelegatedLogger();
    }
}
