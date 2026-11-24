<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Support;

use Joomla\Component\M365calendar\Administrator\Service\CalendarRef;
use Joomla\Component\M365calendar\Administrator\Service\EventStoreInterface;
use Joomla\Component\M365calendar\Administrator\Service\NormalizedEvent;

/**
 * EventStoreInterface in memory.
 */
final class InMemoryEventStore implements EventStoreInterface
{
    /** @var CalendarRef[] */
    public array $calendars = [];

    /** @var array<int, NormalizedEvent[]> */
    public array $events = [];

    /** @var array<int, array{status: string, message: string, count: ?int}> */
    public array $status = [];

    public ?string $lockOwner = null;

    public ?\DateTimeImmutable $lockedUntil = null;

    public int $orphanCleanups = 0;

    public function acquireLock(string $owner, \DateTimeImmutable $now, \DateTimeImmutable $until): bool
    {
        if ($this->lockOwner !== null && $this->lockOwner !== $owner && $this->lockedUntil !== null && $this->lockedUntil > $now) {
            return false;
        }

        $this->lockOwner   = $owner;
        $this->lockedUntil = $until;

        return true;
    }

    public function releaseLock(string $owner): void
    {
        if ($this->lockOwner === $owner) {
            $this->lockOwner   = null;
            $this->lockedUntil = null;
        }
    }

    public function getPublishedCalendars(): array
    {
        return $this->calendars;
    }

    public function replaceEvents(int $calendarId, array $events, \DateTimeImmutable $now): int
    {
        $this->events[$calendarId] = $events;

        return \count($events);
    }

    public function recordStatus(int $calendarId, string $status, string $message, ?int $count, \DateTimeImmutable $now): void
    {
        $this->status[$calendarId] = ['status' => $status, 'message' => $message, 'count' => $count];
    }

    public function deleteOrphanFeedTokens(): int
    {
        $this->orphanCleanups++;

        return 0;
    }
}
