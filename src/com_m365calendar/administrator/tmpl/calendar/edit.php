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

/** @var \Joomla\Component\M365calendar\Administrator\View\Calendar\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('keepalive')
    ->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_m365calendar&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="calendar-form" aria-label="<?php echo Text::_('COM_M365CALENDAR_CALENDAR_' . ((int) $this->item->id === 0 ? 'NEW' : 'EDIT'), true); ?>" class="form-validate">

    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_M365CALENDAR_CALENDAR_FIELDSET_DETAILS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_M365CALENDAR_CALENDAR_FIELDSET_DETAILS'); ?></legend>
                    <?php echo $this->form->renderField('mailbox'); ?>
                    <?php echo $this->form->renderField('graph_calendar_id'); ?>
                    <?php echo $this->form->renderField('color'); ?>
                    <?php echo $this->form->renderField('allow_feeds'); ?>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); ?></legend>
                    <?php echo $this->form->renderField('published'); ?>
                    <?php echo $this->form->renderField('access'); ?>
                    <?php echo $this->form->renderField('created'); ?>
                    <?php echo $this->form->renderField('modified'); ?>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <?php echo $this->form->renderField('id'); ?>
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
