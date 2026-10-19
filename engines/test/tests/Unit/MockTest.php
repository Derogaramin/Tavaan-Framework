<?php

declare(strict_types=1);

namespace Tavaan\Testing\Tests\Unit;

require_once __DIR__ . '/../Fixtures/MockFixtures.php';

use Tavaan\Testing\AssertionFailed;
use Tavaan\Testing\Mock\Mock;
use Tavaan\Testing\Mock\MockProxy;
use Tavaan\Testing\TestCase;
use Tavaan\Testing\Tests\Fixtures\CountableFixture;
use Tavaan\Testing\Tests\Fixtures\FinalServiceFixture;
use Tavaan\Testing\Tests\Fixtures\NamedFixture;
use Tavaan\Testing\Tests\Fixtures\ServiceFixture;
use Tavaan\Testing\Tests\Fixtures\ShapeFixture;
use Tavaan\Testing\Tests\Fixtures\ShapeFixtureInterface;
use Tavaan\Testing\Tests\Fixtures\StatusFixtureEnum;
use Tavaan\Testing\Tests\Fixtures\StoreFixtureInterface;

/**
 * تست سیستم Mock صفر‌وابستگی (بدون eval — DOC-084E §4/§4.1).
 */
final class MockTest extends TestCase
{
    // ─── رفتار پایه ───────────────────────────────────────────────────────

    public function testInterfaceMock_ReturnsExpectedValue(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('get')->with('key')->andReturn('value')
            ->getMock();

        self::assertSame('value', $mock->get('key'));
        self::assertInstanceOf(StoreFixtureInterface::class, $mock);
        self::assertInstanceOf(MockProxy::class, $mock);
    }

    public function testChainedExpectations_MatchDocsExample(): void
    {
        // زنجیرهٔ دقیق DOC-084E §4: shouldReceive→with→andReturn→shouldReceive→once→getMock
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('get')->with('key')->andReturn('value')
            ->shouldReceive('put')->once()->andReturn(true)
            ->getMock();

        self::assertSame('value', $mock->get('key'));
        self::assertTrue($mock->put('key', 'v'));   // once() → فراخوانی دوم = شکست
    }

    public function testTypedReturn_WithoutValue_EnforcesType(): void
    {
        // Proxy تایپ‌دار است: put(): bool بدون andReturn → null → TypeError واقعی
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('put')
            ->getMock();

        $this->expectException(\TypeError::class);
        $mock->put('k', 'v');
    }

    public function testDefaultReturn_IsNull(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('get')
            ->getMock();

        self::assertNull($mock->get('anything'));
    }

    public function testUnexpectedMethod_Throws(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('get')->andReturn('v')
            ->getMock();

        $this->assertFailsWith('unexpected call', static function () use ($mock): void {
            $mock->has('key');
        });
    }

    public function testArgumentMismatch_Throws(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('get')->with('a')->andReturn(1)
            ->getMock();

        self::assertSame(1, $mock->get('a'));
        $this->assertFailsWith('unmatched arguments', static function () use ($mock): void {
            $mock->get('b');
        });
    }

    public function testOnce_CalledTwice_Throws(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('forget')->once()
            ->getMock();

        $mock->forget('k');
        $this->assertFailsWith('more times than expected', static function () use ($mock): void {
            $mock->forget('k');
        });
    }

    public function testOnce_Unmet_FailsAtVerify(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('forget')->once()
            ->getMock();

        $this->assertFailsWith('expected 1 call(s) but received 0', static function (): void {
            Mock::verifyAll();
        });

        $mock->forget('k');   // ارضای انتظار → verify پایانی تست سبز می‌ماند
    }

    public function testTimes_ExactlyEnforced(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('has')->with('k')->times(2)->andReturn(true)
            ->getMock();

        self::assertTrue($mock->has('k'));
        self::assertTrue($mock->has('k'));
        $this->assertFailsWith('more times than expected', static function () use ($mock): void {
            $mock->has('k');
        });
    }

    public function testNever_AnyCallThrows(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('forget')->never()
            ->getMock();

        $this->assertFailsWith('more times than expected', static function () use ($mock): void {
            $mock->forget('k');
        });
    }

    public function testAndReturn_ConsecutiveValues(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('get')->andReturn('a', 'b')
            ->getMock();

        self::assertSame('a', $mock->get('k'));
        self::assertSame('b', $mock->get('k'));
        self::assertSame('b', $mock->get('k'));   // آخرین مقدار تکرار می‌شود
    }

    public function testAndReturnUsing_ReceivesArguments(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('put')->andReturnUsing(
                static fn (string $key, mixed $value, ?int $ttl = null): bool => $key === 'ok' && $value === 1 && $ttl === 5,
            )
            ->getMock();

        self::assertTrue($mock->put('ok', 1, 5));
        self::assertFalse($mock->put('no', 1));
    }

