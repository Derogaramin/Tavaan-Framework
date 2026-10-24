<?php

declare(strict_types=1);

namespace Modules\Forum\Tests\Unit;

use Modules\Forum\Domain\Services\FloodControl;
use Tavaan\Testing\TestCase;

/**
 * تست FloodControl — DOC-094 §6/§7 (Anti-spam: flood control).
 */
final class FloodControlTest extends TestCase
{
    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-24 12:00:00', new \DateTimeZone('UTC'));
    }

    public function testFirstPostAlwaysAllowed(): void
    {
        $flood = new FloodControl(['min_seconds' => 30, 'window_seconds' => 300, 'max_posts' => 10]);

        $decision = $flood->decide([], $this->now());

        self::assertTrue($decision['allowed']);
    }

    public function testMinIntervalBlocksRapidSecondPost(): void
    {
        $flood = new FloodControl(['min_seconds' => 30, 'window_seconds' => 300, 'max_posts' => 10]);
        $now = $this->now();

        // پست ۱۰ ثانیه پیش
        $decision = $flood->decide([$now->sub(new \DateInterval('PT10S'))], $now);

        self::assertFalse($decision['allowed']);
        self::assertSame('min_interval', $decision['reason']);
        self::assertSame(20, $decision['retry_after']);
    }

    public function testMinIntervalAllowsAfterElapsed(): void
    {
        $flood = new FloodControl(['min_seconds' => 30, 'window_seconds' => 300, 'max_posts' => 10]);
        $now = $this->now();

        $decision = $flood->decide([$now->sub(new \DateInterval('PT31S'))], $now);

        self::assertTrue($decision['allowed']);
    }

    public function testWindowLimitBlocksTooManyPosts(): void
    {
        $flood = new FloodControl(['min_seconds' => 0, 'window_seconds' => 300, 'max_posts' => 3]);
        $now = $this->now();

        $recent = [
            $now->sub(new \DateInterval('PT200S')),
            $now->sub(new \DateInterval('PT150S')),
            $now->sub(new \DateInterval('PT100S')),
        ];

        $decision = $flood->decide($recent, $now);

        self::assertFalse($decision['allowed']);
        self::assertSame('window_limit', $decision['reason']);
        self::assertGreaterThan(0, $decision['retry_after']);
    }

    public function testPostsOutsideWindowAreIgnored(): void
    {
        $flood = new FloodControl(['min_seconds' => 0, 'window_seconds' => 300, 'max_posts' => 3]);
        $now = $this->now();

        $recent = [
            $now->sub(new \DateInterval('PT900S')),   // بیرون پنجره
            $now->sub(new \DateInterval('PT800S')),   // بیرون پنجره
            $now->sub(new \DateInterval('PT400S')),   // بیرون پنجره
        ];

        self::assertTrue($flood->decide($recent, $now)['allowed']);
    }

    public function testDisabledAllowsEverything(): void
    {
        $flood = new FloodControl(['enabled' => false]);

        self::assertTrue($flood->decide([$this->now()], $this->now())['allowed']);
        self::assertFalse($flood->enabled());
    }
}
