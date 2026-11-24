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
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List of the Outlook categories found in synced events.
 *
 * @since  1.0.0
 */
class M365categoriesField extends ListField implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * The form field type.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $type = 'M365categories';

    /**
     * Method to get the field options.
     *
     * @return  object[]
     *
     * @since   1.0.0
     */
    protected function getOptions()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('category'))
            ->from($db->quoteName('#__m365calendar_event_categories'))
            ->order($db->quoteName('category') . ' ASC');

        $options = parent::getOptions();
        $values  = $db->setQuery($query, 0, 500)->loadColumn();

        // Keep values that are already saved in the menu item even if no event uses them now.
        foreach ((array) $this->value as $saved) {
            if (\is_string($saved) && $saved !== '' && !\in_array($saved, $values, true)) {
                $values[] = $saved;
            }
        }

        foreach ($values as $value) {
            $options[] = HTMLHelper::_('select.option', $value, $value);
        }

        return $options;
    }
}
