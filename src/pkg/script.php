<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  pkg_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Install script of pkg_m365calendar.
 *
 * @since  0.1.0
 */
return new class () implements ServiceProviderInterface {
    /**
     * Registers the install script.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class ($container->get(AdministratorApplication::class), $container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                /**
                 * Minimum Joomla version.
                 *
                 * @var    string
                 * @since  0.1.0
                 */
                private const MIN_JOOMLA = '5.0.0';

                /**
                 * Minimum PHP version.
                 *
                 * @var    string
                 * @since  0.1.0
                 */
                private const MIN_PHP = '8.1.0';

                /**
                 * Tables of the component.
                 *
                 * @var    string[]
                 * @since  0.1.0
                 */
                private const TABLES = [
                    '#__m365calendar_event_categories',
                    '#__m365calendar_events',
                    '#__m365calendar_sync',
                    '#__m365calendar_feed_tokens',
                    '#__m365calendar_calendars',
                ];

                /**
                 * Package version found before an update ('' on install).
                 *
                 * @var    string
                 * @since  0.1.2
                 */
                private string $previousVersion = '';

                /**
                 * Constructor.
                 *
                 * @param   AdministratorApplication  $app  The application.
                 * @param   DatabaseInterface         $db   The database.
                 *
                 * @since   0.1.0
                 */
                public function __construct(private AdministratorApplication $app, private DatabaseInterface $db)
                {
                }

                /**
                 * Function called after the extension is installed.
                 *
                 * @param   InstallerAdapter  $adapter  The adapter calling this method.
                 *
                 * @return  boolean
                 *
                 * @since   0.1.0
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Function called after the extension is updated.
                 *
                 * @param   InstallerAdapter  $adapter  The adapter calling this method.
                 *
                 * @return  boolean
                 *
                 * @since   0.1.0
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Remove all three extensions' data: tables, scheduled tasks, action log registration.
                 *
                 * The child extensions themselves are removed by the package installer.
                 *
                 * @param   InstallerAdapter  $adapter  The adapter calling this method.
                 *
                 * @return  boolean
                 *
                 * @since   0.1.0
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    $db = $this->db;

                    foreach (self::TABLES as $table) {
                        try {
                            $db->dropTable($table, true);
                        } catch (\RuntimeException) {
                            // Continue with the other tables.
                        }
                    }

                    $cleanups = [
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__scheduler_tasks'))
                            ->where($db->quoteName('type') . ' = ' . $db->quote('m365calendar.sync')),
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__action_logs_extensions'))
                            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_m365calendar')),
                        $db->getQuery(true)
                            ->delete($db->quoteName('#__action_log_config'))
                            ->where($db->quoteName('type_alias') . ' = ' . $db->quote('com_m365calendar.calendar')),
                    ];

                    foreach ($cleanups as $query) {
                        try {
                            $db->setQuery($query)->execute();
                        } catch (\RuntimeException) {
                            // Table may not exist (e.g. scheduler not installed).
                        }
                    }

                    $this->setActionLogging(false);

                    $this->app->enqueueMessage(Text::_('PKG_M365CALENDAR_UNINSTALLED'));

                    return true;
                }

                /**
                 * Check requirements before install/update.
                 *
                 * @param   string            $type     install, update, discover_install or uninstall.
                 * @param   InstallerAdapter  $adapter  The adapter calling this method.
                 *
                 * @return  boolean
                 *
                 * @since   0.1.0
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'uninstall') {
                        return true;
                    }

                    $this->previousVersion = $type === 'update' ? $this->installedVersion() : '';

                    if (version_compare(JVERSION, self::MIN_JOOMLA, '<')) {
                        $this->app->enqueueMessage(Text::sprintf('PKG_M365CALENDAR_ERROR_JOOMLA', self::MIN_JOOMLA), 'error');

                        return false;
                    }

                    if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
                        $this->app->enqueueMessage(Text::sprintf('PKG_M365CALENDAR_ERROR_PHP', self::MIN_PHP), 'error');

                        return false;
                    }

                    if (!\extension_loaded('openssl')) {
                        $this->app->enqueueMessage(Text::_('PKG_M365CALENDAR_ERROR_OPENSSL'), 'error');

                        return false;
                    }

                    return true;
                }

                /**
                 * Enable the task plugin after a fresh install.
                 *
                 * @param   string            $type     install, update or discover_install.
                 * @param   InstallerAdapter  $adapter  The adapter calling this method.
                 *
                 * @return  boolean
                 *
                 * @since   0.1.0
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'update') {
                        // Versions before 0.1.2 did not switch action logging on at install.
                        if ($this->previousVersion !== '' && version_compare($this->previousVersion, '0.1.2', '<')) {
                            $this->setActionLogging(true);
                        }

                        return true;
                    }

                    if ($type !== 'install' && $type !== 'discover_install') {
                        return true;
                    }

                    $db    = $this->db;
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('enabled') . ' = 1')
                        ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                        ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('m365calendarsync'));

                    $db->setQuery($query)->execute();

                    $this->setActionLogging(true);

                    $this->app->enqueueMessage(Text::_('PKG_M365CALENDAR_POSTINSTALL'));

                    return true;
                }

                /**
                 * Version of the package that is installed right now (before an update).
                 *
                 * @return  string  Empty when unknown.
                 *
                 * @since   0.1.2
                 */
                private function installedVersion(): string
                {
                    try {
                        $db    = $this->db;
                        $query = $db->getQuery(true)
                            ->select($db->quoteName('manifest_cache'))
                            ->from($db->quoteName('#__extensions'))
                            ->where($db->quoteName('type') . ' = ' . $db->quote('package'))
                            ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_m365calendar'));

                        $cache = json_decode((string) $db->setQuery($query)->loadResult(), true);

                        return \is_array($cache) && \is_string($cache['version'] ?? null) ? $cache['version'] : '';
                    } catch (\Throwable) {
                        return '';
                    }
                }

                /**
                 * Add the component to (or remove it from) the extensions whose content
                 * changes the core "Action Log - Joomla" plugin records
                 * (Users > User Actions Log > Options > Events To Log).
                 *
                 * Done once at install so calendar create/edit/delete are logged out of
                 * the box; an administrator can untick it afterwards.
                 *
                 * @param   boolean  $enable  True to add, false to remove.
                 *
                 * @return  void
                 *
                 * @since   0.1.2
                 */
                private function setActionLogging(bool $enable): void
                {
                    try {
                        $db    = $this->db;
                        $query = $db->getQuery(true)
                            ->select($db->quoteName(['extension_id', 'params']))
                            ->from($db->quoteName('#__extensions'))
                            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                            ->where($db->quoteName('element') . ' = ' . $db->quote('com_actionlogs'));

                        $row = $db->setQuery($query)->loadObject();

                        if (!$row) {
                            return;
                        }

                        $params = json_decode((string) $row->params, true);
                        $params = \is_array($params) ? $params : [];
                        $list   = array_values(array_filter((array) ($params['loggable_extensions'] ?? []), '\is_string'));
                        $has    = \in_array('com_m365calendar', $list, true);

                        if ($enable === $has) {
                            return;
                        }

                        $params['loggable_extensions'] = $enable
                            ? array_merge($list, ['com_m365calendar'])
                            : array_values(array_diff($list, ['com_m365calendar']));

                        $json  = json_encode($params, JSON_THROW_ON_ERROR);
                        $id    = (int) $row->extension_id;
                        $query = $db->getQuery(true)
                            ->update($db->quoteName('#__extensions'))
                            ->set($db->quoteName('params') . ' = :params')
                            ->where($db->quoteName('extension_id') . ' = :id')
                            ->bind(':params', $json)
                            ->bind(':id', $id, ParameterType::INTEGER);

                        $db->setQuery($query)->execute();
                    } catch (\Throwable) {
                        // Action logging is optional; never fail the install for it.
                    }
                }
            }
        );
    }
};
