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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Component\M365calendar\Administrator\Rule\M365calendaridRule;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Calendar table.
 *
 * @since  0.1.0
 */
class CalendarTable extends Table
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
        $this->typeAlias = 'com_m365calendar.calendar';

        parent::__construct('#__m365calendar_calendars', 'id', $db, $dispatcher);
    }

    /**
     * Validate the row before storing.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        $this->title             = trim((string) $this->title);
        $this->mailbox           = strtolower(trim((string) $this->mailbox));
        $this->graph_calendar_id = trim((string) $this->graph_calendar_id);
        $this->color             = strtolower(trim((string) $this->color));
        $this->allow_feeds       = (int) $this->allow_feeds === 1 ? 1 : 0;
        $this->access            = max(1, (int) $this->access);

        if ($this->title === '' || mb_strlen($this->title) > 255) {
            $this->setError(Text::_('COM_M365CALENDAR_CALENDAR_ERROR_TITLE'));

            return false;
        }

        if (\strlen($this->mailbox) > 320 || filter_var($this->mailbox, FILTER_VALIDATE_EMAIL) === false) {
            $this->setError(Text::_('COM_M365CALENDAR_CALENDAR_ERROR_MAILBOX'));

            return false;
        }

        // Graph calendar IDs are base64url-like strings.
        if ($this->graph_calendar_id !== '' && !preg_match(M365calendaridRule::PATTERN, $this->graph_calendar_id)) {
            $this->setError(Text::_('COM_M365CALENDAR_CALENDAR_ERROR_CALENDAR_ID'));

            return false;
        }

        if (!preg_match('/^#[0-9a-f]{6}$/', $this->color)) {
            $this->color = '#0d6efd';
        }

        return true;
    }

    /**
     * Store the row, setting created/modified data.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function store($updateNulls = true)
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();
        $uid  = $user ? (int) $user->id : 0;

        if (!(int) $this->id) {
            $this->created    = $date;
            $this->created_by = $uid;
        }

        $this->modified    = $date;
        $this->modified_by = $uid;

        if (empty($this->created)) {
            $this->created = $date;
        }

        return parent::store($updateNulls);
    }

    /**
     * Delete the calendar together with its events, categories and sync status.
     *
     * @param   mixed  $pk  Primary key.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function delete($pk = null)
    {
        $id = (int) ($pk ?? $this->id);

        if (!parent::delete($pk)) {
            return false;
        }

        $db  = $this->_db;
        $sub = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__m365calendar_events'))
            ->where($db->quoteName('calendar_id') . ' = :cid1');

        $queries = [
            $db->getQuery(true)
                ->delete($db->quoteName('#__m365calendar_event_categories'))
                ->where($db->quoteName('event_id') . ' IN (' . $sub . ')')
                ->bind(':cid1', $id, ParameterType::INTEGER),
            $db->getQuery(true)
                ->delete($db->quoteName('#__m365calendar_events'))
                ->where($db->quoteName('calendar_id') . ' = :cid')
                ->bind(':cid', $id, ParameterType::INTEGER),
            $db->getQuery(true)
                ->delete($db->quoteName('#__m365calendar_sync'))
                ->where($db->quoteName('calendar_id') . ' = :cid')
                ->bind(':cid', $id, ParameterType::INTEGER),
        ];

        foreach ($queries as $query) {
            $db->setQuery($query)->execute();
        }

        return true;
    }
}
