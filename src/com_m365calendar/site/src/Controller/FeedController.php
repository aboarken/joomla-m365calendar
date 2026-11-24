<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Site\Controller;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\M365calendar\Administrator\Extension\M365calendarComponent;
use Joomla\Component\M365calendar\Administrator\Feed\FeedAccessResolver;
use Joomla\Component\M365calendar\Administrator\Feed\FeedCalendarResolver;
use Joomla\Component\M365calendar\Administrator\Feed\FeedTokenService;
use Joomla\Component\M365calendar\Administrator\Feed\IcsWriter;
use Joomla\Component\M365calendar\Administrator\Feed\RateLimiter;
use Joomla\Component\M365calendar\Administrator\Filter\FilterResolver;
use Joomla\Component\M365calendar\Administrator\Filter\PresetFilter;
use Joomla\Component\M365calendar\Administrator\Filter\VisitorFilter;
use Joomla\Component\M365calendar\Administrator\Helper\LogHelper;
use Joomla\Component\M365calendar\Site\Helper\EventHelper;
use Joomla\Component\M365calendar\Site\Service\EventRepository;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\IpHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Calendar subscription feeds (format=ics), single-event .ics downloads and
 * personal feed link creation/reset.
 *
 * Feed URLs and tokens are never logged; only menu item and user IDs.
 *
 * @since  0.1.0
 */
class FeedController extends BaseController
{
    /**
     * Feed cache lifetime in minutes.
     *
     * @var    integer
     * @since  0.1.0
     */
    public const CACHE_MINUTES = 15;

    /**
     * Requests per minute and IP address (Joomla's IpHelper, which honours the
     * "Behind Load Balancer" setting, so proxied visitors are not counted as one).
     *
     * @var    integer
     * @since  0.1.0
     */
    public const LIMIT_PER_IP = 60;

    /**
     * Requests per minute and token.
     *
     * @var    integer
     * @since  0.1.0
     */
    public const LIMIT_PER_TOKEN = 20;

    /**
     * Maximum events in a feed.
     *
     * @var    integer
     * @since  0.1.0
     */
    public const MAX_EVENTS = 2000;

    /**
     * Session key holding a newly created personal token until it is shown once.
     *
     * @var    string
     * @since  0.1.0
     */
    public const SESSION_KEY = 'com_m365calendar.feedtoken.new';

    /**
     * Serve the subscription feed of a Calendar menu item.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function serve(): void
    {
        LogHelper::register();

        $menu = $this->calendarMenuItem();

        if ($menu === null) {
            $this->respond(404);

            return;
        }

        $params = $menu->getParams();
        $mode   = (string) $params->get('feed_mode', 'none');

        if (!\in_array($mode, [FeedCalendarResolver::MODE_PUBLIC, FeedCalendarResolver::MODE_PERSONAL], true)) {
            $this->respond(404);

            return;
        }

        $limiter = $this->rateLimiter();

        if (!$limiter->hit('ip|' . IpHelper::getIp(), self::LIMIT_PER_IP)) {
            $this->respond(429, '', ['Retry-After' => (string) $limiter->retryAfter()]);

            return;
        }

        $guestLevels = array_map('intval', Access::getAuthorisedViewLevels(0));
        $userId      = 0;
        $levels      = $guestLevels;

        if ($mode === FeedCalendarResolver::MODE_PERSONAL) {
            $token = $this->input->getAlnum('token', '');

            if (!$limiter->hit('token|' . FeedTokenService::hash($token), self::LIMIT_PER_TOKEN)) {
                $this->respond(429, '', ['Retry-After' => (string) $limiter->retryAfter()]);

                return;
            }

            $principal = $this->accessResolver()->resolve($token);

            if ($principal === null) {
                $this->respond(404);

                return;
            }

            $userId = $principal->userId;
            $levels = $principal->viewLevels;
            sort($levels);
        }

        // The menu item itself must be visible to whoever the feed is for.
        if (!\in_array((int) $menu->access, $levels, true)) {
            $this->respond(404);

            return;
        }

        $cacheKey = $mode === FeedCalendarResolver::MODE_PUBLIC
            ? 'public.' . (int) $menu->id
            // The view levels are part of the key, so a change of the user's groups applies at once.
            : 'personal.' . $userId . '.' . (int) $menu->id . '.' . md5(implode(',', $levels));

        try {
            $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController(
                'callback',
                ['defaultgroup' => M365calendarComponent::FEED_CACHE_GROUP, 'lifetime' => self::CACHE_MINUTES, 'caching' => true]
            );

            $payload = $cache->get(
                fn (): array => $this->buildFeed($menu, $mode, $guestLevels, $levels),
                [],
                // The feed contains absolute URLs, so the host is part of the key: a request
                // with a forged Host header cannot poison the copy served to everyone else.
                md5('m365calendar.feed.' . $cacheKey . '.' . Uri::root())
            );
        } catch (\Throwable) {
            LogHelper::logger()->error(
                'Feed generation failed for menu item ' . (int) $menu->id . ', user ' . $userId . '.',
                ['category' => LogHelper::CATEGORY_FEED]
            );
            $this->respond(500);

            return;
        }

        $this->sendCalendar($payload, $mode === FeedCalendarResolver::MODE_PUBLIC, 'calendar.ics', false);
    }

    /**
     * Download one event as .ics (for Outlook desktop and Apple Calendar).
     *
     * Uses the visitor's own access levels and the menu item's preset filters.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function event(): void
    {
        $menu = $this->calendarMenuItem();

        if ($menu === null || (int) $this->app->getParams('com_m365calendar')->get('show_add_to_calendar', 1) !== 1) {
            $this->respond(404);

            return;
        }

        $limiter = $this->rateLimiter();

        if (!$limiter->hit('ip|' . IpHelper::getIp(), self::LIMIT_PER_IP)) {
            $this->respond(429, '', ['Retry-After' => (string) $limiter->retryAfter()]);

            return;
        }

        /** @var \Joomla\Component\M365calendar\Site\Model\EventModel $model */
        $model = $this->getModel('Event', 'Site');
        $row   = $model->getItem($this->input->getUint('id', 0));

