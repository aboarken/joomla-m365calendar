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
 * Storage of hashed feed tokens (#__m365calendar_feed_tokens).
 *
 * @since  0.1.0
 */
interface FeedTokenStoreInterface
{
    /**
     * Find a token row by hash.
     *
     * @param   string  $hash  SHA-256 hex.
     *
     * @return  array{user_id: int, token_hash: string}|null
     *
     * @since   0.1.0
     */
    public function findByHash(string $hash): ?array;

    /**
     * Whether a user has a token.
     *
     * @param   integer  $userId  User ID.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function hasToken(int $userId): bool;

    /**
     * Store (replace) the token hash of a user. The old hash stops working.
     *
     * @param   integer             $userId  User ID.
     * @param   string              $hash    SHA-256 hex.
     * @param   \DateTimeImmutable  $now     Creation time.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function replace(int $userId, string $hash, \DateTimeImmutable $now): void;

    /**
     * Update last_used.
     *
     * @param   string              $hash  Token hash.
     * @param   \DateTimeImmutable  $now   Time.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function touch(string $hash, \DateTimeImmutable $now): void;

    /**
     * Delete the token(s) of a user.
     *
     * @param   integer  $userId  User ID.
     *
     * @return  void
     *
     * @since   0.1.0
     */
    public function deleteForUser(int $userId): void;
}
