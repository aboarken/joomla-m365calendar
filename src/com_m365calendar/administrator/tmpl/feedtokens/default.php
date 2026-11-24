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

/** @var \Joomla\Component\M365calendar\Administrator\View\Feedtokens\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('table.columns')
    ->useScript('multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_m365calendar&view=feedtokens'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <div class="alert alert-info"><?php echo Text::_('COM_M365CALENDAR_FEEDTOKENS_INTRO'); ?></div>

                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('COM_M365CALENDAR_FEEDTOKENS_EMPTY'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="feedtokenList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_M365CALENDAR_FEEDTOKENS_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                                <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_M365CALENDAR_HEADING_USER', 'u.name', $listDirn, $listOrder); ?></th>
                                <th scope="col" class="w-20"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_CREATED', 't.created', $listDirn, $listOrder); ?></th>
                                <th scope="col" class="w-20"><?php echo HTMLHelper::_('searchtools.sort', 'COM_M365CALENDAR_HEADING_LAST_USED', 't.last_used', $listDirn, $listOrder); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', (string) $item->username); ?></td>
                                <th scope="row">
                                    <?php if ($item->name === null) : ?>
                                        <?php echo Text::sprintf('COM_M365CALENDAR_FEEDTOKENS_DELETED_USER', (int) $item->user_id); ?>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->name); ?>
                                        <span class="small text-body-secondary">(<?php echo $this->escape($item->username); ?>)</span>
                                        <?php if ((int) $item->block === 1) : ?>
                                            <span class="badge bg-danger"><?php echo Text::_('COM_M365CALENDAR_FEEDTOKENS_BLOCKED'); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </th>
                                <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?></td>
                                <td><?php echo $item->last_used ? HTMLHelper::_('date', $item->last_used, Text::_('DATE_FORMAT_LC5')) : Text::_('COM_M365CALENDAR_NEVER'); ?></td>
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
