<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Component\M365calendar\Administrator\Helper\CacheHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Calendar edit model.
 *
 * Saving, deleting and publishing trigger the core content events, so the
 * core "Action Log - Joomla" plugin records them (see the install SQL).
 *
 * @since  0.1.0
 */
class CalendarModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  0.1.0
     */
    public $typeAlias = 'com_m365calendar.calendar';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  0.1.0
     */
    protected $text_prefix = 'COM_M365CALENDAR_CALENDAR';

    /**
     * Batch commands: only the access level applies to calendars.
     *
     * @var    array<string, string>
     * @since  0.1.0
     */
    protected $batch_commands = ['assetgroup_id' => 'batchAccess'];

    /**
     * Method to get the record form.
     *
     * @param   array<string, mixed>  $data  Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since   0.1.0
     */
    public function getForm($data = [], $loadData = true)
    {
        // loadForm() returns a Form or throws.
        $form = $this->loadForm('com_m365calendar.calendar', 'calendar', ['control' => 'jform', 'load_data' => $loadData]);

        $user = $this->getCurrentUser();

        if (!$user->authorise('core.edit.state', 'com_m365calendar')) {
            $form->setFieldAttribute('published', 'disabled', 'true');
            $form->setFieldAttribute('published', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   0.1.0
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_m365calendar.edit.calendar.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_m365calendar.calendar', $data);

        return $data;
    }

    /**
     * Prepare the table before saving.
     *
     * @param   Table  $table  The table.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    protected function prepareTable($table)
    {
        if (empty($table->id) && empty($table->ordering)) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('MAX(' . $db->quoteName('ordering') . ')')
                ->from($db->quoteName('#__m365calendar_calendars'));

            $table->ordering = (int) $db->setQuery($query)->loadResult() + 1;
        }
    }

    /**
     * Clean caches after save, publish or delete, including the site's
     * cached feeds, so "Allow in feeds", access and publishing changes apply at once.
     *
     * @param   string  $group  Cache group.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    protected function cleanCache($group = null)
    {
        parent::cleanCache($group);

        CacheHelper::cleanSiteCaches();
    }
}
