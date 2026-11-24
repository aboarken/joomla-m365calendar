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

use Joomla\CMS\MVC\Model\AdminModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Feed token model; used to revoke (delete) tokens.
 *
 * @since  1.0.0
 */
class FeedtokenModel extends AdminModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_M365CALENDAR_FEEDTOKENS';

    /**
     * Tokens are never edited, so there is no form.
     *
     * @param   array<string, mixed>  $data  Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        return false;
    }

    /**
     * Revoking needs core.delete on the component.
     *
     * @param   object  $record  A record object.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    protected function canDelete($record)
    {
        return $this->getCurrentUser()->authorise('core.delete', 'com_m365calendar');
    }
}
