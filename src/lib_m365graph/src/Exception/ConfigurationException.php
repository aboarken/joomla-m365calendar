<?php

/**
 * @package     M365Graph
 * @subpackage  lib_m365graph
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Graph\Exception;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Invalid or incomplete connection settings.
 *
 * @since  1.0.0
 */
class ConfigurationException extends \InvalidArgumentException
{
    /**
     * Constructor.
     *
     * @param   string  $message  Technical message.
     * @param   string  $reason   Short machine-readable reason, e.g. "path_in_webroot".
     *
     * @since   1.0.0
     */
    public function __construct(string $message, private readonly string $reason = '')
    {
        parent::__construct($message);
    }

    /**
     * Machine-readable reason, used by callers to pick a translated message.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