        if ($row === null) {
            $this->respond(404);

            return;
        }

        $detail = (int) $menu->getParams()->get('show_detail_page', 1) === 1;
        $writer = new IcsWriter(Uri::getInstance()->getHost());
        $body   = $writer->calendar(
            (string) $row->subject,
            [EventHelper::icsEvent($row, EventHelper::publicUrl($row, (int) $menu->id, $detail))],
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->sendCalendar(
            ['body' => $body, 'etag' => '"' . sha1($body) . '"', 'modified' => time()],
            false,
            'event.ics',
            true
        );
    }

    /**
     * Create or reset the current user's personal feed link (POST + CSRF token).
     *
     * The plain token is kept in the session only until it is shown once.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function token(): void
    {
        $this->checkToken();

        $menu = $this->calendarMenuItem();
        $user = $this->app->getIdentity();

        if ($menu === null || (string) $menu->getParams()->get('feed_mode', 'none') !== FeedCalendarResolver::MODE_PERSONAL) {
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        if ($user === null || $user->guest || !\in_array((int) $menu->access, array_map('intval', $user->getAuthorisedViewLevels()), true)) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var M365calendarComponent $component */
        $component = $this->app->bootComponent('com_m365calendar');
        $hadToken  = $component->getFeedTokenService()->hasToken((int) $user->id);
        $token     = $component->getFeedTokenService()->reset((int) $user->id);

        $this->app->getSession()->set(self::SESSION_KEY, ['user' => (int) $user->id, 'token' => $token]);

