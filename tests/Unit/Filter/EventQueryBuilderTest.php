<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Unit\Filter;

use Joomla\Component\M365calendar\Administrator\Filter\EffectiveFilter;
use Joomla\Component\M365calendar\Administrator\Filter\EventQueryBuilder;
use Joomla\Component\M365calendar\Administrator\Filter\FilterResolver;
use Joomla\Component\M365calendar\Administrator\Filter\PresetFilter;
use Joomla\Component\M365calendar\Administrator\Filter\VisitorFilter;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\Mysqli\MysqliQuery;
use PHPUnit\Framework\TestCase;

/**
 * SQL built from an EffectiveFilter: every value bound, no concatenation.
 */
final class EventQueryBuilderTest extends TestCase
{
    private DatabaseInterface $db;

    protected function setUp(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('quoteName')->willReturnCallback(static function ($name, $as = null) {
            $quote = static fn (string $n): string => implode('.', array_map(static fn (string $p): string => '`' . $p . '`', explode('.', $n)));

            if (\is_array($name)) {
                return array_map($quote, $name);
            }

            return $quote($name) . ($as !== null ? ' AS ' . $quote($as) : '');
        });
        $db->method('getPrefix')->willReturn('jos_');

        $this->db = $db;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function build(EffectiveFilter $filter): array
    {
        $query = new MysqliQuery($this->db);
        $query->select('*')->from('#__m365calendar_events AS e');

        (new EventQueryBuilder($this->db))->apply($query, $filter);

        $values = [];

        foreach ($query->getBounded() as $key => $bound) {
            $values[$key] = $bound->value;
        }

        return [(string) $query, $values];
    }

    public function testCalendarsAreBound(): void
    {
        [$sql, $values] = $this->build(new EffectiveFilter([1, 2]));

        $this->assertMatchesRegularExpression('/`e`\.`calendar_id` IN \(:preparedArray\d+,:preparedArray\d+\)/', $sql);
        $this->assertSame([1, 2], array_values($values));
    }

    public function testVisitorInjectionAttemptIsOnlyAValue(): void
    {
        $evil   = "x' OR 1=1 --";
        $filter = FilterResolver::resolve(
            new PresetFilter([1]),
            new VisitorFilter(search: $evil, category: $evil, location: $evil),
            [1],
            new \DateTimeImmutable('2026-11-24', new \DateTimeZone('UTC')),
            new \DateTimeZone('UTC'),
            new \DateTimeZone('UTC')
        );

        [$sql, $values] = $this->build($filter);

        $this->assertStringNotContainsString('OR 1=1', $sql);
        $this->assertContains("%x' or 1=1 --%", $values);
        $this->assertContains($evil, $values, 'Category value bound as-is.');
    }

    public function testPresetAndVisitorConditionsAreAnded(): void
    {
        $filter = FilterResolver::resolve(
            new PresetFilter([1, 2], ['Board'], 'Berlin', 'Meeting', 'upcoming', 'none', 0, '', '', 'timed'),
            new VisitorFilter(search: 'budget', calendarId: 2, location: 'Room'),
            [1, 2, 3],
            new \DateTimeImmutable('2026-11-24 12:00:00', new \DateTimeZone('UTC')),
            new \DateTimeZone('UTC'),
            new \DateTimeZone('UTC')
        );

        [$sql, $values] = $this->build($filter);

        $where = substr($sql, (int) strpos($sql, 'WHERE'));
        $this->assertStringNotContainsString(') OR (', $where);
        $this->assertStringContainsString('LOWER(`e`.`subject`) LIKE', $where);
        $this->assertStringContainsString('LOWER(`e`.`location`) LIKE', $where);
        $this->assertStringContainsString('EXISTS (SELECT 1 FROM `#__m365calendar_event_categories` AS `ec`', $where);
        $this->assertStringContainsString('`e`.`end_utc` >', $where);
        $this->assertStringContainsString('`e`.`all_day` = 0', $where);
        // The only OR is inside the parenthesised "subject OR location" search.
        $this->assertSame(1, substr_count($where, ' OR '));
        $this->assertMatchesRegularExpression('/\(LOWER\(`e`\.`subject`\) LIKE :\w+ OR LOWER\(`e`\.`location`\) LIKE :\w+\)/', $where);
        $this->assertContains(2, $values);
        $this->assertNotContains(1, $values, 'The visitor narrowed to calendar 2 only.');
        $this->assertContains('%meeting%', $values);
        $this->assertContains('%berlin%', $values);
        $this->assertContains('%room%', $values);
        $this->assertContains('2026-11-24 12:00:00', $values);
    }

    public function testImpossibleFilterMatchesNothing(): void
    {
        [$sql, $values] = $this->build(new EffectiveFilter([]));

        $this->assertStringContainsString('1 = 0', $sql);
        $this->assertSame([], $values);
    }

    public function testLikeWildcardsAreEscaped(): void
    {
        $this->assertSame('%100\\%\\_off%', EventQueryBuilder::likePattern('100%_OFF'));
    }
}
