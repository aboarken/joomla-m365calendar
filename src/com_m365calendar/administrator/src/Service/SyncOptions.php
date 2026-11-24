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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sync window and behaviour options.
 *
 * @since  0.1.0
 */
final class SyncOptions
{
    /**
     * Constructor.
     *
     * @param   integer  $daysBack     Days before today to sync.
     * @param   integer  $daysAhead    Days after today to sync.
     * @param   string   $timezone     IANA time zone sent in the Prefer header.
     * @param   integer  $lockMinutes  Lock expiry in minutes.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly int $daysBack = 30,
        public readonly int $daysAhead = 365,
        public readonly string $timezone = 'UTC',
        public readonly int $lockMinutes = 15
    ) {
    }

    /**
     * Create from component parameters.
     *
     * @param   Registry  $params      Component parameters.
     * @param   string    $siteOffset  Site time zone (fallback).
     *
     * @return  self
     *
     * @since   0.1.0
     */
    public static function fromParams(Registry $params, string $siteOffset): self
    {
        $tz = trim((string) $params->get('timezone', '')) ?: $siteOffset;

        if (!\in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = 'UTC';
        }

        return new self(
            max(0, min(730, (int) $params->get('sync_days_back', 30))),
            max(1, min(1095, (int) $params->get('sync_days_ahead', 365))),
            $tz,
            max(2, min(120, (int) $params->get('lock_minutes', 15)))
        );
    }
}
