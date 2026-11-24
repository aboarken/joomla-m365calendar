<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Service;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * EventStoreInterface backed by the Joomla database (query builder, bound parameters).
 *
 * @since  0.1.0
 */
final class DatabaseEventStore implements EventStoreInterface
{
    /**
     * Rows per multi-row statement.
     *
     * @var    integer
     * @since  0.1.0
     */
    private const CHUNK = 200;

    /**
     * Constructor.
     *
     * @param   DatabaseInterface  $db  Database.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function acquireLock(string $owner, \DateTimeImmutable $now, \DateTimeImmutable $until): bool
    {
        $this->ensureSyncRow(0);

        $db      = $this->db;
        $nowStr  = self::sql($now);
        $untilStr = self::sql($until);
        $query   = $db->getQuery(true)
            ->update($db->quoteName('#__m365calendar_sync'))
            ->set($db->quoteName('locked_until') . ' = :until')
            ->set($db->quoteName('lock_owner') . ' = :owner')
            ->where($db->quoteName('calendar_id') . ' = 0')
            ->extendWhere(
                'AND',
                [
                    $db->quoteName('locked_until') . ' IS NULL',
                    $db->quoteName('locked_until') . ' < :now',
                    $db->quoteName('lock_owner') . ' = :owner2',
                ],
                'OR'
            )
            ->bind(':until', $untilStr)
            ->bind(':owner', $owner)
            ->bind(':now', $nowStr)
            ->bind(':owner2', $owner);

        $db->setQuery($query)->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function releaseLock(string $owner): void
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__m365calendar_sync'))
            ->set($db->quoteName('locked_until') . ' = NULL')
            ->set($db->quoteName('lock_owner') . ' = ' . $db->quote(''))
            ->where($db->quoteName('calendar_id') . ' = 0')
            ->where($db->quoteName('lock_owner') . ' = :owner')
            ->bind(':owner', $owner);

        $db->setQuery($query)->execute();
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function getPublishedCalendars(): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'mailbox', 'graph_calendar_id']))
            ->from($db->quoteName('#__m365calendar_calendars'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

        $calendars = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            if (filter_var($row->mailbox, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $calendars[] = new CalendarRef((int) $row->id, (string) $row->title, (string) $row->mailbox, (string) $row->graph_calendar_id);
        }

        return $calendars;
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function replaceEvents(int $calendarId, array $events, \DateTimeImmutable $now): int
    {
        $db     = $this->db;
        $synced = self::sql($now);

        // bind() takes a reference and database drivers may change the bound
        // variable's type (PDO turns it into a string), so never bind a
        // variable that is used again afterwards.
        $boundCalendarId = $calendarId;

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'uid_hash']))
            ->from($db->quoteName('#__m365calendar_events'))
            ->where($db->quoteName('calendar_id') . ' = :cid')
            ->bind(':cid', $boundCalendarId, ParameterType::INTEGER);

        /** @var array<string, string> $existing uid_hash => id */
        $existing = $db->setQuery($query)->loadAssocList('uid_hash', 'id');

        $db->transactionStart();

