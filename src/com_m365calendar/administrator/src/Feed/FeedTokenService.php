<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Feed;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Personal feed tokens.
 *
 * A token is 32 random bytes (64 hex characters). It is returned once, to be
 * placed in the feed URL, and only its SHA-256 hash is stored. A token only
 * identifies whose feed to build: it never logs anyone in.
 *
 * @since  0.1.0
 */
final class FeedTokenService
{
    /**
     * Clock.
     *
     * @var    \Closure(): \DateTimeImmutable
     * @since  0.1.0
     */
    private \Closure $clock;

    /**
     * Constructor.
     *
     * @param   FeedTokenStoreInterface  $store  Storage.
     * @param   \Closure|null            $clock  Clock for tests.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly FeedTokenStoreInterface $store, ?\Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Hash a token for storage and lookup.
     *
     * @param   string  $token  Plain token.
     *
     * @return  string  SHA-256 hex.
     *
     * @since   0.1.0
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Whether a string has the shape of a token.
     *
     * @param   string  $token  Candidate.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public static function isWellFormed(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Create a token for a user, replacing any existing one.
     *
     * @param   integer  $userId  User ID (> 0).
     *
     * @return  string  The plain token; show it once and never store it.
     *
     * @since   0.1.0
     */
    public function issue(int $userId): string
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Guests cannot have feed tokens.');
        }

        $token = bin2hex(random_bytes(32));
        $this->store->replace($userId, self::hash($token), ($this->clock)());

        return $token;
    }

    /**
     * Reset: replace the user's token. The old link stops working immediately.
     *
     * @param   integer  $userId  User ID.
     *
     * @return  string  The new plain token.
     *
     * @since   0.1.0
     */
    public function reset(int $userId): string
    {
        return $this->issue($userId);
    }

    /**
     * Whether a user already has a token.
     *
     * @param   integer  $userId  User ID.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function hasToken(int $userId): bool
    {
        return $userId > 0 && $this->store->hasToken($userId);
    }

    /**
     * Revoke all tokens of a user.
     *
     * @param   integer  $userId  User ID.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function revoke(int $userId): void
    {
        $this->store->deleteForUser($userId);
    }

    /**
     * Resolve a token to a user ID.
     *
     * @param   string  $token  Plain token from the feed URL.
     *
     * @return  integer|null  User ID, or null when unknown.
     *
     * @since   0.1.0
     */
    public function resolveUserId(string $token): ?int
    {
        if (!self::isWellFormed($token)) {
            return null;
        }

        $hash = self::hash($token);
        $row  = $this->store->findByHash($hash);

        if ($row === null || !hash_equals($row['token_hash'], $hash)) {
            return null;
        }

        $this->store->touch($hash, ($this->clock)());

        return $row['user_id'];
    }
}
