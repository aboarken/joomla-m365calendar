<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Service;

use Joomla\Registry\Registry;
use M365Graph\ConnectionSettings;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds the library's ConnectionSettings from the component options.
 *
 * @since  0.1.0
 */
final class ConnectionSettingsFactory
{
    /**
     * Create settings from the component parameters.
     *
     * @param   Registry  $params  Component parameters.
     *
     * @return  ConnectionSettings
     *
     * @since   0.1.0
     */
    public static function fromParams(Registry $params): ConnectionSettings
    {
        return new ConnectionSettings(
            trim((string) $params->get('tenant_id', '')),
            trim((string) $params->get('client_id', '')),
            trim((string) $params->get('certificate_path', '')),
            trim((string) $params->get('private_key_path', ''))
        );
    }
}
