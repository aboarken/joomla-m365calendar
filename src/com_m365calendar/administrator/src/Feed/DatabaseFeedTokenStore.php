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

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Database implementation of the feed token storage.
 *
 * @since  0.1.0
 */
final class DatabaseFeedTokenStore implements FeedTokenStoreInterface
{
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
    public function findByHash(string $hash): ?array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['user_id', 'token_hash']))
            ->from($db->quoteName('#__m365calendar_feed_tokens'))
            ->where($db->quoteName('token_hash') . ' = :hash')
            ->bind(':hash', $hash);

        $row = $db->setQuery($query, 0, 1)->loadAssoc();

        return $row ? ['user_id' => (int) $row['user_id'], 'token_hash' => (string) $row['token_hash']] : null;
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function hasToken(int $userId): bool
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__m365calendar_feed_tokens'))
            ->where($db->quoteName('user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function replace(int $userId, string $hash, \DateTimeImmutable $now): void
    {
        $db      = $this->db;
        $created = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->transactionStart();

        try {
            $this->deleteForUser($userId);

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__m365calendar_feed_tokens'))
                ->columns($db->quoteName(['user_id', 'token_hash', 'created']))
                ->values(':uid, :hash, :created')
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':hash', $hash)
                ->bind(':created', $created);

            $db->setQuery($query)->execute();
            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();

            throw new \RuntimeException('Storing the feed token failed.', 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function touch(string $hash, \DateTimeImmutable $now): void
    {
        $db   = $this->db;
        $used = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__m365calendar_feed_tokens'))
            ->set($db->quoteName('last_used') . ' = :used')
            ->where($db->quoteName('token_hash') . ' = :hash')
            ->bind(':used', $used)
            ->bind(':hash', $hash);

        $db->setQuery($query)->execute();
    }

    /**
     * {@inheritDoc}
     *
     * @since   0.1.0
     */
    public function deleteForUser(int $userId): void
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__m365calendar_feed_tokens'))
            ->where($db->quoteName('user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }
}
