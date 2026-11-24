<?php

/**
 * Integration smoke test. Runs INSIDE a Joomla site that has the package
 * installed (see tests/Integration/run.sh) and exercises the real database
 * code on that site's database: tables, sync (with a stand-in Graph client),
 * filters, feed tokens, cascade delete, and it creates a Calendar menu item
 * for the HTTP checks that follow.
 *
 * Usage: php tests/Integration/smoke.php /path/to/joomla
 *
 * Only for disposable test sites: it creates and deletes calendars.
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
const _JEXEC = 1;

if (PHP_SAPI !== 'cli' || empty($argv[1]) || !is_file($argv[1] . '/includes/framework.php')) {
    fwrite(STDERR, "Usage: php smoke.php /path/to/joomla\n");
    exit(2);
}

define('JPATH_BASE', realpath($argv[1]));

// Joomla's console application sets a host for the router; do the same here.
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Component\M365calendar\Administrator\Feed\DatabaseFeedTokenStore;
use Joomla\Component\M365calendar\Administrator\Feed\FeedTokenService;
use Joomla\Component\M365calendar\Administrator\Filter\FilterResolver;
use Joomla\Component\M365calendar\Administrator\Filter\PresetFilter;
use Joomla\Component\M365calendar\Administrator\Filter\VisitorFilter;
use Joomla\Component\M365calendar\Administrator\Service\DatabaseEventStore;
use Joomla\Component\M365calendar\Administrator\Service\SyncOptions;
use Joomla\Component\M365calendar\Administrator\Service\SyncResult;
use Joomla\Component\M365calendar\Administrator\Service\SyncService;
use Joomla\Component\M365calendar\Site\Service\EventRepository;
use Joomla\Database\DatabaseInterface;
use M365Graph\ConnectionSettings;
use M365Graph\Exception\ThrottledException;
use M365Graph\GraphClientInterface;

// Boot Joomla the way cli/joomla.php does.
$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app = $container->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();

/** @var DatabaseInterface $db */
$db     = $container->get(DatabaseInterface::class);
$checks = 0;

$check = static function (bool $ok, string $what) use (&$checks): void {
    $checks++;

    if (!$ok) {
        fwrite(STDERR, "FAILED: $what\n");
        exit(1);
    }

    echo "  ok  $what\n";
};

$scalar = static function (string $sql) use ($db) {
    return $db->setQuery($sql)->loadResult();
};

echo "Installation\n";

$tables = array_map('strtolower', $db->getTableList());

foreach (['calendars', 'events', 'event_categories', 'sync', 'feed_tokens'] as $name) {
    $check(in_array(strtolower($db->getPrefix()) . 'm365calendar_' . $name, $tables, true), "table #__m365calendar_$name exists");
}

$check((int) $scalar('SELECT COUNT(*) FROM ' . $db->quoteName('#__m365calendar_sync') . ' WHERE ' . $db->quoteName('calendar_id') . ' = 0') === 1, 'lock row exists');
$check(
    (int) $scalar(
        'SELECT ' . $db->quoteName('enabled') . ' FROM ' . $db->quoteName('#__extensions')
        . ' WHERE ' . $db->quoteName('element') . ' = ' . $db->quote('m365calendarsync') . ' AND ' . $db->quoteName('folder') . ' = ' . $db->quote('task')
    ) === 1,
    'task plugin is enabled'
);
$check(
    str_contains((string) $scalar('SELECT ' . $db->quoteName('params') . ' FROM ' . $db->quoteName('#__extensions') . ' WHERE ' . $db->quoteName('element') . ' = ' . $db->quote('com_actionlogs')), 'com_m365calendar'),
    'component is ticked for the User Actions Log'
);
$check((int) $scalar('SELECT COUNT(*) FROM ' . $db->quoteName('#__m365calendar_calendars')) === 0, 'site has no calendars yet (disposable test site)');

echo "Calendars (table validation)\n";

$component = $app->bootComponent('com_m365calendar');
$factory   = $component->getMVCFactory();

$newCalendar = static function (array $data) use ($factory): Table {
    $table = $factory->createTable('Calendar', 'Administrator');
    $table->bind($data + ['published' => 1, 'access' => 1, 'color' => '#0d6efd']);

    return $table;
};

