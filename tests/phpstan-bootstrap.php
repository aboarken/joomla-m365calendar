<?php

/**
 * Constants Joomla defines at runtime, for static analysis.
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') || \define('_JEXEC', 1);
\defined('JPATH_ROOT') || \define('JPATH_ROOT', (string) getenv('JOOMLA_ROOT'));
\defined('JPATH_ADMINISTRATOR') || \define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');
\defined('JVERSION') || \define('JVERSION', '5.0.0');
