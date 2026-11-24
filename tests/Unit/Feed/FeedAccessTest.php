<?php

/**
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace M365Calendar\Tests\Unit\Feed;

use Joomla\Component\M365calendar\Administrator\Feed\FeedAccessResolver;
use Joomla\Component\M365calendar\Administrator\Feed\FeedCalendarResolver;
use Joomla\Component\M365calendar\Administrator\Feed\FeedTokenService;
use M365Calendar\Tests\Support\InMemoryTokenStore;
use PHPUnit\Framework\TestCase;

/**
 * Which calendars a feed may contain, and personal tokens.
 */
final class FeedAccessTest extends TestCase
{
    private const PUBLIC     = 1;
    private const REGISTERED = 2;
    private const SPECIAL    = 3;

    /**
     * @return object[]
     */
    private static function calendars(): array
    {
        return [
            (object) ['id' => 1, 'access' => self::PUBLIC, 'published' => 1, 'allow_feeds' => 1],
            (object) ['id' => 2, 'access' => self::REGISTERED, 'published' => 1, 'allow_feeds' => 1],
            (object) ['id' => 3, 'access' => self::PUBLIC, 'published' => 1, 'allow_feeds' => 0],
            (object) ['id' => 4, 'access' => self::SPECIAL, 'published' => 1, 'allow_feeds' => 1],
            (object) ['id' => 5, 'access' => self::PUBLIC, 'published' => 0, 'allow_feeds' => 1],
        ];
    }

    public function testPublicFeedExcludesNonPublicCalendars(): void
    {
        $ids = FeedCalendarResolver::resolve(self::calendars(), 'public', [self::PUBLIC], [self::PUBLIC, self::REGISTERED, self::SPECIAL], []);

        $this->assertSame([1], $ids);
    }

    public function testPublicFeedIgnoresPresetCalendarsThatAreNotPublic(): void
    {
        $ids = FeedCalendarResolver::resolve(self::calendars(), 'public', [self::PUBLIC], [], [1, 2, 4]);

        $this->assertSame([1], $ids);
    }

    public function testCalendarsWithoutAllowInFeedsNeverAppear(): void
    {
        foreach (['public', 'personal'] as $mode) {
            $ids = FeedCalendarResolver::resolve(self::calendars(), $mode, [self::PUBLIC], [self::PUBLIC, self::REGISTERED, self::SPECIAL], [3]);
            $this->assertNotContains(3, $ids, $mode);
            $this->assertSame([], $ids, $mode . ': preset [3] only contains a non-feed calendar.');
        }

        $all = FeedCalendarResolver::resolve(self::calendars(), 'personal', [self::PUBLIC], [self::PUBLIC, self::REGISTERED, self::SPECIAL], []);
        $this->assertSame([1, 2, 4], $all);
    }

    public function testPersonalFeedUsesUserViewLevels(): void
    {
        $ids = FeedCalendarResolver::resolve(self::calendars(), 'personal', [self::PUBLIC], [self::PUBLIC, self::REGISTERED], []);

        $this->assertSame([1, 2], $ids);
    }

    public function testFeedsOffReturnsNothing(): void
    {
        $this->assertSame([], FeedCalendarResolver::resolve(self::calendars(), 'none', [self::PUBLIC], [self::PUBLIC], []));
    }

    public function testTokenIsStoredOnlyAsHash(): void
    {
        $store   = new InMemoryTokenStore();
        $service = new FeedTokenService($store);
        $token   = $service->issue(42);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token, '32 random bytes, hex encoded.');
        $this->assertSame(hash('sha256', $token), $store->rows[42]['hash']);
        $this->assertStringNotContainsString($token, serialize($store->rows));
        $this->assertSame(42, $service->resolveUserId($token));
        $this->assertNotNull($store->rows[42]['last_used']);
    }

    public function testResetInvalidatesOldToken(): void
    {
        $store   = new InMemoryTokenStore();
        $service = new FeedTokenService($store);
        $old     = $service->issue(7);
        $new     = $service->reset(7);

        $this->assertNotSame($old, $new);
        $this->assertNull($service->resolveUserId($old));
        $this->assertSame(7, $service->resolveUserId($new));
        $this->assertCount(1, $store->rows);
    }

    public function testMalformedOrUnknownTokensAreRejected(): void
    {
        $service = new FeedTokenService(new InMemoryTokenStore());
        $service->issue(1);

        $this->assertNull($service->resolveUserId(''));
        $this->assertNull($service->resolveUserId(str_repeat('z', 64)));
        $this->assertNull($service->resolveUserId(str_repeat('a', 64)));
        $this->assertNull($service->resolveUserId("' OR 1=1 --"));
    }

    public function testGuestsCannotHaveTokens(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FeedTokenService(new InMemoryTokenStore()))->issue(0);
    }

    public function testBlockedUserGets404(): void
    {
        $service  = new FeedTokenService(new InMemoryTokenStore());
        $token    = $service->issue(9);
        $resolver = new FeedAccessResolver($service, static fn (int $id): array => ['blocked' => true, 'viewLevels' => [1, 2]]);

        $this->assertNull($resolver->resolve($token), 'null makes the controller answer 404.');
    }

    public function testDeletedUserGets404AndTokenIsRemoved(): void
    {
        $store    = new InMemoryTokenStore();
        $service  = new FeedTokenService($store);
        $token    = $service->issue(9);
        $resolver = new FeedAccessResolver($service, static fn (int $id): ?array => null);

        $this->assertNull($resolver->resolve($token));
        $this->assertSame([], $store->rows);
    }

    public function testActiveUserGetsOnlyViewLevels(): void
    {
        $service  = new FeedTokenService(new InMemoryTokenStore());
        $token    = $service->issue(9);
        $resolver = new FeedAccessResolver($service, static fn (int $id): array => ['blocked' => false, 'viewLevels' => ['1', '2']]);

        $principal = $resolver->resolve($token);

        $this->assertNotNull($principal);
        $this->assertSame(9, $principal->userId);
        $this->assertSame([1, 2], $principal->viewLevels);
    }
}
