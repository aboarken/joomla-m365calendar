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
use Joomla\Component\M365calendar\Site\Helper\EventHelper;

/**
 * Calendar page. Override in templates/YOUR_TEMPLATE/html/com_m365calendar/calendar/default.php
 *
 * @var \Joomla\Component\M365calendar\Site\View\Calendar\HtmlView $this
 */

$layoutOptions = ['component' => 'com_m365calendar'];
$showToggle    = (int) $this->params->get('show_view_toggle', 1) === 1;
?>
<div class="m365calendar m365calendar-page<?php echo $this->pageclass_sfx; ?>">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <div class="page-header">
            <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
        </div>
    <?php endif; ?>

    <div class="m365calendar-toolbar d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <?php if ($showToggle) : ?>
            <nav class="btn-group" aria-label="<?php echo Text::_('COM_M365CALENDAR_VIEW_TOGGLE_LABEL'); ?>">
                <a class="btn btn-sm <?php echo $this->mode === 'list' ? 'btn-primary' : 'btn-outline-primary'; ?>"
                   href="<?php echo $this->escape(EventHelper::calendarUrl($this->itemId, ['mode' => 'list'])); ?>"
                   <?php echo $this->mode === 'list' ? 'aria-current="page"' : ''; ?>>
                    <?php echo Text::_('COM_M365CALENDAR_MODE_LIST'); ?>
                </a>
                <a class="btn btn-sm <?php echo $this->mode === 'month' ? 'btn-primary' : 'btn-outline-primary'; ?>"
                   href="<?php echo $this->escape(EventHelper::calendarUrl($this->itemId, ['mode' => 'month'])); ?>"
                   <?php echo $this->mode === 'month' ? 'aria-current="page"' : ''; ?>>
                    <?php echo Text::_('COM_M365CALENDAR_MODE_MONTH'); ?>
                </a>
            </nav>
        <?php endif; ?>

        <?php if ($this->subscription !== null) : ?>
            <?php echo LayoutHelper::render('m365calendar.subscribe', ['subscription' => $this->subscription], null, $layoutOptions); ?>
        <?php endif; ?>
    </div>

    <?php if ((int) $this->params->get('show_filter_bar', 1) === 1) : ?>
        <?php echo LayoutHelper::render(
            'm365calendar.filter_bar',
            [
                'params'     => $this->params,
                'visitor'    => $this->visitor,
                'calendars'  => $this->calendarOptions,
                'categories' => $this->categoryOptions,
                'sort'       => (string) $this->state->get('list.sort'),
                'itemId'     => $this->itemId,
                'mode'       => $this->mode,
                'month'      => (string) $this->state->get('display.month'),
            ],
            null,
            $layoutOptions
        ); ?>
    <?php endif; ?>

    <?php echo $this->loadTemplate($this->mode === 'month' ? 'month' : 'list'); ?>
</div>
