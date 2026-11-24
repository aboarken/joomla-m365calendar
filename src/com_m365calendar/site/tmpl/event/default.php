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
 * Event detail page. Override in templates/YOUR_TEMPLATE/html/com_m365calendar/event/default.php
 *
 * @var \Joomla\Component\M365calendar\Site\View\Event\HtmlView $this
 */

$item       = $this->item;
$range      = EventHelper::dateRange($item, (string) $this->params->get('date_format', 'DATE_FORMAT_LC1'));
$categories = EventHelper::categories($item);
$subject    = (string) $item->subject !== '' ? (string) $item->subject : Text::_('COM_M365CALENDAR_EVENT_NO_SUBJECT');
?>
<div class="m365calendar m365calendar-event<?php echo $this->pageclass_sfx; ?>">
    <article class="card m365calendar-cal-<?php echo (int) $item->calendar_id; ?>">
        <div class="card-body">
            <h1 class="card-title h2"><?php echo $this->escape($subject); ?></h1>

            <dl class="row mb-0">
                <dt class="col-sm-3"><?php echo Text::_('COM_M365CALENDAR_EVENT_WHEN'); ?></dt>
                <dd class="col-sm-9">
                    <time datetime="<?php echo $this->escape($range['startIso']); ?>"><?php echo $this->escape($range['start']); ?></time>
                    <?php if ($range['end'] !== '') : ?>
                        &ndash; <time datetime="<?php echo $this->escape($range['endIso']); ?>"><?php echo $this->escape($range['end']); ?></time>
                    <?php endif; ?>
                    <?php if ($range['allDay']) : ?>
                        <span class="badge bg-info text-dark"><?php echo Text::_('COM_M365CALENDAR_TYPE_ALLDAY'); ?></span>
                    <?php endif; ?>
                </dd>

                <?php if ((string) $item->location !== '') : ?>
                    <dt class="col-sm-3"><?php echo Text::_('COM_M365CALENDAR_EVENT_WHERE'); ?></dt>
                    <dd class="col-sm-9"><?php echo $this->escape($item->location); ?></dd>
                <?php endif; ?>

                <?php if ((int) $this->params->get('show_calendar_name', 1) === 1) : ?>
                    <dt class="col-sm-3"><?php echo Text::_('COM_M365CALENDAR_EVENT_CALENDAR'); ?></dt>
                    <dd class="col-sm-9">
                        <span class="m365calendar-dot" aria-hidden="true"></span>
                        <?php echo $this->escape($item->calendar_title); ?>
                    </dd>
                <?php endif; ?>

                <?php if ($categories !== []) : ?>
                    <dt class="col-sm-3"><?php echo Text::_('COM_M365CALENDAR_EVENT_CATEGORIES'); ?></dt>
                    <dd class="col-sm-9">
                        <?php foreach ($categories as $category) : ?>
                            <span class="badge bg-secondary"><?php echo $this->escape($category); ?></span>
                        <?php endforeach; ?>
                    </dd>
                <?php endif; ?>
            </dl>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php if ((int) $this->params->get('show_add_to_calendar', 1) === 1) : ?>
                    <?php echo LayoutHelper::render(
                        'm365calendar.add_to_calendar',
                        ['links' => EventHelper::addToCalendarLinks($item, $this->itemId, true), 'id' => (int) $item->id],
                        null,
                        ['component' => 'com_m365calendar']
                    ); ?>
                <?php endif; ?>

                <?php if ((int) $this->params->get('show_weblink', 0) === 1 && (string) $item->web_link !== '') : ?>
                    <a class="btn btn-outline-secondary" href="<?php echo $this->escape($item->web_link); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo Text::_('COM_M365CALENDAR_EVENT_OPEN_IN_OUTLOOK'); ?>
                        <span class="visually-hidden"><?php echo Text::_('COM_M365CALENDAR_OPENS_NEW_WINDOW'); ?></span>
                    </a>
                <?php endif; ?>

                <a class="btn btn-link" href="<?php echo EventHelper::calendarUrl($this->itemId); ?>">
                    <?php echo Text::_('COM_M365CALENDAR_EVENT_BACK'); ?>
                </a>
            </div>
        </div>
    </article>
</div>
