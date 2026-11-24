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
use Joomla\Component\M365calendar\Site\Helper\EventHelper;

/**
 * Visitor filter bar (GET form). Calendar and category lists only contain
 * values allowed by the menu item's preset.
 *
 * @var \Joomla\CMS\Layout\FileLayout $this
 * @var array $displayData
 */

$params     = $displayData['params'];
$visitor    = $displayData['visitor'];
$calendars  = $displayData['calendars'];
$categories = $displayData['categories'];
$sort       = $displayData['sort'];
$itemId     = (int) $displayData['itemId'];
$prefix     = 'm365calendar-filter-' . $itemId . '-';
$show       = static fn (string $key): bool => (int) $params->get($key, 1) === 1;
?>
<form class="m365calendar-filters card card-body mb-3" method="get" action="<?php echo EventHelper::calendarUrl($itemId); ?>" role="search" aria-label="<?php echo Text::_('COM_M365CALENDAR_FILTER_FORM_LABEL'); ?>">
    <div class="row g-2 align-items-end">
        <?php if ($show('filter_show_search')) : ?>
            <div class="col-12 col-md-4">
                <label class="form-label" for="<?php echo $prefix; ?>search"><?php echo Text::_('COM_M365CALENDAR_FILTER_SEARCH'); ?></label>
                <input type="search" class="form-control" name="filter_search" id="<?php echo $prefix; ?>search" value="<?php echo $this->escape($visitor->search); ?>" maxlength="100">
            </div>
        <?php endif; ?>

        <?php if ($show('filter_show_dates')) : ?>
            <div class="col-6 col-md-2">
                <label class="form-label" for="<?php echo $prefix; ?>from"><?php echo Text::_('COM_M365CALENDAR_FILTER_FROM'); ?></label>
                <input type="date" class="form-control" name="filter_from" id="<?php echo $prefix; ?>from" value="<?php echo $this->escape($visitor->dateFrom); ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="<?php echo $prefix; ?>to"><?php echo Text::_('COM_M365CALENDAR_FILTER_TO'); ?></label>
                <input type="date" class="form-control" name="filter_to" id="<?php echo $prefix; ?>to" value="<?php echo $this->escape($visitor->dateTo); ?>">
            </div>
        <?php endif; ?>

        <?php if ($show('filter_show_calendar') && \count($calendars) > 1) : ?>
            <div class="col-12 col-md-4">
                <label class="form-label" for="<?php echo $prefix; ?>calendar"><?php echo Text::_('COM_M365CALENDAR_FILTER_CALENDAR'); ?></label>
                <select class="form-select" name="filter_calendar" id="<?php echo $prefix; ?>calendar">
                    <option value=""><?php echo Text::_('COM_M365CALENDAR_FILTER_ALL_CALENDARS'); ?></option>
                    <?php foreach ($calendars as $calendar) : ?>
                        <option value="<?php echo (int) $calendar->id; ?>"<?php echo (int) $calendar->id === $visitor->calendarId ? ' selected' : ''; ?>>
                            <?php echo $this->escape($calendar->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($show('filter_show_category') && $categories !== []) : ?>
            <div class="col-12 col-md-3">
                <label class="form-label" for="<?php echo $prefix; ?>category"><?php echo Text::_('COM_M365CALENDAR_FILTER_CATEGORY'); ?></label>
                <select class="form-select" name="filter_category" id="<?php echo $prefix; ?>category">
                    <option value=""><?php echo Text::_('COM_M365CALENDAR_FILTER_ALL_CATEGORIES'); ?></option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo $this->escape($category); ?>"<?php echo $category === $visitor->category ? ' selected' : ''; ?>>
                            <?php echo $this->escape($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($show('filter_show_location')) : ?>
            <div class="col-12 col-md-3">
                <label class="form-label" for="<?php echo $prefix; ?>location"><?php echo Text::_('COM_M365CALENDAR_FILTER_LOCATION'); ?></label>
                <input type="text" class="form-control" name="filter_location" id="<?php echo $prefix; ?>location" value="<?php echo $this->escape($visitor->location); ?>" maxlength="100">
            </div>
        <?php endif; ?>

        <?php if ($show('filter_show_type')) : ?>
            <div class="col-6 col-md-2">
                <label class="form-label" for="<?php echo $prefix; ?>type"><?php echo Text::_('COM_M365CALENDAR_FILTER_TYPE'); ?></label>
                <select class="form-select" name="filter_type" id="<?php echo $prefix; ?>type">
                    <?php foreach (['all' => 'COM_M365CALENDAR_TYPE_ALL', 'allday' => 'COM_M365CALENDAR_TYPE_ALLDAY_ONLY', 'timed' => 'COM_M365CALENDAR_TYPE_TIMED_ONLY'] as $value => $label) : ?>
                        <option value="<?php echo $value; ?>"<?php echo $visitor->eventType === $value ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($show('filter_show_time')) : ?>
            <div class="col-6 col-md-2">
                <label class="form-label" for="<?php echo $prefix; ?>time"><?php echo Text::_('COM_M365CALENDAR_FILTER_TIME'); ?></label>
                <select class="form-select" name="filter_time" id="<?php echo $prefix; ?>time">
                    <?php foreach (['all' => 'COM_M365CALENDAR_TIME_ALL', 'upcoming' => 'COM_M365CALENDAR_TIME_UPCOMING', 'past' => 'COM_M365CALENDAR_TIME_PAST'] as $value => $label) : ?>
                        <option value="<?php echo $value; ?>"<?php echo $visitor->timeRange === $value ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($show('filter_show_sort') && $displayData['mode'] === 'list') : ?>
            <div class="col-6 col-md-2">
                <label class="form-label" for="<?php echo $prefix; ?>sort"><?php echo Text::_('COM_M365CALENDAR_FILTER_SORT'); ?></label>
                <select class="form-select" name="list_sort" id="<?php echo $prefix; ?>sort">
                    <?php foreach (['start_asc' => 'COM_M365CALENDAR_SORT_DATE_ASC', 'start_desc' => 'COM_M365CALENDAR_SORT_DATE_DESC', 'title_asc' => 'COM_M365CALENDAR_SORT_TITLE'] as $value => $label) : ?>
                        <option value="<?php echo $value; ?>"<?php echo $sort === $value ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="col-12 col-md-auto d-flex gap-2">
            <?php if (!\Joomla\CMS\Factory::getApplication()->get('sef')) : ?>
                <?php // A GET form drops the action's query string, so pass it as fields. ?>
                <input type="hidden" name="option" value="com_m365calendar">
                <input type="hidden" name="view" value="calendar">
                <input type="hidden" name="Itemid" value="<?php echo $itemId; ?>">
            <?php endif; ?>
            <?php if ($displayData['mode'] === 'month') : ?>
                <input type="hidden" name="month" value="<?php echo $this->escape($displayData['month']); ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_M365CALENDAR_FILTER_APPLY'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo EventHelper::calendarUrl($itemId, ['filter_reset' => 1]); ?>">
                <?php echo Text::_('COM_M365CALENDAR_FILTER_RESET'); ?>
            </a>
        </div>
    </div>
</form>
