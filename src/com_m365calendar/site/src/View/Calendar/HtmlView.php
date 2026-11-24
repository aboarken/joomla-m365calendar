<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Site\View\Calendar;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Component\M365calendar\Administrator\Extension\M365calendarComponent;
use Joomla\Component\M365calendar\Site\Controller\FeedController;
use Joomla\Component\M365calendar\Site\Helper\AssetHelper;
use Joomla\Component\M365calendar\Site\Helper\EventHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The "Calendar" page (list or month).
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Events.
     *
     * @var    object[]
     * @since  1.0.0
     */
    protected $items = [];

    /**
     * Pagination.
     *
     * @var    \Joomla\CMS\Pagination\Pagination|null
     * @since  1.0.0
     */
    protected $pagination;

    /**
     * Model state.
     *
     * @var    \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $state;

    /**
     * Merged params.
     *
     * @var    \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $params;

    /**
     * "list" or "month".
     *
     * @var    string
     * @since  1.0.0
     */
    protected $mode = 'list';

    /**
     * Month grid in month mode.
     *
     * @var    array|null
     * @since  1.0.0
     */
    protected $month;

    /**
     * Calendar options for the visitor filter.
     *
     * @var    object[]
     * @since  1.0.0
     */
    protected $calendarOptions = [];

    /**
     * Category options for the visitor filter.
     *
     * @var    string[]
     * @since  1.0.0
     */
    protected $categoryOptions = [];

    /**
     * Current visitor filter.
     *
     * @var    \Joomla\Component\M365calendar\Administrator\Filter\VisitorFilter
     * @since  1.0.0
     */
    protected $visitor;

    /**
     * Menu item ID.
     *
     * @var    integer
     * @since  1.0.0
     */
    protected $itemId = 0;

    /**
     * Data for the subscribe layout (null when hidden).
     *
     * @var    array|null
     * @since  1.0.0
     */
    protected $subscription;

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
     * @since   1.0.0
     */
    public function display($tpl = null): void
    {
        /** @var \Joomla\Component\M365calendar\Site\Model\CalendarModel $model */
        $model = $this->getModel();

        $this->state           = $model->getState();
        $this->params          = $this->state->get('params');
        $this->itemId          = (int) $this->state->get('menu.id');
        $this->mode            = (string) $this->state->get('display.mode');
        $this->visitor         = $this->state->get('filter.visitor');
        $this->items           = $model->getItems();
        $this->pagination      = $this->mode === 'list' ? $model->getPagination() : null;
        $this->month           = $this->mode === 'month' ? $model->getMonthGrid($this->items) : null;
        $this->calendarOptions = $model->getVisitorCalendarOptions();
        $this->categoryOptions = $model->getCategoryOptions();
        $this->pageclass_sfx   = htmlspecialchars((string) $this->params->get('pageclass_sfx', ''), ENT_QUOTES, 'UTF-8');

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->subscription = $this->buildSubscription();

        AssetHelper::load($this->getDocument(), $this->params, $model->getAccessibleCalendars());

        if ($this->subscription !== null) {
            AssetHelper::loadSubscribeScript($this->getDocument());
            $this->getDocument()->addScriptOptions('m365calendar.subscribe', ['copiedText' => Text::_('COM_M365CALENDAR_SUBSCRIBE_COPIED')]);
            Text::script('COM_M365CALENDAR_SUBSCRIBE_COPIED');
            Text::script('COM_M365CALENDAR_SUBSCRIBE_COPY_FAILED');
        }

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * Data for the "Subscribe" button.
     *
     * @return  array|null
     *
     * @since   1.0.0
     */
    protected function buildSubscription(): ?array
    {
        $mode = (string) $this->params->get('feed_mode', 'none');

        if (!\in_array($mode, ['public', 'personal'], true) || !$this->itemId) {
            return null;
        }

        $feedBase = Route::_('index.php?option=com_m365calendar&task=feed.serve&format=ics&Itemid=' . $this->itemId, false, Route::TLS_IGNORE, true);

        if ($mode === 'public') {
            return [
                'mode'   => 'public',
                'url'    => $feedBase,
                'webcal' => self::webcal($feedBase),
            ];
        }

        $user = $this->getCurrentUser();

        // Guests do not see the button on a "Logged-in users only" page.
        if ($user->guest) {
            return null;
        }

        /** @var M365calendarComponent $component */
        $component = Factory::getApplication()->bootComponent('com_m365calendar');
        $session   = Factory::getApplication()->getSession();
        $new       = $session->get(FeedController::SESSION_KEY);
        $url       = '';

        // A new token is shown exactly once, then forgotten.
        if (\is_array($new) && (int) ($new['user'] ?? 0) === (int) $user->id && \is_string($new['token'] ?? null)) {
            $url = $feedBase . (str_contains($feedBase, '?') ? '&' : '?') . 'token=' . rawurlencode($new['token']);
        }

        $session->remove(FeedController::SESSION_KEY);

        return [
            'mode'     => 'personal',
            'url'      => $url,
            'webcal'   => $url !== '' ? self::webcal($url) : '',
            'hasToken' => $component->getFeedTokenService()->hasToken((int) $user->id),
            'action'   => Route::_('index.php?option=com_m365calendar&task=feed.token&Itemid=' . $this->itemId),
        ];
    }

    /**
     * webcal:// variant of a feed URL.
     *
     * @param   string  $url  http(s) URL.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private static function webcal(string $url): string
    {
        return (string) preg_replace('#^https?://#i', 'webcal://', $url);
    }

    /**
     * Title, meta description, robots, canonical and JSON-LD.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function prepareDocument(): void
    {
        $app      = Factory::getApplication();
        $document = $this->getDocument();
        $menu     = $app->getMenu()->getActive();

        $this->params->def('page_heading', $this->params->get('page_title', $menu ? $menu->title : Text::_('COM_M365CALENDAR_CALENDAR_DEFAULT_TITLE')));

        $this->setDocumentTitle((string) $this->params->get('page_title', $menu ? $menu->title : ''));

        if ($description = (string) $this->params->get('menu-meta_description', '')) {
            $document->setDescription($description);
        }

        if ($robots = (string) $this->params->get('robots', '')) {
            $document->setMetaData('robots', $robots);
        }

        if ($this->itemId) {
            $document->addHeadLink(htmlspecialchars(Route::_('index.php?Itemid=' . $this->itemId, false, Route::TLS_IGNORE, true), ENT_COMPAT, 'UTF-8'), 'canonical');
        }

        if ((int) $this->params->get('show_jsonld', 1) === 1 && $this->items !== [] && $this->mode === 'list') {
            $document->getWebAssetManager()->addInlineScript(
                EventHelper::jsonLd($this->items, $this->itemId, (int) $this->params->get('show_detail_page', 1) === 1),
                ['name' => 'm365calendar.jsonld'],
                ['type' => 'application/ld+json']
            );
        }
    }
}
