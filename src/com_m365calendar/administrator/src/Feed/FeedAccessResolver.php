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
 * Resolves a personal feed token to the user it belongs to.
 *
 * Returns null (the controller answers 404) for unknown tokens, deleted users
 * and blocked users. A token for a deleted user is removed.
 *
 * @since  1.0.0
 */
final class FeedAccessResolver
{
    /**
     * Loads a user: fn (int $id): ?array{blocked: bool, viewLevels: int[]}; null when the user does not exist.
     *
     * @var    \Closure
     * @since  1.0.0
     */
    private \Closure $userLoader;

    /**
     * Constructor.
     *
     * @param   FeedTokenService  $tokens      Token service.
     * @param   \Closure          $userLoader  User loader.
     *
     * @since   1.0.0
     */
    public function __construct(private readonly FeedTokenService $tokens, \Closure $userLoader)
    {
        $this->userLoader = $userLoader;
    }

    /**
     * Resolve a token.
     *
     * @param   string  $token  Token from the URL.
     *
     * @return  FeedPrincipal|null
     *
     * @since   1.0.0
     */
    public function resolve(string $token): ?FeedPrincipal
    {
        $userId = $this->tokens->resolveUserId($token);

        if ($userId === null) {
            return null;
        }

        $user = ($this->userLoader)($userId);

        if ($user === null) {
            $this->tokens->revoke($userId);

            return null;
        }

        if (!empty($user['blocked'])) {
            return null;
        }

        return new FeedPrincipal($userId, array_values(array_map('intval', (array) ($user['viewLevels'] ?? []))));
    }
}
