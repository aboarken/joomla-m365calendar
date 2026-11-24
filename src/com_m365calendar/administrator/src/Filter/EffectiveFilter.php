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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The combined (AND) filter that is turned into SQL.
 *
 * Time constraints are expressed as three bounds that only ever get tighter:
 * end_utc > $endAfter, start_utc < $startBefore, end_utc <= $endBefore.
 *
 * @since  0.1.0
 */
final class EffectiveFilter
{
    /**
     * Constructor.
     *
     * @param   integer[]                $calendarIds    Calendars (never empty unless $impossible).
     * @param   string[]|null            $categories     Required categories (any of), null = no restriction.
     * @param   string[]                 $subjectTerms   Each must be contained in the subject.
     * @param   string[]                 $locationTerms  Each must be contained in the location.
     * @param   string                   $search         Must be contained in subject or location.
     * @param   \DateTimeImmutable|null  $endAfter       Lower bound for end_utc (exclusive).
     * @param   \DateTimeImmutable|null  $startBefore    Upper bound for start_utc (exclusive).
     * @param   \DateTimeImmutable|null  $endBefore      Upper bound for end_utc (inclusive).
     * @param   boolean|null             $allDay         true = all-day only, false = timed only, null = both.
     * @param   boolean                  $impossible     Nothing can match.
     *
     * @since   0.1.0
     */
    public function __construct(
        public readonly array $calendarIds,
        public readonly ?array $categories = null,
        public readonly array $subjectTerms = [],
        public readonly array $locationTerms = [],
        public readonly string $search = '',
        public readonly ?\DateTimeImmutable $endAfter = null,
        public readonly ?\DateTimeImmutable $startBefore = null,
        public readonly ?\DateTimeImmutable $endBefore = null,
        public readonly ?bool $allDay = null,
        public readonly bool $impossible = false
    ) {
    }

    /**
     * Copy restricted to a time range [from, to) (overlap), e.g. a month or the feed window.
     *
     * @param   \DateTimeImmutable  $from  From.
     * @param   \DateTimeImmutable  $to    To.
     *
     * @return  self
     *
     * @since   0.1.0
     */
    public function withRange(\DateTimeImmutable $from, \DateTimeImmutable $to): self
    {
        return $this->copy(
            endAfter: FilterResolver::later($this->endAfter, $from),
            startBefore: FilterResolver::earlier($this->startBefore, $to)
        );
    }

    /**
     * Copy restricted to a calendar subset (intersection).
     *
     * @param   integer[]  $calendarIds  Calendars.
     *
     * @return  self
     *
     * @since   0.1.0
     */
    public function withCalendars(array $calendarIds): self
    {
        return $this->copy(calendarIds: array_values(array_intersect($this->calendarIds, array_map('intval', $calendarIds))));
    }

    /**
     * Whether the filter can match anything.
     *
     * @return  boolean
     *
     * @since   0.1.0
     */
    public function isImpossible(): bool
    {
        if ($this->impossible || $this->calendarIds === [] || $this->categories === []) {
            return true;
        }

        if ($this->endAfter !== null && $this->endBefore !== null && $this->endBefore <= $this->endAfter) {
            return true;
        }

        return false;
    }

    /**
     * Copy with changes.
     *
     * @param   mixed  ...$changes  Named changes (property name => new value).
     *
     * @return  self
     *
     * @since   0.1.0
     */
    private function copy(mixed ...$changes): self
    {
        $values = get_object_vars($this);

        foreach ($changes as $name => $value) {
            $values[$name] = $value;
        }

        return new self(...$values);
    }
}
