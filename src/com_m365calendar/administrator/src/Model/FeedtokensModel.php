<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Model;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List of users with a personal feed token (no token data is shown).
 *
 * @since  1.0.0
 */
class FeedtokensModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array                     $config   An optional associative array of configuration settings.
     * @param   MVCFactoryInterface|null  $factory  The factory.
     *
     * @since   1.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 't.id', 'name', 'u.name', 'username', 'u.username', 'created', 't.created', 'last_used', 't.last_used'];
        }

        parent::__construct($config, $factory);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function populateState($ordering = 't.created', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * Build the list query.
     *
     * @return  QueryInterface
     *
     * @since   1.0.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(
                $db->quoteName(
                    ['t.id', 't.user_id', 't.created', 't.last_used', 'u.name', 'u.username', 'u.block'],
                    ['id', 'user_id', 'created', 'last_used', 'name', 'username', 'block']
                )
            )
            ->from($db->quoteName('#__m365calendar_feed_tokens', 't'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('t.user_id'));

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('t.user_id') . ' = :uid')->bind(':uid', $id, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $search) . '%';
                $query->where('(' . $db->quoteName('u.name') . ' LIKE :s1 OR ' . $db->quoteName('u.username') . ' LIKE :s2)')
                    ->bind(':s1', $search)
                    ->bind(':s2', $search);
            }
        }

        $orderCol  = (string) $this->state->get('list.ordering', 't.created');
        $orderDirn = strtoupper((string) $this->state->get('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $query->order($db->escape($orderCol) . ' ' . $orderDirn);

        return $query;
    }
}
