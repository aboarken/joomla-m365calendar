<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\View\Calendar;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Calendar edit view.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The form.
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  1.0.0
     */
    protected $form;

    /**
     * The item.
     *
     * @var    object
     * @since  1.0.0
     */
    protected $item;

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
        /** @var \Joomla\Component\M365calendar\Administrator\Model\CalendarModel $model */
        $model = $this->getModel();

        $this->form = $model->getForm();
        $this->item = $model->getItem();

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
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $user    = $this->getCurrentUser();
        $isNew   = empty($this->item->id);
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_($isNew ? 'COM_M365CALENDAR_CALENDAR_NEW' : 'COM_M365CALENDAR_CALENDAR_EDIT'), 'calendar');

        $canSave = $isNew ? $user->authorise('core.create', 'com_m365calendar') : $user->authorise('core.edit', 'com_m365calendar');

        if ($canSave) {
            $toolbar->apply('calendar.apply');
            $toolbar->save('calendar.save');

            if ($user->authorise('core.create', 'com_m365calendar')) {
                $toolbar->save2new('calendar.save2new');
            }
        }

        $toolbar->cancel('calendar.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
