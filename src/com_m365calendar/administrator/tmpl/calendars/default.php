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

/** @var \Joomla\Component\M365calendar\Administrator\View\Calendars\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('table.columns')
    ->useScript('multiselect');

$user      = $this->getCurrentUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$canChange = $user->authorise('core.edit.state', 'com_m365calendar');
$canEdit   = $user->authorise('core.edit', 'com_m365calendar');

// Calendar colours as a nonce-able inline stylesheet (no style attributes).
$css = '';

foreach ($this->items as $item) {
    if (preg_match('/^#[0-9a-f]{6}$/i', (string) $item->color)) {
        $css .= '.m365calendar-swatch-' . (int) $item->id . '{background-color:' . $item->color . ';}';
    }
}

if ($css !== '') {
    $this->getDocument()->getWebAssetManager()->addInlineStyle(
        '.m365calendar-swatch{display:inline-block;width:.75rem;height:.75rem;border-radius:50%;vertical-align:middle;margin-inline-end:.25rem;}' . $css,
        ['name' => 'm365calendar.admin.swatches']
    );
}
?>
<form action="<?php echo Route::_('index.php?option=com_m365calendar&view=calendars'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('COM_M365CALENDAR_CALENDARS_EMPTY'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="calendarList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_M365CALENDAR_CALENDARS_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_M365CALENDAR_CALENDAR_MAILBOX_LABEL', 'a.mailbox', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell text-center">
                                    <?php echo Text::_('COM_M365CALENDAR_CALENDAR_ALLOW_FEEDS_LABEL'); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'access_level', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15 d-none d-lg-table-cell">
                                    <?php echo Text::_('COM_M365CALENDAR_HEADING_LAST_SYNC'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) :
                            $canCheckin = $user->authorise('core.manage', 'com_checkin') || (int) $item->checked_out === (int) $user->id || (int) $item->checked_out === 0;
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?>
                                </td>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'calendars.', $canChange, 'cb'); ?>
                                </td>
                                <th scope="row">
                                    <span class="m365calendar-swatch m365calendar-swatch-<?php echo (int) $item->id; ?>" aria-hidden="true"></span>
                                    <?php if ($item->checked_out) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'calendars.', $canCheckin); ?>
                                    <?php endif; ?>
                                    <?php if ($canEdit) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_m365calendar&task=calendar.edit&id=' . (int) $item->id); ?>">
                                            <?php echo $this->escape($item->title); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->title); ?>
                                    <?php endif; ?>
                                    <div class="small text-body-secondary">
                                        <?php echo $item->graph_calendar_id === ''
                                            ? Text::_('COM_M365CALENDAR_CALENDAR_DEFAULT_CALENDAR')
                                            : Text::_('COM_M365CALENDAR_CALENDAR_SPECIFIC_CALENDAR'); ?>
                                    </div>
                                </th>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $this->escape($item->mailbox); ?>
                                </td>
                                <td class="d-none d-md-table-cell text-center">
                                    <?php if ((int) $item->allow_feeds === 1) : ?>
                                        <span class="badge bg-warning text-dark"><?php echo Text::_('JYES'); ?></span>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?php echo Text::_('JNO'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo $this->escape($item->access_level); ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <?php echo LayoutHelper::render('m365calendar.syncstatus', ['status' => $item->sync_status, 'last_run' => $item->last_run, 'count' => $item->event_count], JPATH_ADMINISTRATOR . '/components/com_m365calendar/layouts'); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>

                    <?php if ($user->authorise('core.create', 'com_m365calendar') && $user->authorise('core.edit', 'com_m365calendar') && $canChange) : ?>
                        <template id="joomla-dialog-batch"><?php echo $this->loadTemplate('batch_body'); ?></template>
                    <?php endif; ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
