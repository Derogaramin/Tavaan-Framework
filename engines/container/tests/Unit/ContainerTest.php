<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container\Tests\Unit;

use Tavaan\Engine\Container\Container;
use Tavaan\Engine\Container\Exceptions\AmbiguousUnionException;
use Tavaan\Engine\Container\Exceptions\BindingResolutionException;
use Tavaan\Engine\Container\Exceptions\CircularDependencyException;
use Tavaan\Engine\Container\Exceptions\ScopeNotActiveException;
use Tavaan\Engine\Container\Exceptions\UntypedParameterException;
use Tavaan\Testing\TestCase;
use Tests\Fixtures\Container\BaseExtendable;
use Tests\Fixtures\Container\CycA;
use Tests\Fixtures\Container\CycB;
use Tests\Fixtures\Container\DecoratedExtendable;
use Tests\Fixtures\Container\Deep1;
use Tests\Fixtures\Container\EnumService;
use Tests\Fixtures\Container\ExtendableInterface;
use Tests\Fixtures\Container\FactoryService;
use Tests\Fixtures\Container\GreeterInterface;
use Tests\Fixtures\Container\LogTransport;
use Tests\Fixtures\Container\MailerService;
use Tests\Fixtures\Container\NotifierService;
use Tests\Fixtures\Container\PersianGreeter;
use Tests\Fixtures\Container\PromotedService;
use Tests\Fixtures\Container\ScopedService;
use Tests\Fixtures\Container\SimpleClock;
use Tests\Fixtures\Container\SmtpTransport;
use Tests\Fixtures\Container\StaticTarget;
use Tests\Fixtures\Container\StatusEnum;
use Tests\Fixtures\Container\StringDepService;
use Tests\Fixtures\Container\TransportInterface;
use Tests\Fixtures\Container\UnionA;
use Tests\Fixtures\Container\UnionB;
use Tests\Fixtures\Container\UnionImpl;
use Tests\Fixtures\Container\UnionNullableService;
use Tests\Fixtures\Container\UnionService;
use Tests\Fixtures\Container\UntypedService;
use Tests\Fixtures\Container\VariadicService;
use Tests\Fixtures\Container\WelcomeService;

/**
 * تست موتور Container — سناریوهای DOC-031 §12 (AC-CON-01).
 */
