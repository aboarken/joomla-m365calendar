<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Rule;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Validates a Graph calendar ID (base64url-like characters only), or empty.
 *
 * @since  0.1.2
 */
class M365calendaridRule extends FormRule
{
    /**
     * Characters Graph uses in calendar IDs. Also enforced by the table.
     *
     * @var    string
     * @since  0.1.2
     */
    public const PATTERN = '/^[A-Za-z0-9=_\\-+\\/]{1,512}$/';

    /**
     * Method to test the value.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value.
     * @param   Registry           $input    An optional Registry object with the entire data set to validate against the entire form.
     * @param   Form               $form     The form object for which the field is being tested.
     *
     * @return  boolean  True if the value is valid, false otherwise.
     *
     * @since   0.1.2
     */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $value = trim((string) $value);

        return $value === '' || (bool) preg_match(self::PATTERN, $value);
    }
}
