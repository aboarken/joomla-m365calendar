<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\View\Feedtokens;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Users with personal feed links.
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
        /** @var \Joomla\Component\M365calendar\Administrator\Model\FeedtokensModel $model */
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

        ToolbarHelper::title(Text::_('COM_M365CALENDAR_FEEDTOKENS_TITLE'), 'calendar');

        if ($user->authorise('core.delete', 'com_m365calendar')) {
            $toolbar->delete('feedtokens.revoke', 'COM_M365CALENDAR_TOOLBAR_REVOKE')
                ->message('COM_M365CALENDAR_FEEDTOKENS_CONFIRM_REVOKE')
                ->listCheck(true);
        }

        if ($user->authorise('core.admin', 'com_m365calendar') || $user->authorise('core.options', 'com_m365calendar')) {
            $toolbar->preferences('com_m365calendar');
        }
    }
}
