<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\View\Events;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Read-only events view with sync status and the sync/test buttons.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Items.
     *
     * @var    object[]
     * @since  0.1.0
     */
    protected $items = [];

    /**
     * Pagination.
     *
     * @var    \Joomla\CMS\Pagination\Pagination
     * @since  0.1.0
     */
    protected $pagination;

    /**
     * Model state.
     *
     * @var    \Joomla\Registry\Registry
     * @since  0.1.0
     */
    protected $state;

    /**
     * Filter form.
     *
     * @var    \Joomla\CMS\Form\Form|null
     * @since  0.1.0
     */
    public $filterForm;

    /**
     * Active filters.
     *
     * @var    array<string, mixed>
     * @since  0.1.0
     */
    public $activeFilters = [];

    /**
     * Sync status per calendar.
     *
     * @var    object[]
     * @since  0.1.0
     */
    protected $syncStatus = [];

    /**
     * Lock row.
     *
     * @var    object|null
     * @since  0.1.0
     */
    protected $lock;

    /**
     * Whether the user may sync.
     *
     * @var    boolean
     * @since  0.1.0
     */
    protected $canSync = false;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function display($tpl = null): void
    {
        /** @var \Joomla\Component\M365calendar\Administrator\Model\EventsModel $model */
        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();
        $this->syncStatus    = $model->getSyncStatus();
        $this->lock          = $model->getLock();
        $this->canSync       = $this->getCurrentUser()->authorise('m365calendar.sync', 'com_m365calendar');

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
     * @since   0.1.0
     */
    protected function addToolbar(): void
    {
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_M365CALENDAR_EVENTS_TITLE'), 'calendar');

        if ($this->canSync) {
            $toolbar->standardButton('m365calendar-sync', 'COM_M365CALENDAR_TOOLBAR_SYNC_NOW', 'events.sync')
                ->icon('icon-refresh')
                ->listCheck(false);
            $toolbar->standardButton('m365calendar-test', 'COM_M365CALENDAR_TOOLBAR_TEST_CONNECTION', 'events.test')
                ->icon('icon-plug')
                ->listCheck(false);
        }

        if ($user->authorise('core.admin', 'com_m365calendar') || $user->authorise('core.options', 'com_m365calendar')) {
            $toolbar->preferences('com_m365calendar');
        }
    }
}
