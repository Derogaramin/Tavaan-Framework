<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Tests\Unit;

use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\Hook;
use Tavaan\Testing\TestCase;

/**
 * تست Hook — declare/listen/fire + DoS همکاری‌کننده (DOC-079 §6/§9 — T-05).
 */
final class HookTest extends TestCase
{
    protected function setUp(): void
    {
        Hook::flush();
        Hook::setLimits(5000, null);
    }

    protected function tearDown(): void
    {
        Hook::flush();
        Hook::setLimits(5000, null);
    }

    public function testHostListener_AlwaysAllowed(): void
    {
        Hook::listen('any.hook', static fn (): string => 'ok');

        self::assertTrue(Hook::has('any.hook'));
        self::assertSame(['ok'], Hook::fire('any.hook'));
    }

    public function testPluginListener_RequiresDeclaredHook(): void
    {
        Hook::declare('demo.greet');
        Hook::listen('demo.greet', static fn (): string => 'hi', 'acme.demo');

        self::assertSame(['hi'], Hook::fire('demo.greet'));
    }

    public function testPluginListener_UndeclaredHook_Rejected(): void
    {
        $this->expectException(PluginException::class);
        $this->expectExceptionMessageId('plugin.hook_not_declared');
        Hook::listen('secret.hook', static function (): void {}, 'acme.demo');
    }

    public function testFire_KeepsRegistrationOrder(): void
    {
        Hook::declare('h');
        Hook::listen('h', static fn (): string => 'first', 'p1');
        Hook::listen('h', static fn (): string => 'second');   // میزبان
        Hook::listen('h', static fn (): string => 'third', 'p2');

        self::assertSame(['first', 'second', 'third'], Hook::fire('h'));
    }

    public function testFire_ArgumentsPassedThrough(): void
    {
        Hook::declare('sum');
        Hook::listen('sum', static fn (int $a, int $b): int => $a + $b);

        self::assertSame([7], Hook::fire('sum', 3, 4));
    }

    public function testFire_ListenerThrowable_IsIsolated(): void
    {
        Hook::declare('h');
        Hook::listen('h', static function (): never {
            throw new \RuntimeException('plugin crashed');
        }, 'bad.plugin');
        Hook::listen('h', static fn (): string => 'still-runs');

        $results = Hook::fire('h');

        self::assertCount(2, $results);
        self::assertInstanceOf(\RuntimeException::class, $results[0]);
        self::assertSame('still-runs', $results[1]);   // میزبان نمی‌شکند
    }

    public function testFire_TimeoutViolation_StopsRemainingListeners(): void
    {
        Hook::setLimits(10, null);
        Hook::declare('slow');
        Hook::listen('slow', static function (): string {
            \usleep(30000);   // 30ms > 10ms

            return 'slow';
        }, 'slow.plugin');
        Hook::listen('slow', static fn (): string => 'never');

        $results = Hook::fire('slow');

        self::assertSame(['slow'], $results);   // listener دوم اجرا نشد
        self::assertCount(1, Hook::violations());
        self::assertStringContainsString('timeout', Hook::violations()[0]['reason']);
    }

    public function testFlushPlugin_RemovesOnlyItsListeners(): void
    {
        Hook::declare('h');
        Hook::listen('h', static fn (): string => 'p1', 'p1');
        Hook::listen('h', static fn (): string => 'host');
        Hook::listen('h', static fn (): string => 'p2', 'p2');

        Hook::flushPlugin('p1');
        $results = Hook::fire('h');

        self::assertSame(['host', 'p2'], $results);
    }

    public function testHas_FalseForUnknownHook(): void
    {
        self::assertFalse(Hook::has('nothing'));
        self::assertSame([], Hook::fire('nothing'));
    }

    public function testDeclared_ReflectsDeclarations(): void
    {
        self::assertFalse(Hook::declared('x'));
        Hook::declare('x', '');
        self::assertTrue(Hook::declared('x'));
    }
}
