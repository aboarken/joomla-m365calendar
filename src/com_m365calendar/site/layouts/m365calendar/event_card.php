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
 * Event card. Override in templates/YOUR_TEMPLATE/html/layouts/com_m365calendar/m365calendar/event_card.php
 *
 * @var \Joomla\CMS\Layout\FileLayout $this
 * @var array $displayData  ['item' => object, 'params' => Registry, 'itemId' => int]
 */

$item       = $displayData['item'];
$params     = $displayData['params'];
$itemId     = (int) $displayData['itemId'];
$detail     = (int) $params->get('show_detail_page', 1) === 1;
$range      = EventHelper::dateRange($item, (string) $params->get('date_format', 'DATE_FORMAT_LC1'));
$categories = (int) $params->get('show_categories', 1) === 1 ? EventHelper::categories($item) : [];
$subject    = (string) $item->subject !== '' ? (string) $item->subject : Text::_('COM_M365CALENDAR_EVENT_NO_SUBJECT');
$headingId  = 'm365calendar-event-' . $itemId . '-' . (int) $item->id;
?>
<article class="card m365calendar-card m365calendar-cal-<?php echo (int) $item->calendar_id; ?>" aria-labelledby="<?php echo $headingId; ?>">
    <div class="card-body">
        <h2 class="card-title h5" id="<?php echo $headingId; ?>">
            <?php if ($detail) : ?>
                <a href="<?php echo EventHelper::eventUrl((int) $item->id, $itemId); ?>"><?php echo $this->escape($subject); ?></a>
            <?php else : ?>
                <?php echo $this->escape($subject); ?>
            <?php endif; ?>
        </h2>
        <p class="card-subtitle mb-2 text-body-secondary">
            <time datetime="<?php echo $this->escape($range['startIso']); ?>"><?php echo $this->escape($range['start']); ?></time>
            <?php if ($range['end'] !== '') : ?>
                &ndash; <time datetime="<?php echo $this->escape($range['endIso']); ?>"><?php echo $this->escape($range['end']); ?></time>
            <?php endif; ?>
            <?php if ($range['allDay']) : ?>
                <span class="badge bg-info text-dark"><?php echo Text::_('COM_M365CALENDAR_TYPE_ALLDAY'); ?></span>
            <?php endif; ?>
        </p>
        <?php if ((int) $params->get('show_location', 1) === 1 && (string) $item->location !== '') : ?>
            <p class="card-text mb-1">
                <span class="visually-hidden"><?php echo Text::_('COM_M365CALENDAR_EVENT_WHERE'); ?>:</span>
                <?php echo $this->escape($item->location); ?>
            </p>
        <?php endif; ?>
        <?php if ((int) $params->get('show_calendar_name', 1) === 1) : ?>
            <p class="card-text small mb-1">
                <span class="m365calendar-dot" aria-hidden="true"></span>
                <span class="visually-hidden"><?php echo Text::_('COM_M365CALENDAR_EVENT_CALENDAR'); ?>:</span>
                <?php echo $this->escape($item->calendar_title); ?>
            </p>
        <?php endif; ?>
        <?php if ($categories !== []) : ?>
            <p class="card-text mb-2">
                <span class="visually-hidden"><?php echo Text::_('COM_M365CALENDAR_EVENT_CATEGORIES'); ?>:</span>
                <?php foreach ($categories as $category) : ?>
                    <span class="badge bg-secondary"><?php echo $this->escape($category); ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <?php if ((int) $params->get('show_add_to_calendar', 1) === 1) : ?>
            <?php echo LayoutHelper::render(
                'm365calendar.add_to_calendar',
                ['links' => EventHelper::addToCalendarLinks($item, $itemId, $detail), 'id' => (int) $item->id, 'small' => true],
                null,
                ['component' => 'com_m365calendar']
            ); ?>
        <?php endif; ?>
    </div>
</article>
