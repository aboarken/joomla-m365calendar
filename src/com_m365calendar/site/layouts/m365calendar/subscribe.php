<?php

/**
 * @package     Joomla.Site
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
 * "Subscribe" button with the feed link, copy button and (personal) reset form.
 *
 * @var \Joomla\CMS\Layout\FileLayout $this
 * @var array $displayData  ['subscription' => array]
 */

$sub = $displayData['subscription'];
$id  = 'm365calendar-feed-' . bin2hex(random_bytes(4));
?>
<details class="m365calendar-subscribe">
    <summary class="btn btn-sm btn-outline-secondary"><?php echo Text::_('COM_M365CALENDAR_SUBSCRIBE'); ?></summary>
    <div class="border rounded p-3 mt-2 m365calendar-subscribe-panel">
        <?php if ($sub['url'] !== '') : ?>
            <p class="small mb-2">
                <?php echo Text::_($sub['mode'] === 'public' ? 'COM_M365CALENDAR_SUBSCRIBE_HELP_PUBLIC' : 'COM_M365CALENDAR_SUBSCRIBE_HELP_PERSONAL_NEW'); ?>
            </p>
            <label class="form-label" for="<?php echo $id; ?>"><?php echo Text::_('COM_M365CALENDAR_SUBSCRIBE_LINK_LABEL'); ?></label>
            <div class="input-group mb-2">
                <input type="text" class="form-control" id="<?php echo $id; ?>" value="<?php echo $this->escape($sub['url']); ?>" readonly>
                <button type="button" class="btn btn-secondary" data-m365calendar-copy="<?php echo $id; ?>">
                    <?php echo Text::_('COM_M365CALENDAR_SUBSCRIBE_COPY'); ?>
                </button>
            </div>
            <p class="visually-hidden" role="status" aria-live="polite" data-m365calendar-copy-status="<?php echo $id; ?>"></p>
            <p class="mb-2">
                <a href="<?php echo $this->escape($sub['webcal']); ?>" rel="nofollow"><?php echo Text::_('COM_M365CALENDAR_SUBSCRIBE_WEBCAL'); ?></a>
            </p>
        <?php elseif ($sub['mode'] === 'personal') : ?>
            <p class="small mb-2">
                <?php echo Text::_($sub['hasToken'] ? 'COM_M365CALENDAR_SUBSCRIBE_HELP_PERSONAL_EXISTING' : 'COM_M365CALENDAR_SUBSCRIBE_HELP_PERSONAL_NONE'); ?>
            </p>
        <?php endif; ?>

        <?php if ($sub['mode'] === 'personal') : ?>
            <form action="<?php echo $this->escape($sub['action']); ?>" method="post" class="mt-2">
                <button type="submit" class="btn btn-sm <?php echo $sub['hasToken'] ? 'btn-outline-danger' : 'btn-primary'; ?>">
                    <?php echo Text::_($sub['hasToken'] ? 'COM_M365CALENDAR_SUBSCRIBE_RESET' : 'COM_M365CALENDAR_SUBSCRIBE_CREATE'); ?>
                </button>
                <?php if ($sub['hasToken']) : ?>
                    <p class="small text-body-secondary mt-1 mb-0"><?php echo Text::_('COM_M365CALENDAR_SUBSCRIBE_RESET_HELP'); ?></p>
                <?php endif; ?>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        <?php endif; ?>
    </div>
</details>
