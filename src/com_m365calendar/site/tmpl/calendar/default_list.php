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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\M365calendar\Site\View\Calendar\HtmlView $this */

$layoutOptions = ['component' => 'com_m365calendar'];
?>
<div class="m365calendar-list" aria-live="polite">
    <?php if (empty($this->items)) : ?>
        <div class="alert alert-info" role="status">
            <?php echo Text::_('COM_M365CALENDAR_NO_EVENTS'); ?>
        </div>
    <?php else : ?>
        <ul class="list-unstyled m365calendar-events">
            <?php foreach ($this->items as $item) : ?>
                <li class="mb-3">
                    <?php echo LayoutHelper::render(
                        'm365calendar.event_card',
                        ['item' => $item, 'params' => $this->params, 'itemId' => $this->itemId],
                        null,
                        $layoutOptions
                    ); ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($this->pagination && $this->pagination->pagesTotal > 1) : ?>
            <div class="com-m365calendar-pagination w-100">
                <p class="counter float-end pt-3 pe-2">
                    <?php echo $this->pagination->getPagesCounter(); ?>
                </p>
                <?php echo $this->pagination->getPagesLinks(); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
