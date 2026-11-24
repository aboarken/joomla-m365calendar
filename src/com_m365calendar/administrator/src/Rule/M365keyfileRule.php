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
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use M365Graph\Certificate\CertificateLoader;
use M365Graph\Exception\ConfigurationException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Validates the certificate / private key path: the file must exist, be
 * readable, be outside JPATH_ROOT and contain a parseable certificate or
 * RSA private key. Empty is allowed (not configured yet).
 *
 * @since  0.1.0
 */
class M365keyfileRule extends FormRule
{
    /**
     * Method to test the value.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value.
     * @param   Registry           $input    An optional Registry object with the entire data set to validate against the entire form.
     * @param   Form               $form     The form object for which the field is being tested.
     *
     * @return  boolean  True if the value is valid.
     *
     * @throws  \UnexpectedValueException  With a translated message when invalid.
     *
     * @since   0.1.0
     */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return true;
        }

        $loader = new CertificateLoader(JPATH_ROOT);

        try {
            if ((string) $element['filetype'] === 'privatekey') {
                $loader->loadPrivateKey($value);
                $certificate = $input instanceof Registry ? trim((string) $input->get('certificate_path', '')) : '';

                if ($certificate !== '' && !$loader->keyMatchesCertificate($certificate, $value)) {
                    throw new ConfigurationException('Key does not match the certificate.', 'key_mismatch');
                }
            } else {
                $loader->loadCertificateDer($value);
            }
        } catch (ConfigurationException $e) {
            throw new \UnexpectedValueException(
                Text::sprintf(
                    'COM_M365CALENDAR_CONFIG_PATH_ERROR_' . strtoupper($e->getReason()),
                    Text::_((string) $element['label'])
                )
            );
        }

        return true;
    }
}
