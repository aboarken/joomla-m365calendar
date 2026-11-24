<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Site\View\Event;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\M365calendar\Site\Helper\AssetHelper;
use Joomla\Component\M365calendar\Site\Helper\EventHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Event detail page (opened from a Calendar page, uses its Itemid).
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The event.
     *
     * @var    object|null
     * @since  1.0.0
     */
    protected $item;

    /**
     * Merged params.
     *
     * @var    \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $params;

    /**
     * Menu item ID.
     *
     * @var    integer
     * @since  1.0.0
     */
    protected $itemId = 0;

    /**
     * Page class suffix.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $pageclass_sfx = '';

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @throws  \Exception  404 when the event is not available here.
     *
     * @since   1.0.0
     */
    public function display($tpl = null): void
    {
        /** @var \Joomla\Component\M365calendar\Site\Model\EventModel $model */
        $model = $this->getModel();

        $this->params        = $model->getState('params');
        $this->itemId        = (int) $model->getState('menu.id');
        $this->item          = $model->getItem();
        $this->pageclass_sfx = htmlspecialchars((string) $this->params->get('pageclass_sfx', ''), ENT_QUOTES, 'UTF-8');

        if ($this->item === null || (int) $this->params->get('show_detail_page', 1) !== 1) {
            throw new \Exception(Text::_('COM_M365CALENDAR_EVENT_NOT_FOUND'), 404);
        }

        AssetHelper::load($this->getDocument(), $this->params, [(object) ['id' => $this->item->calendar_id, 'color' => $this->item->calendar_color]]);

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * Title, breadcrumb, description, canonical and JSON-LD.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function prepareDocument(): void
    {
        $app      = Factory::getApplication();
        $document = $this->getDocument();
        $subject  = (string) $this->item->subject !== '' ? (string) $this->item->subject : Text::_('COM_M365CALENDAR_EVENT_NO_SUBJECT');

        $this->setDocumentTitle($subject);
        $app->getPathway()->addItem($subject, '');

        $range       = EventHelper::dateRange($this->item, (string) $this->params->get('date_format', 'DATE_FORMAT_LC1'));
        $description = $range['start'] . ($range['end'] !== '' ? ' – ' . $range['end'] : '');

        if ((string) $this->item->location !== '') {
            $description .= ', ' . $this->item->location;
        }

        // setDescription() output is escaped by the document renderer.
        $document->setDescription(mb_substr($description, 0, 160));

        if ($robots = (string) $this->params->get('robots', '')) {
            $document->setMetaData('robots', $robots);
        }

        $document->addHeadLink(htmlspecialchars(EventHelper::eventUrl((int) $this->item->id, $this->itemId, true), ENT_COMPAT, 'UTF-8'), 'canonical');

        if ((int) $this->params->get('show_jsonld', 1) === 1) {
            $document->getWebAssetManager()->addInlineScript(
                EventHelper::jsonLd([$this->item], $this->itemId, true),
                ['name' => 'm365calendar.jsonld'],
                ['type' => 'application/ld+json']
            );
        }
    }
}
