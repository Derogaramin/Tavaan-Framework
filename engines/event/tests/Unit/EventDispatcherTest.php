<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event\Tests\Unit;

use Tavaan\Core\Contracts\Events\StoppableEventInterface;
use Tavaan\Engine\Container\Container;
use Tavaan\Engine\Event\EventDispatcher;
use Tavaan\Engine\Event\Exceptions\EventNotAllowlistedException;
use Tavaan\Testing\TestCase;
use Tests\Fixtures\Event\FailingListener;
use Tests\Fixtures\Event\ListenerRecorder;
use Tests\Fixtures\Event\OrderPaid;
use Tests\Fixtures\Event\OrderSubscriber;
use Tests\Fixtures\Event\PaidInterface;
use Tests\Fixtures\Event\QueueableEvent;
use Tests\Fixtures\Event\StoppableSample;

/**
 * تست موتور Event — سناریوهای DOC-033 §12 (AC-EVT).
 */
final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $events;

    protected function setUp(): void
    {
        ListenerRecorder::reset();
        $this->events = new EventDispatcher(new Container());
    }

    /** T-01: ترتیب Priority قطعی (100/0/50) */
    public function testPriorityOrderIsDeterministic(): void
    {
        $this->events->listen(OrderPaid::class, static fn () => ListenerRecorder::record('low'), priority: 0);
        $this->events->listen(OrderPaid::class, static fn () => ListenerRecorder::record('high'), priority: 100);
        $this->events->listen(OrderPaid::class, static fn () => ListenerRecorder::record('mid'), priority: 50);

        $this->events->dispatch(new OrderPaid());
        $this->assertSame(['high', 'mid', 'low'], ListenerRecorder::$log);
    }

    /** اولویت برابر → ترتیب ثبت (seq) */
    public function testEqualPriorityUsesRegistrationOrder(): void
    {
        $this->events->listen(OrderPaid::class, static fn () => ListenerRecorder::record('first'));
        $this->events->listen(OrderPaid::class, static fn () => ListenerRecorder::record('second'));
        $this->events->dispatch(new OrderPaid());
        $this->assertSame(['first', 'second'], ListenerRecorder::$log);
    }

    /** T-02: Wildcard فقط مطابقی‌ها */
    public function testWildcardMatching(): void
    {
        $this->events->listen('shop.order.*', static fn () => ListenerRecorder::record('wild'));
        $this->events->listen('shop.*.paid', static fn () => ListenerRecorder::record('wild2'));
        $this->events->listen('blog.post.*', static fn () => ListenerRecorder::record('nope'));

        $this->events->dispatch('shop.order.paid');
        $this->assertSame(['wild', 'wild2'], ListenerRecorder::$log);
    }

    /** T-03: until() در اولین پاسخ غیر-null متوقف می‌شود */
    public function testUntilStopsAtFirstNonNull(): void
    {
        $this->events->listen('check', static function () { ListenerRecorder::record('a'); return null; });
        $this->events->listen('check', static function () { ListenerRecorder::record('b'); return 'stop'; }, priority: -1);
        $this->events->listen('check', static function () { ListenerRecorder::record('c'); return 'never'; }, priority: -2);

        $result = $this->events->until('check');
        $this->assertSame('stop', $result);
        $this->assertSame(['a', 'b'], ListenerRecorder::$log);
    }

    /** T-04: stopPropagation جلوی بقیه را می‌گیرد */
    public function testStopPropagation(): void
    {
        $this->events->listen(StoppableSample::class, static function (StoppableSample $e) {
            ListenerRecorder::record('first');
            $e->stopPropagation();
        }, priority: 10);
        $this->events->listen(StoppableSample::class, static fn () => ListenerRecorder::record('second'));

        $event = new StoppableSample();
        $this->events->dispatch($event);
        $this->assertInstanceOf(StoppableEventInterface::class, $event);
        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame(['first'], ListenerRecorder::$log);
    }

    /** T-05: Listener با خطا → ادامه (stop_on_error=false) */
    public function testFailingListenerDoesNotStopChain(): void
    {
        $this->events->listen(OrderPaid::class, FailingListener::class, priority: 10);
        $this->events->listen(OrderPaid::class, static fn () => ListenerRecorder::record('after-failure'));

        $this->events->dispatch(new OrderPaid());
        $this->assertSame(['failing:before', 'after-failure'], ListenerRecorder::$log);
    }

    /** T-06: ShouldQueue → بدون اجرای همزمان */
    public function testQueueableEventIsPushedNotDispatched(): void
    {
        $this->events->listen(QueueableEvent::class, static fn () => ListenerRecorder::record('should-not-run'));
        $this->events->dispatch(new QueueableEvent());

        $this->assertSame([], ListenerRecorder::$log);
        $this->assertContains(QueueableEvent::class, $this->events->pushedEvents());
    }

    /** T-12: fake + assertDispatched */
    public function testFakeRecordsAndAsserts(): void
    {
        $this->events->listen(OrderPaid::class, static fn () => ListenerRecorder::record('real'));
        $this->events->fake();

        $this->events->dispatch(new OrderPaid('01JABC', 500));
        $this->events->dispatch(new OrderPaid('02', 100));
        $this->assertSame([], ListenerRecorder::$log, 'listeners must not run while faking');

        $this->events->assertDispatched(OrderPaid::class);
        $this->events->assertDispatched(OrderPaid::class, static fn (OrderPaid $e): bool => $e->orderId === '01JABC');
        $this->events->assertDispatchedTimes(OrderPaid::class, 2);
        $this->events->assertNotDispatched('shop.order.paid');
    }

    /** T-14: Subscriber با چند رویداد */
    public function testSubscriberRegistersMultipleEvents(): void
    {
        $this->events->subscribe(new OrderSubscriber());
        $this->events->dispatch(new OrderPaid('77'));
        $this->events->dispatch('shop.order.canceled');

        $this->assertSame(['subscriber:paid:77', 'subscriber:canceled'], ListenerRecorder::$log);
    }

    /** T-15: Hierarchical — Listener روی Interface */
    public function testHierarchyInterfaceListeners(): void
    {
        $this->events->listen(PaidInterface::class, static fn () => ListenerRecorder::record('via-interface'));
        $this->events->dispatch(new OrderPaid());
        $this->assertSame(['via-interface'], ListenerRecorder::$log);
    }

    /** رویداد رشته‌ای با payload لیست → آرگومان‌ها باز می‌شوند */
    public function testStringEventWithListPayload(): void
    {
        $this->events->listen('shop.order.paid', static function (string $event, mixed $payload) {
            ListenerRecorder::record($event . ':' . \json_encode($payload));
        });
        $this->events->dispatch('shop.order.paid', ['orderId' => 9]);
        $this->assertSame(['shop.order.paid:{"orderId":9}'], ListenerRecorder::$log);
    }

    /** hasListeners/forget */
    public function testHasListenersAndForget(): void
    {
        $this->events->listen('a.b.c', static fn () => null);
        $this->assertTrue($this->events->hasListeners('a.b.c'));
        $this->events->forget('a.b.c');
        $this->assertFalse($this->events->hasListeners('a.b.c'));
    }

    /** allowlist — امنیت dispatch رشته‌ای */
    public function testAllowlistBlocksUnknownStringEvents(): void
    {
        $this->events->allowDispatch(['shop.*', 'auth.login']);
        $this->events->listen('shop.order.paid', static fn () => ListenerRecorder::record('ok'));
        $this->events->dispatch('shop.order.paid');
        $this->assertSame(['ok'], ListenerRecorder::$log);

        $this->expectException(EventNotAllowlistedException::class);
        $this->events->dispatch('evil.payload');
    }

    /** max_listeners → exception */
    public function testMaxListenersExceeded(): void
    {
        $small = new EventDispatcher(new Container(), ['max_listeners' => 2]);
        $small->listen('x', static fn () => null);
        $small->listen('y', static fn () => null);
        $this->expectException(\Tavaan\Engine\Event\Exceptions\MaxListenersExceededException::class);
        $small->listen('z', static fn () => null);
    }
}
