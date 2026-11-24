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

use Joomla\CMS\Cache\Controller\OutputController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Basic fixed-window rate limiter using Joomla's Cache API.
 *
 * Keys are hashed, so no IP address or token is stored in clear text.
 *
 * @since  0.1.0
 */
final class RateLimiter
{
    /**
     * Constructor.
     *
     * @param   OutputController  $cache   Cache controller (caching forced on, lifetime = window).
     * @param   integer           $window  Window length in seconds.
     *
     * @since   0.1.0
     */
    public function __construct(private readonly OutputController $cache, private readonly int $window = 60)
    {
    }

    /**
     * Count a hit and check the limit.
     *
     * @param   string   $key    Raw key (IP address, token hash ...).
     * @param   integer  $limit  Maximum hits per window.
     *
     * @return  boolean  True when the request is allowed.
     *
     * @since   0.1.0
     */
    public function hit(string $key, int $limit): bool
    {
        $bucket = (int) floor(time() / $this->window);
        $id     = hash('sha256', 'm365calendar|' . $key . '|' . $bucket);
        $count  = (int) $this->cache->get($id);

        if ($count >= $limit) {
            return false;
        }

        $this->cache->store((string) ($count + 1), $id);

        return true;
    }

    /**
     * Seconds until the current window ends.
     *
     * @return  integer
     *
     * @since   0.1.0
     */
    public function retryAfter(): int
    {
        return $this->window - (time() % $this->window);
    }
}
