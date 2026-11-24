<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Unit\Filter;

use Joomla\Component\M365calendar\Administrator\Filter\EffectiveFilter;
use Joomla\Component\M365calendar\Administrator\Filter\FilterResolver;
use Joomla\Component\M365calendar\Administrator\Filter\PresetFilter;
use Joomla\Component\M365calendar\Administrator\Filter\VisitorFilter;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Preset (menu item) AND visitor filters: visitors can narrow, never widen.
 */
final class FilterResolverTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-24 12:00:00', new \DateTimeZone('UTC'));
    }

    /**
     * @param  int[]  $accessible
     */
    private function resolve(PresetFilter $preset, VisitorFilter $visitor, array $accessible = [1, 2, 3, 4]): EffectiveFilter
    {
        $utc = new \DateTimeZone('UTC');

        return FilterResolver::resolve($preset, $visitor, $accessible, $this->now, $utc, $utc);
    }

    public function testVisitorCannotSelectCalendarOutsidePreset(): void
    {
        $filter = $this->resolve(new PresetFilter([1, 2]), new VisitorFilter(calendarId: 3));

        $this->assertSame([1, 2], $filter->calendarIds, 'The out-of-preset value is ignored.');
    }

    public function testVisitorCanNarrowToCalendarInsidePreset(): void
    {
        $filter = $this->resolve(new PresetFilter([1, 2]), new VisitorFilter(calendarId: 2));

        $this->assertSame([2], $filter->calendarIds);
    }

    public function testPresetIsLimitedToAccessibleCalendars(): void
    {
        $filter = $this->resolve(new PresetFilter([1, 2]), new VisitorFilter(calendarId: 2), [1]);

        $this->assertSame([1], $filter->calendarIds, 'Calendar 2 is not accessible, so the visitor value is ignored.');
    }

    public function testInaccessiblePresetMatchesNothing(): void
    {
        $filter = $this->resolve(new PresetFilter([5]), new VisitorFilter(), [1, 2]);

        $this->assertSame([], $filter->calendarIds);
        $this->assertTrue($filter->isImpossible());
    }

    public function testEmptyPresetMeansAllAccessible(): void
    {
        $this->assertSame([1, 2, 3, 4], $this->resolve(new PresetFilter(), new VisitorFilter())->calendarIds);
        $this->assertSame([1, 2, 3, 4], $this->resolve(new PresetFilter(), new VisitorFilter(calendarId: 99))->calendarIds);
    }

    public function testVisitorCannotSelectCategoryOutsidePreset(): void
    {
        $preset = new PresetFilter(categories: ['Board']);

        $this->assertSame(['Board'], $this->resolve($preset, new VisitorFilter(category: 'Party'))->categories);
        $this->assertSame(['Board'], $this->resolve($preset, new VisitorFilter(category: 'Board'))->categories);
        $this->assertNull($this->resolve(new PresetFilter(), new VisitorFilter())->categories);
        $this->assertSame(['Party'], $this->resolve(new PresetFilter(), new VisitorFilter(category: 'Party'))->categories);
    }

    public function testTextFiltersAreCombined(): void
    {
        $filter = $this->resolve(
            new PresetFilter(location: 'Berlin', keyword: 'Board'),
            new VisitorFilter(search: 'budget', location: 'Room 1')
        );

        $this->assertSame(['Board'], $filter->subjectTerms);
        $this->assertSame(['Berlin', 'Room 1'], $filter->locationTerms);
        $this->assertSame('budget', $filter->search);
    }

    public function testVisitorDatesCannotWidenPresetWindow(): void
    {
        $filter = $this->resolve(
            new PresetFilter(dateMode: 'next', days: 30),
            new VisitorFilter(dateFrom: '2000-01-01', dateTo: '2099-12-31')
        );

        $this->assertEquals($this->now, $filter->endAfter);
        $this->assertEquals($this->now->modify('+30 days'), $filter->startBefore);
    }

    public function testVisitorDatesNarrowPresetWindow(): void
    {
        $filter = $this->resolve(
            new PresetFilter(dateMode: 'fixed', dateFrom: '2026-01-01', dateTo: '2026-12-31'),
            new VisitorFilter(dateFrom: '2026-06-01', dateTo: '2026-06-30')
        );

        $this->assertSame('2026-06-01 00:00:00', $filter->endAfter?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-01 00:00:00', $filter->startBefore?->format('Y-m-d H:i:s'));
    }

    public function testFixedPresetUsesSiteTimeZone(): void
    {
        $filter = FilterResolver::resolve(
            new PresetFilter(dateMode: 'fixed', dateFrom: '2026-12-01'),
            new VisitorFilter(),
            [1],
            $this->now,
            new \DateTimeZone('Europe/Berlin'),
            new \DateTimeZone('UTC')
        );

        $this->assertSame('2026-11-30 23:00:00', $filter->endAfter?->format('Y-m-d H:i:s'));
    }

    public function testUpcomingPresetCannotBeTurnedIntoPast(): void
    {
        $filter = $this->resolve(new PresetFilter(timeRange: 'upcoming'), new VisitorFilter(timeRange: 'past'));

        $this->assertEquals($this->now, $filter->endAfter);
        $this->assertEquals($this->now, $filter->endBefore);
        $this->assertTrue($filter->isImpossible(), 'upcoming AND past matches nothing, never everything.');
    }

    public function testEventTypeConflictMatchesNothing(): void
    {
        $this->assertTrue($this->resolve(new PresetFilter(eventType: 'allday'), new VisitorFilter(eventType: 'timed'))->isImpossible());
        $this->assertTrue($this->resolve(new PresetFilter(eventType: 'allday'), new VisitorFilter(eventType: 'allday'))->allDay);
        $this->assertFalse($this->resolve(new PresetFilter(), new VisitorFilter(eventType: 'timed'))->allDay);
    }

    public function testMonthRangeIntersectsPreset(): void
    {
        $filter = $this->resolve(new PresetFilter(dateMode: 'next', days: 10), new VisitorFilter())
            ->withRange(new \DateTimeImmutable('2026-11-01', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-12-31', new \DateTimeZone('UTC')));

        $this->assertEquals($this->now, $filter->endAfter);
        $this->assertEquals($this->now->modify('+10 days'), $filter->startBefore);
    }

    public function testWithCalendarsOnlyIntersects(): void
    {
        $filter = $this->resolve(new PresetFilter([1, 2]), new VisitorFilter())->withCalendars([2, 3]);

        $this->assertSame([2], $filter->calendarIds);
    }

    public function testPresetComesFromMenuParamsOnly(): void
    {
        $preset = PresetFilter::fromParams(new Registry([
            'preset_calendars'  => ['2', '1', '1', 'x'],
            'preset_categories' => ['Board', ' ', 'Board'],
            'preset_time_range' => 'everything',
            'preset_date_mode'  => 'fixed',
            'preset_date_from'  => '2026-02-30',
            'preset_date_to'    => '2026-12-31 00:00:00',
        ]));

        $this->assertSame([2, 1], $preset->calendarIds);
        $this->assertSame(['Board'], $preset->categories);
        $this->assertSame('all', $preset->timeRange, 'Unknown values fall back to the default.');
        $this->assertSame('', $preset->dateFrom, 'Invalid dates are dropped.');
        $this->assertSame('2026-12-31', $preset->dateTo);
    }

    public function testVisitorInputIsNormalized(): void
    {
        $visitor = VisitorFilter::fromArray([
            'search'   => "  x\x00y  ",
            'calendar' => '-5',
            'type'     => 'DROP TABLE',
            'from'     => '2026-13-01',
        ]);

        $this->assertSame('x y', $visitor->search);
        $this->assertSame(0, $visitor->calendarId);
        $this->assertSame('all', $visitor->eventType);
        $this->assertSame('', $visitor->dateFrom);
    }
}
