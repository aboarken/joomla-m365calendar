<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List of configured calendars (used for the menu item preset and admin filters).
 *
 * @since  0.1.0
 */
class M365calendarsField extends ListField implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * The form field type.
     *
     * @var    string
     * @since  0.1.0
     */
    protected $type = 'M365calendars';

    /**
     * Method to get the field options.
     *
     * @return  object[]
     *
     * @since   0.1.0
     */
    protected function getOptions()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'published']))
            ->from($db->quoteName('#__m365calendar_calendars'))
            ->where($db->quoteName('published') . ' IN (0, 1)')
            ->order($db->quoteName('title') . ' ASC');

        $options = parent::getOptions();

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $text      = (int) $row->published === 1 ? $row->title : $row->title . ' [' . Text::_('JUNPUBLISHED') . ']';
            $options[] = HTMLHelper::_('select.option', (string) $row->id, $text);
        }

        return $options;
    }
}