    public function testAndThrow_PropagatesThrowable(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('get')->andThrow(new \RuntimeException('boom'))
            ->getMock();

        $this->expectException(\RuntimeException::class);
        $mock->get('k');
    }

    public function testCalls_AreRecordedWithArguments(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('get')
            ->getMock();

        $mock->get('a');
        $mock->get('b');

        $calls = $mock->tvnMockHandler()->calls('get');
        self::assertCount(2, $calls);
        self::assertSame(['a'], $calls[0]['args']);
        self::assertSame(['b'], $calls[1]['args']);
        self::assertCount(2, $mock->tvnMockHandler()->calls());
    }

    public function testVoidMethod_CallIsRecorded(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('forget')
            ->getMock();

        $result = $mock->forget('k');
        self::assertNull($result);
        self::assertCount(1, $mock->tvnMockHandler()->calls('forget'));
    }

    public function testVariadicMethod_ForwardsAllArguments(): void
    {
        $mock = Mock::of(StoreFixtureInterface::class)
            ->shouldReceive('pullMany')->with('a', 'b', 'c')->andReturn(['a' => 1, 'b' => 2, 'c' => 3])
            ->getMock();

        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3], $mock->pullMany('a', 'b', 'c'));
    }

    // ─── کلاس‌ها (نه فقط اینترفیس) ────────────────────────────────────────

    public function testNonFinalClass_ParentConstructorNotCalled(): void
    {
        // constructor والد آرگومان واجب دارد و روی '' استثنا می‌دهد —
        // Proxy باید بدون صدا کردن والد ساخته شود.
        $mock = Mock::of(ServiceFixture::class)
            ->shouldReceive('getValue')->andReturn('mocked')
            ->getMock();

        self::assertInstanceOf(ServiceFixture::class, $mock);
        self::assertSame('mocked', $mock->getValue());
    }

    public function testFinalClass_IsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Mock::of(FinalServiceFixture::class)->shouldReceive('run')->getMock();
    }

    public function testUnknownMethod_IsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Mock::of(StoreFixtureInterface::class)->shouldReceive('nonexistent');
    }

    // ─── آشپزخانهٔ امضاها (ProxyGenerator) ────────────────────────────────

    public function testSignatureShapes_ProxyCompilesAndDispatches(): void
    {
        $mock = null;
        $builder = Mock::of(ShapeFixtureInterface::class);
        $builder
            ->shouldReceive('union')->with(5)->andReturn(10)
            ->shouldReceive('nullable')->andReturn(null)
            ->shouldReceive('withDefaults')->andReturn(7)
            ->shouldReceive('dnf')->andReturn(true)
            ->shouldReceive('withEnum')->andReturn('ok')
            ->shouldReceive('make')->andReturnUsing(static function () use (&$mock): object {
                return $mock;
            })
            ->shouldReceive('duplicate')->andReturnUsing(static function () use (&$mock): object {
                return $mock;
            })
            ->shouldReceive('nothing');

        $mock = $builder->getMock();

        self::assertSame(10, $mock->union(5));
        self::assertNull($mock->nullable());
        self::assertSame(7, $mock->withDefaults());                 // پیش‌فرض‌ها (PHP_INT_MAX/آرایه/null)
        self::assertTrue($mock->dnf(new ShapeFixture()));           // DNF: (A&B)|null
        self::assertTrue($mock->dnf(null));
        self::assertSame('ok', $mock->withEnum(StatusFixtureEnum::Inactive));
        self::assertSame($mock, $mock::make('id-1'));               // static + بازگشت static
        self::assertSame($mock, $mock->duplicate());                // بازگشت self
        $mock->nothing();                                           // void
        self::assertCount(1, $mock->tvnMockHandler()->calls('nothing'));
    }

    public function testDnfType_RejectsNonConformingObject(): void
    {
        $mock = Mock::of(ShapeFixtureInterface::class)
            ->shouldReceive('dnf')->andReturn(true)
            ->getMock();

        // فقط Countable (بدون NamedFixture) → TypeError سمت PHP (type-safety واقعی Proxy)
        $this->expectException(\TypeError::class);
        $mock->dnf(new \ArrayObject());
    }

    // ─── ابزار ────────────────────────────────────────────────────────────

    private function assertFailsWith(string $needle, \Closure $action): void
    {
        try {
            $action();
        } catch (AssertionFailed $e) {
            self::assertStringContainsString($needle, $e->getMessage());

            return;
        }

        self::fail('Expected AssertionFailed containing [' . $needle . '] was not thrown.');
    }
}
