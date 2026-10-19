<?php

declare(strict_types=1);

namespace Tavaan\Testing\Mock;

use Tavaan\Testing\AssertionFailed;

/**
 * تَوان | Tavaan — Testing
 * Mock — نقطهٔ ورود ساخت Mock صفر‌وابستگی (بدون Mockery/eval — DOC-084E §4).
 *
 * ```php
 * $mock = Mock::of(StoreInterface::class)
 *     ->shouldReceive('get')->with('key')->andReturn('value')
 *     ->shouldReceive('put')->once()
 *     ->getMock();
 * ```
 *
 * چرخهٔ عمر: `TestCase::runBare` در آغاز `reset()` و در پایان `verifyAll()`
 * را صدا می‌زند → انتظاراتِ شمارشی (`once/times/never`) خودکار بررسی می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class Mock
{
    /** @var list<MockHandler> */
    private static array $handlers = [];

    private function __construct()
    {
    }

    /** @param class-string $target */
    public static function of(string $target): MockBuilder
    {
        return new MockBuilder($target);
    }

    /** ثبت Handler برای verify خودکار (توسط MockBuilder::getMock). */
    public static function register(MockHandler $handler): void
    {
        self::$handlers[] = $handler;
    }

    /**
     * بررسی همهٔ انتظارات ثبت‌شده؛ در صورت نقص، یک AssertionFailed تجمعی.
     *
     * @throws AssertionFailed
     */
    public static function verifyAll(): void
    {
        $problems = [];
        foreach (self::$handlers as $handler) {
            try {
                $handler->verify();
            } catch (AssertionFailed $e) {
                $problems[] = $e->getMessage();
            }
        }

        if ($problems !== []) {
            throw AssertionFailed::for('Mock expectations not met:' . \PHP_EOL . '  - ' . \implode(\PHP_EOL . '  - ', $problems));
        }
    }

    /** پاک‌سازی کامل state (آغاز/پایان هر تست). */
    public static function reset(): void
    {
        self::$handlers = [];
        MockHandler::resetStatics();
    }
}
