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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Text field for connection settings that is only editable with core.admin.
 *
 * Users without core.admin on com_m365calendar see a notice instead of the
 * input; the M365connection form filter then keeps the stored value, so the
 * setting cannot be changed by posting it either.
 *
 * @since  1.0.0
 */
class M365connectionField extends TextField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $type = 'M365connection';

    /**
     * Method to get the field input markup.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    protected function getInput()
    {
        if (!self::canEdit()) {
            return '<p class="form-control-plaintext text-body-secondary">'
                . htmlspecialchars(Text::_('COM_M365CALENDAR_CONFIG_CONNECTION_RESTRICTED'), ENT_QUOTES, 'UTF-8')
                . '</p>';
        }

        return parent::getInput();
    }

    /**
     * Whether the current user may edit connection settings.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public static function canEdit(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user !== null && $user->authorise('core.admin', 'com_m365calendar');
    }
}
