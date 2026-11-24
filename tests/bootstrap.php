<?php

/**
 * PHPUnit bootstrap. Unit tests run without a Joomla installation: they only
 * cover classes that depend on PHP, the Joomla Framework packages installed
 * by Composer (database, registry) and PSR-3.
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\define('_JEXEC', 1);

// A fake web root: key files inside it must be rejected.
$webRoot = sys_get_temp_dir() . '/m365calendar-tests-webroot';

if (!is_dir($webRoot)) {
    mkdir($webRoot, 0700, true);
}

\define('JPATH_ROOT', $webRoot);

require dirname(__DIR__) . '/vendor/autoload.php';
