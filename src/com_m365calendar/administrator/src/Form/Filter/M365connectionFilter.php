<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Form\Filter;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFilterInterface;
use Joomla\Component\M365calendar\Administrator\Field\M365connectionField;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Form filter for connection settings.
 *
 * Trims the value and, for users without core.admin, replaces any submitted
 * value with the stored one, so only core.admin can change the "Connection" tab.
 *
 * @since  1.0.0
 */
class M365connectionFilter implements FormFilterInterface
{
    /**
     * Method to filter a field value.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value.
     * @param   Registry           $input    An optional Registry object with the entire data set to validate against the entire form.
     * @param   Form               $form     The form object for which the field is being tested.
     *
     * @return  mixed   The filtered value.
     *
     * @since   1.0.0
     */
    public function filter(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        if (!M365connectionField::canEdit()) {
            return (string) ComponentHelper::getParams('com_m365calendar')->get((string) $element['name'], '');
        }

        $value = \is_scalar($value) ? (string) $value : '';

        // Remove control characters and surrounding whitespace.
        return trim(preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '');
    }
}
