<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Site\Helper;

use Joomla\CMS\Document\Document;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Loads this component's assets through the Web Asset Manager, on this
 * component's pages only.
 *
 * Bootstrap and jQuery are never bundled: by default the template provides
 * them; optionally Joomla core's registered assets are used.
 *
 * @since  1.0.0
 */
final class AssetHelper
{
    /**
     * Load the page assets.
     *
     * @param   Document  $document   The HTML document.
     * @param   Registry  $params     Merged component/menu params.
     * @param   object[]  $calendars  Calendars shown (for colours).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function load(Document $document, Registry $params, array $calendars = []): void
    {
        if (!method_exists($document, 'getWebAssetManager')) {
            return;
        }

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_m365calendar');

        if ($params->get('bootstrap', 'template') === 'core') {
            // CSS only: the markup needs no Bootstrap JavaScript (native <details> is used).
            $wa->useStyle('bootstrap.css');
        }

        if ($params->get('jquery', 'template') === 'core') {
            // Only for template overrides that need jQuery; the extension's own JS does not.
            $wa->useScript('jquery-noconflict');
        }

        $wa->useStyle('m365calendar.site');

        $css = '';

        foreach ($calendars as $calendar) {
            $color = strtolower((string) ($calendar->color ?? ''));

            if (preg_match('/^#[0-9a-f]{6}$/', $color)) {
                $css .= '.m365calendar .m365calendar-cal-' . (int) $calendar->id . '{--m365calendar-color:' . $color . ';}';
            }
        }

        if ($css !== '') {
            $wa->addInlineStyle($css, ['name' => 'm365calendar.colors'], [], ['m365calendar.site']);
        }
    }

    /**
     * Load the subscribe / copy-link script.
     *
     * @param   Document  $document  The HTML document.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function loadSubscribeScript(Document $document): void
    {
        if (!method_exists($document, 'getWebAssetManager')) {
            return;
        }

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_m365calendar');
        $wa->useScript('m365calendar.subscribe');
    }
}