$bad = $newCalendar(['title' => 'Bad', 'mailbox' => 'not-an-email']);
$check($bad->check() === false, 'invalid mailbox is rejected');
$bad = $newCalendar(['title' => 'Bad', 'mailbox' => 'a@example.org', 'graph_calendar_id' => "x' OR 1=1"]);
$check($bad->check() === false, 'invalid calendar ID is rejected');

$ids = [];

foreach (
    [
        'board' => ['title' => 'Board', 'mailbox' => 'Board+Room@Example.org', 'allow_feeds' => 1],
        'staff' => ['title' => 'Staff', 'mailbox' => 'staff@example.org', 'access' => 2, 'allow_feeds' => 1],
        'hr'    => ['title' => 'HR', 'mailbox' => 'hr@example.org', 'graph_calendar_id' => 'AAMk/abc+def==', 'allow_feeds' => 0],
    ] as $key => $data
) {
    $table = $newCalendar($data);
    $check($table->check() && $table->store(), "calendar '$key' saved");
    $ids[$key] = (int) $table->id;
}

$check($scalar('SELECT ' . $db->quoteName('mailbox') . ' FROM ' . $db->quoteName('#__m365calendar_calendars') . ' WHERE ' . $db->quoteName('id') . ' = ' . $ids['board']) === 'board+room@example.org', 'mailbox is normalised to lower case');

echo "Sync (real store, stand-in Graph client)\n";

$tz    = 'Europe/Berlin';
$day   = static fn (int $offset, string $time): string => (new DateTimeImmutable('today ' . $time, new DateTimeZone($tz)))->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d\TH:i:s') . '.0000000';
$event = static function (string $id, string $subject, int $offset, array $extra = []) use ($day, $tz): array {
    return $extra + [
        'id'          => $id,
        'subject'     => $subject,
        'start'       => ['dateTime' => $day($offset, '09:00'), 'timeZone' => $tz],
        'end'         => ['dateTime' => $day($offset, '10:00'), 'timeZone' => $tz],
        'isAllDay'    => false,
        'location'    => ['displayName' => 'Room 1'],
        'categories'  => ['Board'],
        'sensitivity' => 'normal',
        'isCancelled' => false,
        'webLink'     => 'https://outlook.office365.com/owa/?itemid=' . rawurlencode($id),
        'body'        => ['content' => 'SECRET-BODY'],
        'attendees'   => [['emailAddress' => ['address' => 'secret@attendee.example']]],
    ];
};

$client = new class () implements GraphClientInterface {
    /** @var array<string, array<int, array<string, mixed>>> */
    public array $data = [];

    /** @var array<string, \M365Graph\Exception\GraphException> */
    public array $failures = [];

    /** @var string[] */
    public array $paths = [];

    public function authenticate(): void
    {
    }

    public function get(string $path, array $query = [], array $headers = []): array
    {
        return ['id' => 'x'];
    }

    public function getCollection(string $path, array $query = [], array $headers = []): \Generator
    {
        $this->paths[] = $path;

        foreach ($this->data[$path] ?? [] as $item) {
            yield $item;

            if (isset($this->failures[$path])) {
                throw $this->failures[$path];
            }
        }
    }
};

$boardPath = '/users/board%2Broom%40example.org/calendarView';
$staffPath = '/users/staff%40example.org/calendarView';
$hrPath    = '/users/hr%40example.org/calendars/AAMk%2Fabc%2Bdef%3D%3D/calendarView';

$client->data = [
    $boardPath => [
        $event('b1', 'Board meeting; budget, Q4', 1, ['categories' => ['Board', 'Finance']]),
        $event('b2', '<script>alert(1)</script> & "quotes"', 2),
        $event('b3', 'Holiday', 0, ['isAllDay' => true, 'start' => ['dateTime' => '2030-12-24T00:00:00.0000000', 'timeZone' => $tz], 'end' => ['dateTime' => '2030-12-26T00:00:00.0000000', 'timeZone' => $tz], 'categories' => []]),
        $event('b4', 'To be removed', 3),
        $event('b5', 'PRIVATE', 2, ['sensitivity' => 'private']),
        $event('b6', 'CANCELLED', 2, ['isCancelled' => true]),
        $event('b7', "Ünïcödé 日本語 100%_off \\ end", 4, ['categories' => ['Training']]),
    ],
    $staffPath => [$event('s1', 'Staff only event', 5, ['categories' => ['Staff']])],
    $hrPath    => [$event('c1', 'Confidential HR review', 2, ['categories' => ['HR']])],
];

