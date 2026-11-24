<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;

/**
 * "Add to my calendar" menu, built with a native <details> element (no JavaScript).
 *
 * @var \Joomla\CMS\Layout\FileLayout $this
 * @var array $displayData  ['links' => array{outlook: string, google: string, ics: string}, 'id' => int, 'small' => bool]
 */

$links = $displayData['links'];
$small = !empty($displayData['small']);
?>
<details class="m365calendar-add d-inline-block">
    <summary class="btn btn-outline-primary<?php echo $small ? ' btn-sm' : ''; ?>">
        <?php echo Text::_('COM_M365CALENDAR_ADD_TO_CALENDAR'); ?>
    </summary>
    <ul class="list-unstyled m365calendar-add-menu border rounded p-2 mt-1">
        <li>
            <a href="<?php echo $this->escape($links['outlook']); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo Text::_('COM_M365CALENDAR_ADD_OUTLOOK'); ?>
                <span class="visually-hidden"><?php echo Text::_('COM_M365CALENDAR_OPENS_NEW_WINDOW'); ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo $this->escape($links['google']); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo Text::_('COM_M365CALENDAR_ADD_GOOGLE'); ?>
                <span class="visually-hidden"><?php echo Text::_('COM_M365CALENDAR_OPENS_NEW_WINDOW'); ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo $this->escape($links['ics']); ?>" rel="nofollow" download>
                <?php echo Text::_('COM_M365CALENDAR_ADD_ICS'); ?>
            </a>
        </li>
    </ul>
</details>