final class ContainerTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    /** T-01: Singleton دو بار make → یک نمونه */
    public function testSingletonReturnsSameInstance(): void
    {
        $this->c->singleton(GreeterInterface::class, PersianGreeter::class);
        $this->assertSame($this->c->make(GreeterInterface::class), $this->c->make(GreeterInterface::class));
    }

    /** T-02: Transient دو بار → دو نمونه */
    public function testTransientReturnsNewInstances(): void
    {
        $this->c->bind(SimpleClock::class);
        $this->assertNotSame($this->c->make(SimpleClock::class), $this->c->make(SimpleClock::class));
    }

    /** T-03: Auto-wire ۵ سطح تو‌در‌تو */
    public function testAutoWireFiveLevelsDeep(): void
    {
        $deep = $this->c->make(Deep1::class);
        $this->assertInstanceOf(Deep1::class, $deep);
        $this->assertSame('deep5', $deep->d->d->d->d->value());
    }

    /** Auto-wire کامل با Interface binding */
    public function testAutoWireWithInterfaceBinding(): void
    {
        $this->c->singleton(GreeterInterface::class, PersianGreeter::class);
        $service = $this->c->make(WelcomeService::class);
        $this->assertSame('سلام تَوان! (12:00)', $service->run('تَوان'));
    }

    /** T-04: چرخه A→B→A → CircularDependencyException با مسیر */
    public function testCircularDependencyDetectedWithPath(): void
    {
        try {
            $this->c->make(CycA::class);
            self::fail('Expected CircularDependencyException');
        } catch (CircularDependencyException $e) {
            $this->assertSame('container.circular_dependency', $e->messageId);
            $this->assertSame([CycA::class, CycB::class, CycA::class], $e->context['path']);
        }
    }

    /** T-05: Union با یک کاندیدای bound → resolve */
    public function testUnionSingleCandidateResolves(): void
    {
        $this->c->bind(UnionA::class, UnionImpl::class);
        $service = $this->c->make(UnionService::class);
        $this->assertSame('union-impl', $service->dep->tag());
    }

    /** Union مبهم → AmbiguousUnionException (قطعیت) */
    public function testAmbiguousUnionThrows(): void
    {
        $this->c->bind(UnionA::class, UnionImpl::class);
        $this->c->bind(UnionB::class, UnionImpl::class);
        $this->expectException(AmbiguousUnionException::class);
        $this->expectExceptionMessageId('container.ambiguous_union');
        $this->c->make(UnionService::class);
    }

    /** Union با null و بدون binding → null */
    public function testUnionNullableFallsBackToNull(): void
    {
        $service = $this->c->make(UnionNullableService::class);
        $this->assertNull($service->dep);
    }

    /** T-06: پارامتر بدون type در حالت strict → UntypedParameterException */
    public function testUntypedParameterThrowsInStrictMode(): void
    {
        $this->expectException(UntypedParameterException::class);
        $this->c->make(UntypedService::class);
    }

    /** پارامتر builtin بدون default → BindingResolutionException */
    public function testUnresolvableBuiltinParameterThrows(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessageId('container.unresolvable_parameter');
        $this->c->make(StringDepService::class);
    }

    /** override صریح پارامتر */
    public function testExplicitParameterOverride(): void
    {
        $service = $this->c->make(StringDepService::class, ['name' => 'توان']);
        $this->assertSame('توان', $service->name);
    }

    /** T-07: Contextual برای دو مصرف‌کننده → پیاده‌سازی متفاوت */
    public function testContextualBindingPerConsumer(): void
    {
        $this->c->when(MailerService::class)->needs(TransportInterface::class)->give(SmtpTransport::class);
        $this->c->when(NotifierService::class)->needs(TransportInterface::class)->give(LogTransport::class);

        $this->assertSame('smtp', $this->c->make(MailerService::class)->transport->name());
        $this->assertSame('log', $this->c->make(NotifierService::class)->transport->name());
    }

    /** T-08: tagged() lazy — بدون make تا iterate */
    public function testTaggedIsLazy(): void
    {
        $this->c->bind(SmtpTransport::class);
        $this->c->bind(LogTransport::class);
        $this->c->tag([SmtpTransport::class, LogTransport::class], 'channels');

        $before = $this->c->stats()->makeCalls;
        $generator = $this->c->tagged('channels');
        $this->assertSame($before, $this->c->stats()->makeCalls, 'tagged() must not resolve eagerly');

        $resolved = \iterator_to_array($generator);
        $this->assertCount(2, $resolved);
        $this->assertGreaterThan($before, $this->c->stats()->makeCalls);
    }

    /** T-09: extend() دو بار → زنجیرهٔ decorator با ترتیب صحیح */
    public function testExtendChainsDecorators(): void
    {
        $this->c->singleton(ExtendableInterface::class, BaseExtendable::class);
        $this->c->extend(ExtendableInterface::class, static fn (ExtendableInterface $s, Container $c): ExtendableInterface => new DecoratedExtendable($s, 'first'));
        $this->c->extend(ExtendableInterface::class, static fn (ExtendableInterface $s, Container $c): ExtendableInterface => new DecoratedExtendable($s, 'second'));

        $this->assertSame('second(first(base))', $this->c->make(ExtendableInterface::class)->name());
    }

    /** T-10: flushScoped → singleton باقی، scoped حذف */
    public function testScopedInstancesAreFlushed(): void
    {
        $this->c->singleton(SimpleClock::class);
        $this->c->scoped(ScopedService::class);

        $clock1 = $this->c->make(SimpleClock::class);
        $this->c->beginScope('request');
        $scoped1 = $this->c->make(ScopedService::class);
        $scoped2 = $this->c->make(ScopedService::class);
        $this->assertSame($scoped1, $scoped2, 'same instance within one scope');
        $this->c->endScope('request');

        $this->c->flushScoped();
        $this->assertSame($clock1, $this->c->make(SimpleClock::class), 'singleton survives flushScoped');

        $this->c->beginScope('request');
        $this->assertNotSame($scoped1, $this->c->make(ScopedService::class), 'scoped rebuilt after flush');
        $this->c->endScope('request');
    }

    /** scoped بدون Scope فعال → ScopeNotActiveException */
    public function testScopedWithoutActiveScopeThrows(): void
    {
        $this->c->scoped(ScopedService::class);
        $this->expectException(ScopeNotActiveException::class);
        $this->c->make(ScopedService::class);
    }

    /** T-11: Variadic بدون آرگومان → بدون خطا */
    public function testVariadicParameterWithoutArguments(): void
    {
        $service = $this->c->make(VariadicService::class);
        $this->assertSame('base', $service->name);
        $this->assertSame(0, $service->count());

        $service2 = $this->c->make(VariadicService::class, ['name' => 'n', 'items' => ['a', 'b']]);
        $this->assertSame(2, $service2->count());
    }

    /** T-12: Promoted readonly → تزریق درست */
    public function testPromotedReadonlyPropertyInjection(): void
    {
        $this->c->singleton(GreeterInterface::class, PersianGreeter::class);
        $service = $this->c->make(PromotedService::class);
        $this->assertInstanceOf(PersianGreeter::class, $service->greeter);
        $this->assertSame(5, $service->count());
    }

    /** T-13: Enum با default → مقدار default */
    public function testEnumParameterWithDefault(): void
    {
        $service = $this->c->make(EnumService::class);
        $this->assertSame(StatusEnum::Active, $service->status);
    }

    /** T-14: call() روی static/instance/@syntax/invokable */
    public function testCallMethodInjection(): void
    {
        $this->c->singleton(GreeterInterface::class, PersianGreeter::class);

        $this->assertSame('static:hi', $this->c->call(StaticTarget::class . '::greetStatic', ['name' => 'hi']));
        $this->assertSame('static:tvn', $this->c->call([new StaticTarget(), 'greetStatic'], ['name' => 'tvn']));
        $this->assertSame('سلام 42!', $this->c->call([new StaticTarget(), 'show'], ['id' => 42]));
        $this->assertSame('سلام 7!', $this->c->call(StaticTarget::class . '@show', ['id' => 7]));
        $this->assertSame('سلام invoked!', $this->c->call(new StaticTarget()));
    }

    /** T-15: ReflectionCache dump/load → نتیجه یکسان */
    public function testReflectionCacheDumpLoadRoundtrip(): void
    {
        $this->c->singleton(GreeterInterface::class, PersianGreeter::class);
        $this->c->make(WelcomeService::class);

        $cache = $this->c->reflectionCache();
        $file = \sys_get_temp_dir() . '/tvn-reflection-test-' . \getmypid() . '.php';
        $cache->dump($file);

        $fresh = new \Tavaan\Engine\Container\ReflectionCache();
        $fresh->load($file);
        $original = $cache->signatureOf(WelcomeService::class);
        $loaded = $fresh->signatureOf(WelcomeService::class);

        $this->assertSame($original->toArray(), $loaded->toArray());
        @\unlink($file);
    }

    /** instance + alias */
    public function testInstanceAndAlias(): void
    {
        $clock = new SimpleClock();
        $this->c->instance('clock', $clock);
        $this->c->alias('clock', 'time');
        $this->assertSame($clock, $this->c->make('time'));
        $this->assertTrue($this->c->bound('clock'));
        $this->assertTrue($this->c->resolved('clock'));
    }

    /** factory → هر make جدید */
    public function testFactoryLifetimeCreatesEveryTime(): void
    {
        $this->c->factory(FactoryService::class, static fn (): FactoryService => new FactoryService());
        $this->assertNotSame($this->c->make(FactoryService::class), $this->c->make(FactoryService::class));
    }

    /** abstract ناامن → BindingResolutionException::unsafe */
    public function testUnsafeAbstractRejected(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessageId('container.unsafe_abstract');
        $this->c->make("evil\0class");
    }

    /** Interface بدون binding → notFound */
    public function testUnboundInterfaceThrowsNotFound(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->c->make(GreeterInterface::class);
    }

    /** PSR-11-like: get/has */
    public function testPsrLikeGetHas(): void
    {
        $this->c->singleton(SimpleClock::class);
        $this->assertTrue($this->c->has(SimpleClock::class));
        $this->assertInstanceOf(SimpleClock::class, $this->c->get(SimpleClock::class));
    }

    /** forgetInstance */
    public function testForgetInstanceRebuilds(): void
    {
        $this->c->singleton(SimpleClock::class);
        $first = $this->c->make(SimpleClock::class);
        $this->c->forgetInstance(SimpleClock::class);
        $this->assertNotSame($first, $this->c->make(SimpleClock::class));
    }
}