$service = new SyncService(
    new DatabaseEventStore($db),
    static fn (ConnectionSettings $settings): GraphClientInterface => $client,
    new ConnectionSettings('contoso.onmicrosoft.com', '11111111-2222-3333-4444-555555555555', '/x.pem', '/x.key'),
    new SyncOptions(30, 3650, $tz, 15),
    \Joomla\CMS\Log\Log::createDelegatedLogger()
);

$events = $db->quoteName('#__m365calendar_events');
$count  = static fn (string $where = '1 = 1') => (int) $scalar("SELECT COUNT(*) FROM $events WHERE $where");

$result = $service->syncAll('smoke');
$check($result->state === SyncResult::DONE && $result->failedCount() === 0, 'first sync succeeds for all calendars');
$check($client->paths === [$boardPath, $staffPath, $hrPath], 'Graph paths are URL-encoded (mailbox with "+", calendar ID with "/+=")');
$check($count($db->quoteName('calendar_id') . ' = ' . $ids['board']) === 5, 'private and cancelled events are not stored');
$check($count($db->quoteName('subject') . ' LIKE ' . $db->quote('%SECRET%') . ' OR ' . $db->quoteName('web_link') . ' LIKE ' . $db->quote('%SECRET%')) === 0, 'no body or attendee data is stored');

