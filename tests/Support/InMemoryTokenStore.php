<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Support;

use Joomla\Component\M365calendar\Administrator\Feed\FeedTokenStoreInterface;

/**
 * Token storage in memory: user_id => hash.
 */
final class InMemoryTokenStore implements FeedTokenStoreInterface
{
    /** @var array<int, array{hash: string, created: \DateTimeImmutable, last_used: ?\DateTimeImmutable}> */
    public array $rows = [];

    public function findByHash(string $hash): ?array
    {
        foreach ($this->rows as $userId => $row) {
            if ($row['hash'] === $hash) {
                return ['user_id' => $userId, 'token_hash' => $row['hash']];
            }
        }

        return null;
    }

    public function hasToken(int $userId): bool
    {
        return isset($this->rows[$userId]);
    }

    public function replace(int $userId, string $hash, \DateTimeImmutable $now): void
    {
        $this->rows[$userId] = ['hash' => $hash, 'created' => $now, 'last_used' => null];
    }

    public function touch(string $hash, \DateTimeImmutable $now): void
    {
        foreach ($this->rows as $userId => $row) {
            if ($row['hash'] === $hash) {
                $this->rows[$userId]['last_used'] = $now;
            }
        }
    }

    public function deleteForUser(int $userId): void
    {
        unset($this->rows[$userId]);
    }
}
