<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.m365calendarsync
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Task\M365calendarsync\Extension\M365calendarsync;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Service provider for plg_task_m365calendarsync.
 *
 * @since  1.0.0
 */
return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container): M365calendarsync {
                $config = (array) PluginHelper::getPlugin('task', 'm365calendarsync');

                // Joomla 5: __construct(DispatcherInterface $dispatcher, array $config).
                // Joomla 6: __construct($config); passing the dispatcher is deprecated and
                // removed in 7.0 (the plugin helper sets the dispatcher itself).
                $plugin = (new \ReflectionMethod(CMSPlugin::class, '__construct'))->getNumberOfParameters() > 1
                    ? new M365calendarsync($container->get(DispatcherInterface::class), $config)
                    : new M365calendarsync($config);

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