        $this->setRedirect(
            EventHelper::calendarUrl((int) $menu->id),
            Text::_($hadToken ? 'COM_M365CALENDAR_SUBSCRIBE_RESET_DONE' : 'COM_M365CALENDAR_SUBSCRIBE_CREATED')
        );
    }

    /**
     * Build the feed payload (cached).
     *
     * @param   MenuItem   $menu         Menu item.
     * @param   string     $mode         Feed mode.
     * @param   integer[]  $guestLevels  Guest view levels.
     * @param   integer[]  $levels       View levels of the feed's audience.
     *
     * @return  array{body: string, etag: string, modified: int}
     *
     * @since   0.1.0
     */
    private function buildFeed(MenuItem $menu, string $mode, array $guestLevels, array $levels): array
    {
        $params     = $menu->getParams();
        $repository = new EventRepository(Factory::getContainer()->get(DatabaseInterface::class));
        $preset     = PresetFilter::fromParams($params);

        // Only "Allow in feeds" calendars; public links only Public calendars.
        $calendarIds = FeedCalendarResolver::resolve($repository->publishedCalendars(), $mode, $guestLevels, $levels, $preset->calendarIds);

        $now    = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $ahead  = max(1, min(1095, (int) $params->get('feed_days_ahead', 180)));
        $back   = max(0, min(730, (int) $params->get('feed_days_back', 30)));
        $filter = FilterResolver::resolve(
            $preset,
            new VisitorFilter(),
            $calendarIds,
            $now,
            new \DateTimeZone($this->app->get('offset', 'UTC')),
            new \DateTimeZone($this->app->get('offset', 'UTC'))
        )->withRange($now->modify('-' . $back . ' days'), $now->modify('+' . $ahead . ' days'));

        $rows     = $calendarIds === [] ? [] : $repository->events($filter, $levels, 'start_asc', self::MAX_EVENTS);
        $detail   = (int) $params->get('show_detail_page', 1) === 1;
        $events   = [];
        $modified = 0;

        foreach ($rows as $row) {
            $events[] = EventHelper::icsEvent($row, EventHelper::publicUrl($row, (int) $menu->id, $detail));
            $modified = max($modified, (int) strtotime((string) $row->synced_utc . ' UTC'));
        }

        $name = (string) $params->get('page_title', '') ?: (string) $menu->title;
        $body = (new IcsWriter(Uri::getInstance()->getHost()))->calendar($name, $events, $now, 'PT1H');

        return [
            'body'     => $body,
            'etag'     => '"' . sha1($body) . '"',
            'modified' => $modified ?: $now->getTimestamp(),
        ];
    }

    /**
     * Send a calendar, answering conditional requests with 304.
     *
     * @param   array{body: string, etag: string, modified: int}  $payload     Payload.
     * @param   boolean                                            $public      Shared caches may store it.
     * @param   string                                             $filename    File name.
     * @param   boolean                                            $attachment  Download instead of inline.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function sendCalendar(array $payload, bool $public, string $filename, bool $attachment): void
    {
        $lastModified = gmdate('D, d M Y H:i:s', $payload['modified']) . ' GMT';
        $headers      = [
            'ETag'          => $payload['etag'],
            'Last-Modified' => $lastModified,
            'Cache-Control' => ($public ? 'public' : 'private') . ', max-age=' . (self::CACHE_MINUTES * 60),
        ];

        $ifNoneMatch     = trim($this->input->server->getString('HTTP_IF_NONE_MATCH', ''));
        $ifModifiedSince = trim($this->input->server->getString('HTTP_IF_MODIFIED_SINCE', ''));
        $notModified     = false;

        if ($ifNoneMatch !== '') {
            $tags        = array_map(static fn (string $t): string => trim(preg_replace('/^W\//', '', trim($t)) ?? ''), explode(',', $ifNoneMatch));
            $notModified = \in_array($payload['etag'], $tags, true) || \in_array('*', $tags, true);
        } elseif ($ifModifiedSince !== '' && ($since = strtotime($ifModifiedSince)) !== false) {
            $notModified = $since >= $payload['modified'];
        }

        if ($notModified) {
            $this->respond(304, '', $headers);

            return;
        }

        $headers['Content-Type']        = 'text/calendar; charset=utf-8';
        $headers['Content-Disposition'] = ($attachment ? 'attachment' : 'inline') . '; filename="' . $filename . '"';

        $this->respond(200, $payload['body'], $headers);
    }

    /**
     * The Calendar menu item addressed by Itemid (published, of this component).
     *
     * @return  MenuItem|null
     *
     * @since   0.1.0
     */
    private function calendarMenuItem(): ?MenuItem
    {
        $itemId = $this->input->getInt('Itemid', 0);
        $menu   = $itemId > 0 ? $this->app->getMenu()->getItem($itemId) : null;

        if (!$menu instanceof MenuItem || $menu->component !== 'com_m365calendar' || ($menu->query['view'] ?? '') !== 'calendar') {
            return null;
        }

        return $menu;
    }

    /**
     * Token to user resolver.
     *
     * @return  FeedAccessResolver
     *
     * @since   0.1.0
     */
    private function accessResolver(): FeedAccessResolver
    {
        /** @var M365calendarComponent $component */
        $component   = $this->app->bootComponent('com_m365calendar');
        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);

        return new FeedAccessResolver(
            $component->getFeedTokenService(),
            static function (int $id) use ($userFactory): ?array {
                $user = $userFactory->loadUserById($id);

                if (!$user->id) {
                    return null;
                }

                // Only view levels are used: the token never logs the user in.
                return ['blocked' => (int) $user->block === 1, 'viewLevels' => $user->getAuthorisedViewLevels()];
            }
        );
    }

    /**
     * Rate limiter (cache-based, hashed keys).
     *
     * @return  RateLimiter
     *
     * @since   0.1.0
     */
    private function rateLimiter(): RateLimiter
    {
        /** @var \Joomla\CMS\Cache\Controller\OutputController $cache */
        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController(
            'output',
            ['defaultgroup' => 'com_m365calendar_ratelimit', 'lifetime' => 2, 'caching' => true]
        );

        return new RateLimiter($cache, 60);
    }

    /**
     * Send a complete response and end the request.
     *
     * @param   integer                $status   HTTP status.
     * @param   string                 $body     Body.
     * @param   array<string, string>  $headers  Headers.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function respond(int $status, string $body = '', array $headers = []): void
    {
        $app = $this->app;

        $app->setHeader('status', (string) $status, true);
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);

        if ($status !== 200 && $status !== 304 && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'text/plain; charset=utf-8';
            $headers['Cache-Control'] ??= 'no-store';
        }

        foreach ($headers as $name => $value) {
            $app->setHeader($name, $value, true);
        }

        $app->sendHeaders();

        if ($status !== 304) {
            echo $body !== '' ? $body : ($status === 404 ? 'Not Found' : ($status === 429 ? 'Too Many Requests' : 'Error'));
        }

        $app->close();
    }
}
