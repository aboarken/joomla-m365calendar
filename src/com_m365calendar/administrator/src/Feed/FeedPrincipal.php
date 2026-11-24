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
 * The user a personal feed is built for.
 *
 * @since  0.1.0
 */
final class FeedPrincipal
{
    /**
     * Constructor.
     *
     * @param   integer    $userId      User ID.
     * @param   integer[]  $viewLevels  Authorised view levels.
     *
     * @since   0.1.0
     */
    public function __construct(public readonly int $userId, public readonly array $viewLevels)
    {
    }
}