$b1 = $db->setQuery("SELECT * FROM $events WHERE " . $db->quoteName('subject') . ' LIKE ' . $db->quote('Board meeting%'))->loadObject();
$expected = (new DateTimeImmutable('tomorrow 09:00', new DateTimeZone($tz)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$check(substr((string) $b1->start_utc, 0, 19) === $expected, 'times are stored in UTC');
$check(json_decode((string) $b1->categories, true) === ['Board', 'Finance'], 'categories are stored as JSON');
$check((int) $scalar('SELECT COUNT(*) FROM ' . $db->quoteName('#__m365calendar_event_categories') . ' WHERE ' . $db->quoteName('event_id') . ' = ' . (int) $b1->id) === 2, 'categories are normalised for filtering');

$holiday = $db->setQuery("SELECT * FROM $events WHERE " . $db->quoteName('subject') . ' = ' . $db->quote('Holiday'))->loadObject();
$check((int) $holiday->all_day === 1 && substr((string) $holiday->start_utc, 0, 10) === '2030-12-24' && substr((string) $holiday->end_utc, 0, 10) === '2030-12-26', 'all-day events keep their dates');
$check($scalar("SELECT " . $db->quoteName('subject') . " FROM $events WHERE " . $db->quoteName('subject') . ' LIKE ' . $db->quote('%日本語%')) === "Ünïcödé 日本語 100%_off \\ end", 'Unicode text survives the round trip');

$sync = $db->setQuery('SELECT * FROM ' . $db->quoteName('#__m365calendar_sync') . ' WHERE ' . $db->quoteName('calendar_id') . ' = ' . $ids['board'])->loadObject();
$check($sync->status === 'ok' && (int) $sync->event_count === 5 && !empty($sync->last_success), 'per-calendar status is recorded');
$lock = $db->setQuery('SELECT * FROM ' . $db->quoteName('#__m365calendar_sync') . ' WHERE ' . $db->quoteName('calendar_id') . ' = 0')->loadObject();
$check(empty($lock->locked_until) && (string) $lock->lock_owner === '', 'lock is released after the run');

// Second run: one event changed, one removed, one calendar fails half-way.
$client->paths              = [];
$client->data[$boardPath][0] = $event('b1', 'Board meeting; budget, Q4 (moved)', 1, ['categories' => ['Board']]);
unset($client->data[$boardPath][3]);
$client->data[$staffPath][]  = $event('s2', 'Second page never arrives', 6);
$client->failures[$staffPath] = new ThrottledException('HTTP 429', 429, 'req-smoke');

$result = $service->syncAll('smoke');
$check($result->state === SyncResult::DONE && $result->failedCount() === 1, 'second sync: one calendar fails, the others continue');

$b1After = $db->setQuery("SELECT * FROM $events WHERE " . $db->quoteName('id') . ' = ' . (int) $b1->id)->loadObject();
$check($b1After !== null && $b1After->subject === 'Board meeting; budget, Q4 (moved)', 'changed event is updated in place (same row)');
$check((int) $scalar('SELECT COUNT(*) FROM ' . $db->quoteName('#__m365calendar_event_categories') . ' WHERE ' . $db->quoteName('event_id') . ' = ' . (int) $b1->id) === 1, 'its categories are rebuilt');
$check($count($db->quoteName('subject') . ' = ' . $db->quote('To be removed')) === 0, 'event that left the window is deleted');
$check($count($db->quoteName('calendar_id') . ' = ' . $ids['staff']) === 1 && $count($db->quoteName('subject') . ' LIKE ' . $db->quote('Second page%')) === 0, 'failed calendar keeps its previous data');

$sync = $db->setQuery('SELECT * FROM ' . $db->quoteName('#__m365calendar_sync') . ' WHERE ' . $db->quoteName('calendar_id') . ' = ' . $ids['staff'])->loadObject();
$check($sync->status === 'error' && str_contains((string) $sync->message, 'HTTP 429') && str_contains((string) $sync->message, 'req-smoke') && (int) $sync->event_count === 1, 'failure status has the HTTP code and request-id');
$check(
    (int) $scalar(
        'SELECT COUNT(*) FROM ' . $db->quoteName('#__m365calendar_event_categories', 'ec') . ' LEFT JOIN ' . $db->quoteName('#__m365calendar_events', 'e')
        . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('ec.event_id') . ' WHERE ' . $db->quoteName('e.id') . ' IS NULL'
    ) === 0,
    'no orphaned category rows'
);

// A lock held by another run blocks the sync.
$store = new DatabaseEventStore($db);
$now   = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$check($store->acquireLock('other-run', $now, $now->modify('+5 minutes')), 'lock can be taken');
$check($service->syncAll('smoke')->state === SyncResult::LOCKED, 'a second sync is refused while the lock is held');
$store->releaseLock('other-run');
$check($store->acquireLock('expired', $now->modify('-10 minutes'), $now->modify('-5 minutes')) && $store->acquireLock('next', $now, $now->modify('+1 minute')), 'an expired lock is taken over');
$store->releaseLock('next');

echo "Filters (real queries)\n";

$repository = new EventRepository($db);
$utc        = new DateTimeZone('UTC');
$titles     = static function (PresetFilter $preset, VisitorFilter $visitor, array $levels) use ($repository, $now, $utc): array {
    $accessible = array_map(static fn (object $c): int => (int) $c->id, $repository->accessibleCalendars($levels));
    $filter     = FilterResolver::resolve($preset, $visitor, $accessible, $now, $utc, $utc);

    return array_map(static fn (object $row): string => (string) $row->subject, $repository->events($filter, $levels, 'title_asc', 100));
};

$guest      = [1];
$registered = [1, 2];

$check(count($titles(new PresetFilter(), new VisitorFilter(), $guest)) === 5, 'guest sees Public calendars only');
$check(in_array('Staff only event', $titles(new PresetFilter(), new VisitorFilter(), $registered), true), 'registered user also sees the Registered calendar');
$check($titles(new PresetFilter([$ids['board']]), new VisitorFilter(calendarId: $ids['hr']), $guest) === $titles(new PresetFilter([$ids['board']]), new VisitorFilter(), $guest), 'visitor cannot select a calendar outside the preset');
$check($titles(new PresetFilter(categories: ['Finance']), new VisitorFilter(category: 'HR'), $guest) === [], 'visitor cannot select a category outside the preset (Finance was removed from b1, so nothing matches)');
$check($titles(new PresetFilter(categories: ['Training']), new VisitorFilter(), $guest) === ["Ünïcödé 日本語 100%_off \\ end"], 'category preset filters');
$check($titles(new PresetFilter(), new VisitorFilter(search: 'BUDGET'), $guest) === ['Board meeting; budget, Q4 (moved)'], 'search is case-insensitive');
$check($titles(new PresetFilter(), new VisitorFilter(search: '%'), $guest) === ["Ünïcödé 日本語 100%_off \\ end"], 'a "%" in the search is literal, not a wildcard');
$check($titles(new PresetFilter(), new VisitorFilter(search: '_off \\'), $guest) === ["Ünïcödé 日本語 100%_off \\ end"], '"_" and "\\" in the search are literal');
$check($titles(new PresetFilter(), new VisitorFilter(search: "' OR 1=1 --"), $guest) === [], 'SQL in the search is just text');
$check($titles(new PresetFilter(eventType: 'allday'), new VisitorFilter(), $guest) === ['Holiday'], 'all-day preset');
$check($titles(new PresetFilter(eventType: 'allday'), new VisitorFilter(eventType: 'timed'), $guest) === [], 'conflicting visitor type matches nothing');
$check($titles(new PresetFilter(dateMode: 'fixed', dateFrom: '2030-12-25', dateTo: '2030-12-25'), new VisitorFilter(), $guest) === ['Holiday'], 'fixed date window (overlap)');
$check(count($titles(new PresetFilter(dateMode: 'next', days: 3), new VisitorFilter(dateFrom: '2000-01-01', dateTo: '2099-01-01'), $guest)) === 3, 'visitor dates cannot widen the preset window');
$check($repository->categories([$ids['board']]) === ['Board', 'Training'], 'category list for the visitor filter');

echo "Feed tokens (real store)\n";

$userId = (int) $scalar('SELECT MIN(' . $db->quoteName('id') . ') FROM ' . $db->quoteName('#__users'));
$tokens = new FeedTokenService(new DatabaseFeedTokenStore($db));
$first  = $tokens->issue($userId);
$check($tokens->resolveUserId($first) === $userId, 'issued token resolves to its user');
$check($scalar('SELECT ' . $db->quoteName('token_hash') . ' FROM ' . $db->quoteName('#__m365calendar_feed_tokens')) === hash('sha256', $first), 'only the SHA-256 hash is stored');
$check(!empty($scalar('SELECT ' . $db->quoteName('last_used') . ' FROM ' . $db->quoteName('#__m365calendar_feed_tokens'))), 'last_used is recorded');
$second = $tokens->reset($userId);
$check($tokens->resolveUserId($first) === null && $tokens->resolveUserId($second) === $userId, 'reset invalidates the old token');
$check((int) $scalar('SELECT COUNT(*) FROM ' . $db->quoteName('#__m365calendar_feed_tokens')) === 1, 'one token row per user');

// A token of a user that no longer exists is removed by the sync's clean-up.
$orphanHash = hash('sha256', 'orphan');
$db->setQuery(
    'INSERT INTO ' . $db->quoteName('#__m365calendar_feed_tokens') . ' (' . implode(', ', $db->quoteName(['user_id', 'token_hash', 'created'])) . ') VALUES (99999999, '
    . $db->quote($orphanHash) . ', ' . $db->quote('2026-01-01 00:00:00') . ')'
)->execute();
$check($store->deleteOrphanFeedTokens() === 1 && $tokens->resolveUserId($second) === $userId, 'tokens of deleted users are removed, others kept');
$tokens->revoke($userId);
$check($tokens->hasToken($userId) === false, 'revoke removes the token');

echo "Cascade delete\n";

$table = $factory->createTable('Calendar', 'Administrator');
$check($table->delete($ids['hr']), 'calendar deleted');
$check($count($db->quoteName('calendar_id') . ' = ' . $ids['hr']) === 0, 'its events are deleted');
$check((int) $scalar('SELECT COUNT(*) FROM ' . $db->quoteName('#__m365calendar_sync') . ' WHERE ' . $db->quoteName('calendar_id') . ' = ' . $ids['hr']) === 0, 'its sync status is deleted');

echo "Menu item for the HTTP checks\n";

$componentId = (int) $scalar('SELECT ' . $db->quoteName('extension_id') . ' FROM ' . $db->quoteName('#__extensions') . ' WHERE ' . $db->quoteName('element') . ' = ' . $db->quote('com_m365calendar'));

foreach (['smoke-events' => 'public', 'smoke-personal' => 'personal'] as $alias => $feedMode) {
    $menu = new \Joomla\CMS\Table\Menu($db);
    $menu->setLocation(1, 'last-child');
    $menu->bind([
        'menutype'     => 'mainmenu',
        'title'        => 'Smoke ' . $feedMode,
        'alias'        => $alias,
        'link'         => 'index.php?option=com_m365calendar&view=calendar',
        'type'         => 'component',
        'published'    => 1,
        'component_id' => $componentId,
        'access'       => 1,
        'language'     => '*',
        'client_id'    => 0,
        'params'       => json_encode(['feed_mode' => $feedMode, 'show_filter_bar' => 1, 'events_per_page' => 20]),
    ]);
    $check($menu->check() && $menu->store() && $menu->rebuildPath($menu->id), "menu item /$alias created");
}

echo "\nOK: $checks checks passed.\n";
