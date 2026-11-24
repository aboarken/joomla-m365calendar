<?php

/**
 * Checks, inside a Joomla site, that uninstalling the package removed all of
 * its data and files (see tests/Integration/run.sh).
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
const _JEXEC = 1;

define('JPATH_BASE', realpath($argv[1] ?? ''));

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/** @var DatabaseInterface $db */
$db     = Factory::getContainer()->get(DatabaseInterface::class);
$failed = [];

foreach ($db->getTableList() as $table) {
    if (str_contains(strtolower($table), 'm365calendar')) {
        $failed[] = "table $table still exists";
    }
}

$queries = [
    'scheduled tasks'        => 'SELECT COUNT(*) FROM ' . $db->quoteName('#__scheduler_tasks') . ' WHERE ' . $db->quoteName('type') . ' = ' . $db->quote('m365calendar.sync'),
    'action log extension'   => 'SELECT COUNT(*) FROM ' . $db->quoteName('#__action_logs_extensions') . ' WHERE ' . $db->quoteName('extension') . ' = ' . $db->quote('com_m365calendar'),
    'action log config'      => 'SELECT COUNT(*) FROM ' . $db->quoteName('#__action_log_config') . ' WHERE ' . $db->quoteName('type_alias') . ' LIKE ' . $db->quote('com_m365calendar.%'),
    'action log option'      => 'SELECT COUNT(*) FROM ' . $db->quoteName('#__extensions') . ' WHERE ' . $db->quoteName('element') . ' = ' . $db->quote('com_actionlogs') . ' AND ' . $db->quoteName('params') . ' LIKE ' . $db->quote('%com_m365calendar%'),
    'extension records'      => 'SELECT COUNT(*) FROM ' . $db->quoteName('#__extensions') . ' WHERE ' . $db->quoteName('element') . ' LIKE ' . $db->quote('%m365%'),
];

foreach ($queries as $what => $sql) {
    if ((int) $db->setQuery($sql)->loadResult() !== 0) {
        $failed[] = "$what left behind";
    }
}

foreach (['administrator/components/com_m365calendar', 'components/com_m365calendar', 'media/com_m365calendar', 'libraries/m365graph', 'plugins/task/m365calendarsync'] as $path) {
    if (file_exists(JPATH_BASE . '/' . $path)) {
        $failed[] = "$path still exists";
    }
}

if ($failed !== []) {
    fwrite(STDERR, "FAILED after uninstall:\n  " . implode("\n  ", $failed) . "\n");
    exit(1);
}

echo "  ok  uninstall removed all tables, records and files\n";
