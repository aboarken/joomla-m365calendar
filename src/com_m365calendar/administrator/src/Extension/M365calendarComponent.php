<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\Component\M365calendar\Administrator\Feed\DatabaseFeedTokenStore;
use Joomla\Component\M365calendar\Administrator\Feed\FeedTokenService;
use Joomla\Component\M365calendar\Administrator\Helper\CacheHelper;
use Joomla\Component\M365calendar\Administrator\Helper\LogHelper;
use Joomla\Component\M365calendar\Administrator\Service\ConnectionSettingsFactory;
use Joomla\Component\M365calendar\Administrator\Service\DatabaseEventStore;
use Joomla\Component\M365calendar\Administrator\Service\SyncOptions;
use Joomla\Component\M365calendar\Administrator\Service\SyncService;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use M365Graph\Client;
use M365Graph\ConnectionSettings;
use M365Graph\GraphClientInterface;
use Psr\Container\ContainerInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Component class for com_m365calendar.
 *
 * Exposes the SyncService so the task plugin can use it without duplicating
 * any sync logic.
 *
 * @since  1.0.0
 */
class M365calendarComponent extends MVCComponent implements BootableExtensionInterface, RouterServiceInterface, DatabaseAwareInterface
{
    use RouterServiceTrait;
    use DatabaseAwareTrait;

    /**
     * Cache group for generated feeds.
     *
     * @var    string
     * @since  1.0.0
     */
    public const FEED_CACHE_GROUP = CacheHelper::GROUP_FEED;

    /**
     * Booting the extension. Nothing global is registered here.
     *
     * @param   ContainerInterface  $container  The container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function boot(ContainerInterface $container): void
    {
    }

    /**
     * Build the sync service from the component options.
     *
     * @return  SyncService
     *
     * @since   1.0.0
     */
    public function getSyncService(): SyncService
    {
        LogHelper::register();

        $params = ComponentHelper::getParams('com_m365calendar');

        return new SyncService(
            new DatabaseEventStore($this->getDatabase()),
            static fn (ConnectionSettings $settings): GraphClientInterface => Client::create($settings, JPATH_ROOT),
            ConnectionSettingsFactory::fromParams($params),
            SyncOptions::fromParams($params, Factory::getApplication()->get('offset', 'UTC')),
            LogHelper::logger(),
            null,
            // New data: drop cached feeds and lists.
            static fn () => CacheHelper::cleanSiteCaches()
        );
    }

    /**
     * Build the feed token service.
     *
     * @return  FeedTokenService
     *
     * @since   1.0.0
     */
    public function getFeedTokenService(): FeedTokenService
    {
        return new FeedTokenService(new DatabaseFeedTokenStore($this->getDatabase()));
    }
}
