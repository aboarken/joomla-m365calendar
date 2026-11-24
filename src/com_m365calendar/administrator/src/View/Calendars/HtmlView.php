<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\View\Calendars;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Calendars list view.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Items.
     *
     * @var    object[]
     * @since  1.0.0
     */
    protected $items = [];

    /**
     * Pagination.
     *
     * @var    \Joomla\CMS\Pagination\Pagination
     * @since  1.0.0
     */
    protected $pagination;

    /**
     * Model state.
     *
     * @var    \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $state;

    /**
     * Filter form.
     *
     * @var    \Joomla\CMS\Form\Form|null
     * @since  1.0.0
     */
    public $filterForm;

    /**
     * Active filters.
     *
     * @var    array<string, mixed>
     * @since  1.0.0
     */
    public $activeFilters = [];

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null): void
    {
        /** @var \Joomla\Component\M365calendar\Administrator\Model\CalendarsModel $model */
        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the toolbar.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addToolbar(): void
    {
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_M365CALENDAR_CALENDARS_TITLE'), 'calendar');

        if ($user->authorise('core.create', 'com_m365calendar')) {
            $toolbar->addNew('calendar.add');
        }

        if ($user->authorise('core.edit.state', 'com_m365calendar')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();
            $childBar->publish('calendars.publish')->listCheck(true);
            $childBar->unpublish('calendars.unpublish')->listCheck(true);
            $childBar->checkin('calendars.checkin')->listCheck(true);

            if ($user->authorise('core.create', 'com_m365calendar') && $user->authorise('core.edit', 'com_m365calendar')) {
                $childBar->popupButton('batch', 'JTOOLBAR_BATCH')
                    ->popupType('inline')
                    ->textHeader(Text::_('COM_M365CALENDAR_BATCH_OPTIONS'))
                    ->url('#joomla-dialog-batch')
                    ->modalWidth('800px')
                    ->modalHeight('fit-content')
                    ->listCheck(true);
            }
        }

        if ($user->authorise('core.delete', 'com_m365calendar')) {
            $toolbar->delete('calendars.delete')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($user->authorise('core.admin', 'com_m365calendar') || $user->authorise('core.options', 'com_m365calendar')) {
            $toolbar->preferences('com_m365calendar');
        }
    }
}