        try {
            $touched    = [];
            $categories = [];

            foreach ($events as $event) {
                $id = isset($existing[$event->uidHash])
                    ? $this->updateEvent((int) $existing[$event->uidHash], $event, $synced)
                    : $this->insertEvent($calendarId, $event, $synced);

                $touched[$id] = true;

                foreach ($event->categories as $category) {
                    $categories[] = [$id, $category];
                }
            }

            $touchedIds = array_keys($touched);
            $staleIds   = array_values(array_diff(array_map('intval', $existing), $touchedIds));

            // Categories of touched and stale events are rebuilt / removed.
            $this->deleteByIds('#__m365calendar_event_categories', 'event_id', array_merge($touchedIds, $staleIds));
            $this->deleteByIds('#__m365calendar_events', 'id', $staleIds);

            foreach (array_chunk($categories, self::CHUNK) as $chunk) {
                $insert = $db->getQuery(true)
                    ->insert($db->quoteName('#__m365calendar_event_categories'))
                    ->columns($db->quoteName(['event_id', 'category']));

                foreach ($chunk as [$eventId, $category]) {
                    $insert->values(implode(',', $insert->bindArray([$eventId, $category], [ParameterType::INTEGER, ParameterType::STRING])));
                }

                $db->setQuery($insert)->execute();
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            throw new \RuntimeException('Storing events failed.', 0, $e);
        }

        return \count($events);
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function recordStatus(int $calendarId, string $status, string $message, ?int $count, \DateTimeImmutable $now): void
    {
        $this->ensureSyncRow($calendarId);

        $db     = $this->db;
        $nowStr = self::sql($now);
        $query  = $db->getQuery(true)
            ->update($db->quoteName('#__m365calendar_sync'))
            ->set($db->quoteName('last_run') . ' = :now')
            ->set($db->quoteName('status') . ' = :status')
            ->set($db->quoteName('message') . ' = :message')
            ->where($db->quoteName('calendar_id') . ' = :cid')
            ->bind(':now', $nowStr)
            ->bind(':status', $status)
            ->bind(':message', $message)
            ->bind(':cid', $calendarId, ParameterType::INTEGER);

        if ($status === 'ok') {
            $query->set($db->quoteName('last_success') . ' = :success')->bind(':success', $nowStr);
        }

        if ($count !== null) {
            $query->set($db->quoteName('event_count') . ' = :count')->bind(':count', $count, ParameterType::INTEGER);
        }

        $db->setQuery($query)->execute();
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function deleteOrphanFeedTokens(): int
    {
        $db  = $this->db;
        $sub = $db->getQuery(true)
            ->select($db->quoteName('u.id'))
            ->from($db->quoteName('#__users', 'u'));

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__m365calendar_feed_tokens'))
            ->where($db->quoteName('user_id') . ' NOT IN (' . $sub . ')');

        $db->setQuery($query)->execute();

        return $db->getAffectedRows();
    }

    /**
     * Insert an event.
     *
     * @param   integer          $calendarId  Calendar ID.
     * @param   NormalizedEvent  $event       Event.
     * @param   string           $synced      Sync time.
     *
     * @return  integer  New ID.
     *
     * @since   0.1.0
     */
    private function insertEvent(int $calendarId, NormalizedEvent $event, string $synced): int
    {
        $db     = $this->db;
        $values = $this->values($event, $synced);
        $query  = $db->getQuery(true)
            ->insert($db->quoteName('#__m365calendar_events'))
            ->columns($db->quoteName(array_merge(['calendar_id'], array_keys($values))));

        $types = [ParameterType::INTEGER];

        foreach ($values as $value) {
            $types[] = \is_int($value) ? ParameterType::INTEGER : (\is_null($value) ? ParameterType::NULL : ParameterType::STRING);
        }

        $query->values(implode(',', $query->bindArray(array_merge([$calendarId], array_values($values)), $types)));
        $db->setQuery($query)->execute();

        return (int) $db->insertid();
    }

    /**
     * Update an event.
     *
     * @param   integer          $id      Local ID.
     * @param   NormalizedEvent  $event   Event.
     * @param   string           $synced  Sync time.
     *
     * @return  integer  The ID.
     *
     * @since   0.1.0
     */
    private function updateEvent(int $id, NormalizedEvent $event, string $synced): int
    {
        $db      = $this->db;
        $boundId = $id;
        $query   = $db->getQuery(true)
            ->update($db->quoteName('#__m365calendar_events'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $boundId, ParameterType::INTEGER);

        $i = 0;

        foreach ($this->values($event, $synced) as $column => $value) {
            $param = ':v' . $i++;
            $query->set($db->quoteName($column) . ' = ' . $param);

            if ($value === null) {
                $query->bind($param, $value, ParameterType::NULL);
            } elseif (\is_int($value)) {
                $query->bind($param, $value, ParameterType::INTEGER);
            } else {
                $query->bind($param, $value);
            }

            unset($value);
        }

        $db->setQuery($query)->execute();

        return $id;
    }

    /**
     * Column values of an event.
     *
     * @param   NormalizedEvent  $event   Event.
     * @param   string           $synced  Sync time.
     *
     * @return  array<string, string|int|null>
     *
     * @since   0.1.0
     */
    private function values(NormalizedEvent $event, string $synced): array
    {
        return [
            'graph_id'          => $event->graphId,
            'uid_hash'          => $event->uidHash,
            'subject'           => $event->subject,
            'location'          => $event->location,
            'start_utc'         => self::sql($event->startUtc),
            'end_utc'           => self::sql($event->endUtc),
            'all_day'           => $event->allDay ? 1 : 0,
            'show_as'           => $event->showAs,
            'categories'        => json_encode($event->categories, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'web_link'          => $event->webLink,
            'last_modified_utc' => $event->lastModified !== null ? self::sql($event->lastModified) : null,
            'synced_utc'        => $synced,
        ];
    }

    /**
     * Delete rows by IDs in chunks.
     *
     * @param   string     $table   Table.
     * @param   string     $column  ID column.
     * @param   integer[]  $ids     IDs.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function deleteByIds(string $table, string $column, array $ids): void
    {
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName($table))
                ->whereIn($this->db->quoteName($column), $chunk);

            $this->db->setQuery($query)->execute();
        }
    }

    /**
     * Make sure a status row exists.
     *
     * @param   integer  $calendarId  Calendar ID (0 = lock row).
     *
     * @return  void
     *
     * @since   0.1.0
     */
    private function ensureSyncRow(int $calendarId): void
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__m365calendar_sync'))
            ->where($db->quoteName('calendar_id') . ' = :cid')
            ->bind(':cid', $calendarId, ParameterType::INTEGER);

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            return;
        }

        $empty = '';
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__m365calendar_sync'))
            ->columns($db->quoteName(['calendar_id', 'status', 'message', 'lock_owner']))
            ->values(':cid, :status, :message, :owner')
            ->bind(':cid', $calendarId, ParameterType::INTEGER)
            ->bind(':status', $empty)
            ->bind(':message', $empty)
            ->bind(':owner', $empty);

        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException) {
            // Created concurrently.
        }
    }

    /**
     * SQL datetime in UTC.
     *
     * @param   \DateTimeImmutable  $date  Date.
     *
     * @return  string
     *
     * @since   0.1.0
     */
    public static function sql(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
