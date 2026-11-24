<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_m365calendar
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\Component\M365calendar\Administrator\Filter;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Applies an EffectiveFilter to an events query. Every value is bound.
 *
 * @since  1.0.0
 */
final class EventQueryBuilder
{
    /**
     * Constructor.
     *
     * @param   DatabaseInterface  $db  Database (for quoting names).
     *
     * @since   1.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Add the WHERE conditions.
     *
     * @param   QueryInterface   $query   Query selecting from #__m365calendar_events.
     * @param   EffectiveFilter  $filter  Filter.
     * @param   string           $alias   Events table alias.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function apply(QueryInterface $query, EffectiveFilter $filter, string $alias = 'e'): void
    {
        $db = $this->db;
        $e  = static fn (string $column): string => $db->quoteName($alias . '.' . $column);

        if ($filter->isImpossible()) {
            $query->where('1 = 0');

            return;
        }

        $query->whereIn($e('calendar_id'), array_map('intval', $filter->calendarIds));

        if ($filter->categories !== null) {
            $placeholders = $query->bindArray(array_values($filter->categories), ParameterType::STRING);
            $query->where(
                'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__m365calendar_event_categories', 'ec')
                . ' WHERE ' . $db->quoteName('ec.event_id') . ' = ' . $e('id')
                . ' AND ' . $db->quoteName('ec.category') . ' IN (' . implode(',', $placeholders) . '))'
            );
        }

        foreach ($filter->subjectTerms as $term) {
            $query->where('LOWER(' . $e('subject') . ') LIKE ' . $this->bindOne($query, self::likePattern($term)));
        }

        foreach ($filter->locationTerms as $term) {
            $query->where('LOWER(' . $e('location') . ') LIKE ' . $this->bindOne($query, self::likePattern($term)));
        }

        if ($filter->search !== '') {
            $query->where(
                '(LOWER(' . $e('subject') . ') LIKE ' . $this->bindOne($query, self::likePattern($filter->search))
                . ' OR LOWER(' . $e('location') . ') LIKE ' . $this->bindOne($query, self::likePattern($filter->search)) . ')'
            );
        }

        if ($filter->endAfter !== null) {
            $query->where($e('end_utc') . ' > ' . $this->bindOne($query, self::sql($filter->endAfter)));
        }

        if ($filter->startBefore !== null) {
            $query->where($e('start_utc') . ' < ' . $this->bindOne($query, self::sql($filter->startBefore)));
        }

        if ($filter->endBefore !== null) {
            $query->where($e('end_utc') . ' <= ' . $this->bindOne($query, self::sql($filter->endBefore)));
        }

        if ($filter->allDay !== null) {
            $query->where($e('all_day') . ' = ' . ($filter->allDay ? '1' : '0'));
        }
    }

    /**
     * LIKE pattern for "contains", with wildcards in the term escaped.
     *
     * @param   string  $term  Term.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function likePattern(string $term): string
    {
        return '%' . addcslashes(mb_strtolower($term), '\\%_') . '%';
    }

    /**
     * Bind one string value and return its placeholder.
     *
     * bindArray() keeps its own copy of the value, so repeated calls never
     * overwrite each other's bound variables.
     *
     * @param   QueryInterface  $query  Query.
     * @param   string          $value  Value.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private function bindOne(QueryInterface $query, string $value): string
    {
        return $query->bindArray([$value], ParameterType::STRING)[0];
    }

    /**
     * SQL datetime (UTC).
     *
     * @param   \DateTimeImmutable  $date  Date.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private static function sql(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
