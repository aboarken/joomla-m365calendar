<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Table;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Feed token table (rows only hold a SHA-256 hash, never the token).
 *
 * @since  0.1.0
 */
class FeedtokenTable extends Table
{
    /**
     * Indicates that columns fully support the NULL value in the database.
     *
     * @var    boolean
     * @since  0.1.0
     */
    protected $_supportNullValue = true;

    /**
     * Constructor.
     *
     * @param   DatabaseDriver            $db          Database connector object.
     * @param   DispatcherInterface|null  $dispatcher  Event dispatcher for this table.
     *
     * @since   0.1.0
     */
    public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_m365calendar.feedtoken';

        parent::__construct('#__m365calendar_feed_tokens', 'id', $db, $dispatcher);
    }
}
