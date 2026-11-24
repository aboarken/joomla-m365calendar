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
use Joomla\Component\M365calendar\Site\Helper\EventHelper;

/**
 * Server-rendered month grid (no calendar JS library).
 *
 * @var \Joomla\Component\M365calendar\Site\View\Calendar\HtmlView $this
 */

$grid      = $this->month;
$detail    = (int) $this->params->get('show_detail_page', 1) === 1;
$dayNames  = ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
$dayShort  = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
$monthName = HTMLHelper::_('date', $grid['first'], 'F Y', 'UTC');
$timeFmt   = Text::_('COM_M365CALENDAR_TIME_FORMAT');
?>
<div class="m365calendar-month">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo $this->escape(EventHelper::calendarUrl($this->itemId, ['mode' => 'month', 'month' => $grid['prev']])); ?>">
            <span aria-hidden="true">&lsaquo;</span>
            <span><?php echo Text::_('COM_M365CALENDAR_MONTH_PREVIOUS'); ?></span>
        </a>
        <h2 class="h4 mb-0" id="m365calendar-month-title-<?php echo $this->itemId; ?>"><?php echo $this->escape($monthName); ?></h2>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo $this->escape(EventHelper::calendarUrl($this->itemId, ['mode' => 'month', 'month' => $grid['next']])); ?>">
            <span><?php echo Text::_('COM_M365CALENDAR_MONTH_NEXT'); ?></span>
            <span aria-hidden="true">&rsaquo;</span>
        </a>
    </div>

    <?php if (\count($this->items) >= \Joomla\Component\M365calendar\Site\Model\CalendarModel::MONTH_LIMIT) : ?>
        <div class="alert alert-warning" role="status"><?php echo Text::_('COM_M365CALENDAR_MONTH_TRUNCATED'); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered m365calendar-month-grid" aria-labelledby="m365calendar-month-title-<?php echo $this->itemId; ?>">
            <thead>
                <tr>
                    <?php foreach ($grid['weekdays'] as $weekday) : ?>
                        <th scope="col" class="text-center">
                            <abbr title="<?php echo Text::_($dayNames[$weekday]); ?>"><?php echo Text::_($dayShort[$weekday]); ?></abbr>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grid['weeks'] as $week) : ?>
                    <tr>
                        <?php foreach ($week as $cell) : ?>
                            <td class="m365calendar-day<?php echo $cell['inMonth'] ? '' : ' m365calendar-day-outside'; ?><?php echo $cell['today'] ? ' m365calendar-day-today' : ''; ?>"
                                <?php echo $cell['today'] ? 'aria-current="date"' : ''; ?>>
                                <div class="m365calendar-day-number">
                                    <time datetime="<?php echo $cell['date']; ?>"><?php echo (int) $cell['day']; ?></time>
                                </div>
                                <?php if ($cell['events']) : ?>
                                    <ul class="list-unstyled m-0">
                                        <?php foreach ($cell['events'] as $event) :
                                            $label = (string) $event->subject !== '' ? (string) $event->subject : Text::_('COM_M365CALENDAR_EVENT_NO_SUBJECT');
                                            $time  = (int) $event->all_day === 1 ? '' : HTMLHelper::_('date', $event->start_utc, $timeFmt);
                                            ?>
                                            <li class="m365calendar-month-event m365calendar-cal-<?php echo (int) $event->calendar_id; ?>">
                                                <?php if ($detail) : ?>
                                                    <a href="<?php echo $this->escape(EventHelper::eventUrl((int) $event->id, $this->itemId)); ?>">
                                                        <?php if ($time !== '') :
                                                            ?><span class="m365calendar-time"><?php echo $this->escape($time); ?></span><?php
                                                        endif; ?>
                                                        <?php echo $this->escape($label); ?>
                                                    </a>
                                                <?php else : ?>
                                                    <?php if ($time !== '') :
                                                        ?><span class="m365calendar-time"><?php echo $this->escape($time); ?></span><?php
                                                    endif; ?>
                                                    <?php echo $this->escape($label); ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
