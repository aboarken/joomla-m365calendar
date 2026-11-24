<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Sync status badge.
 *
 * @var  array  $displayData  ['status' => string|null, 'last_run' => string|null, 'count' => int|null]
 */
$status  = (string) ($displayData['status'] ?? '');
$lastRun = $displayData['last_run'] ?? null;
$count   = (int) ($displayData['count'] ?? 0);

if (!$lastRun) : ?>
    <span class="badge bg-secondary"><?php echo Text::_('COM_M365CALENDAR_SYNC_STATUS_NEVER'); ?></span>
<?php else : ?>
    <?php if ($status === 'ok') : ?>
        <span class="badge bg-success"><?php echo Text::_('COM_M365CALENDAR_SYNC_STATUS_OK'); ?></span>
    <?php else : ?>
        <span class="badge bg-danger"><?php echo Text::_('COM_M365CALENDAR_SYNC_STATUS_ERROR'); ?></span>
    <?php endif; ?>
    <span class="small"><?php echo HTMLHelper::_('date', $lastRun, Text::_('DATE_FORMAT_LC5')); ?></span>
    <div class="small text-body-secondary"><?php echo Text::plural('COM_M365CALENDAR_N_EVENTS', $count); ?></div>
<?php endif;
