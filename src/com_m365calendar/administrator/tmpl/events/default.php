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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\M365calendar\Administrator\View\Events\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('table.columns');

$listOrder  = $this->escape($this->state->get('list.ordering'));
$listDirn   = $this->escape($this->state->get('list.direction'));
$layoutPath = JPATH_ADMINISTRATOR . '/components/com_m365calendar/layouts';
$lockActive = $this->lock && $this->lock->locked_until && $this->lock->locked_until > \Joomla\CMS\Factory::getDate()->toSql();
?>
<form action="<?php echo Route::_('index.php?option=com_m365calendar&view=events'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <div class="card mb-3">
                    <div class="card-header">
                        <h2 class="h5 mb-0"><?php echo Text::_('COM_M365CALENDAR_SYNC_STATUS_HEADING'); ?></h2>
                    </div>
                    <div class="card-body">
                        <?php if ($lockActive) : ?>
                            <div class="alert alert-info"><?php echo Text::_('COM_M365CALENDAR_SYNC_RUNNING'); ?></div>
                        <?php endif; ?>
                        <?php if (empty($this->syncStatus)) : ?>
                            <p class="mb-0"><?php echo Text::_('COM_M365CALENDAR_SYNC_NO_CALENDARS'); ?></p>
                        <?php else : ?>
                            <table class="table table-sm mb-0">
                                <caption class="visually-hidden"><?php echo Text::_('COM_M365CALENDAR_SYNC_STATUS_HEADING'); ?></caption>
                                <thead>
                                    <tr>
                                        <th scope="col"><?php echo Text::_('COM_M365CALENDAR_HEADING_CALENDAR'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_M365CALENDAR_HEADING_LAST_SYNC'); ?></th>
                                        <th scope="col" class="d-none d-md-table-cell"><?php echo Text::_('COM_M365CALENDAR_HEADING_LAST_SUCCESS'); ?></th>
                                        <th scope="col" class="d-none d-lg-table-cell"><?php echo Text::_('COM_M365CALENDAR_HEADING_MESSAGE'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($this->syncStatus as $row) : ?>
                                    <tr>
                                        <th scope="row">
                                            <?php echo $this->escape($row->title); ?>
                                            <?php if ((int) $row->published !== 1) : ?>
                                                <span class="badge bg-secondary"><?php echo Text::_('JUNPUBLISHED'); ?></span>
                                            <?php endif; ?>
                                            <div class="small text-body-secondary"><?php echo $this->escape($row->mailbox); ?></div>
                                        </th>
                                        <td>
                                            <?php echo LayoutHelper::render('m365calendar.syncstatus', ['status' => $row->status, 'last_run' => $row->last_run, 'count' => $row->event_count], $layoutPath); ?>
                                        </td>
                                        <td class="d-none d-md-table-cell">
                                            <?php echo $row->last_success ? HTMLHelper::_('date', $row->last_success, Text::_('DATE_FORMAT_LC5')) : '&ndash;'; ?>
                                        </td>
                                        <td class="d-none d-lg-table-cell small">
                                            <?php echo $row->message !== null && $row->message !== '' ? '<code>' . $this->escape($row->message) . '</code>' : '&ndash;'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('COM_M365CALENDAR_EVENTS_EMPTY'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="eventList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_M365CALENDAR_EVENTS_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col" class="w-20">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_M365CALENDAR_HEADING_START', 'e.start_utc', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_M365CALENDAR_HEADING_SUBJECT', 'e.subject', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo Text::_('COM_M365CALENDAR_HEADING_LOCATION'); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_M365CALENDAR_HEADING_CALENDAR', 'c.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-lg-table-cell">
                                    <?php echo Text::_('COM_M365CALENDAR_HEADING_CATEGORIES'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) :
                            $categories = json_decode((string) $item->categories, true) ?: [];
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td>
                                    <?php if ((int) $item->all_day === 1) : ?>
                                        <?php echo HTMLHelper::_('date', $item->start_utc, Text::_('DATE_FORMAT_LC4'), 'UTC'); ?>
                                        <span class="badge bg-info text-dark"><?php echo Text::_('COM_M365CALENDAR_TYPE_ALLDAY'); ?></span>
                                    <?php else : ?>
                                        <?php echo HTMLHelper::_('date', $item->start_utc, Text::_('DATE_FORMAT_LC5')); ?>
                                    <?php endif; ?>
                                </td>
                                <th scope="row"><?php echo $this->escape($item->subject); ?></th>
                                <td class="d-none d-md-table-cell"><?php echo $this->escape($item->location); ?></td>
                                <td class="d-none d-md-table-cell"><?php echo $this->escape($item->calendar_title); ?></td>
                                <td class="d-none d-lg-table-cell">
                                    <?php foreach ($categories as $category) : ?>
                                        <span class="badge bg-secondary"><?php echo $this->escape((string) $category); ?></span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
