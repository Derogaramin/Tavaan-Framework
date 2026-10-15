<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Events\EventSubscriber;
use Tavaan\Core\Contracts\Events\ShouldQueue;
use Tavaan\Core\Contracts\Events\StoppableEventInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Engine\Event\Exceptions\EventNotAllowlistedException;
use Tavaan\Engine\Event\Exceptions\ListenerException;

/**
 * تَوان | Tavaan — Event
 * EventDispatcher — Event Bus همزمان با Priority/Wildcard/Subscriber/Fake.
 *
 * رفتار:
 * - ترتیب قطعی: priority DESC سپس seq ثبت ASC
 * - `until()`/halt: توقف در اولین پاسخ غیر-null
 * - StoppableEventInterface: توقف انتشار
 * - خطای Listener: با stop_on_error=false لاگ و ادامه؛ در غیر این صورت rethrow
 * - ShouldQueue: push به Queue (در صورت bind بودن)
 * - Allowlist امنیتی برای رویدادهای رشته‌ای (DOC-033 §9)
 *
 * Outbox/Inbox در فاز M3 با موتور database فعال می‌شود (soft dependency).
 *
 * @api 1.0
 * @since 1.0.0
 * @see docs/03-engines/event.md
 */
final class EventDispatcher implements DispatcherInterface
{
    private ListenerRegistry $registry;

    private ListenerResolver $resolver;

    /** @var list<string>|null allowlist الگوهای رشته‌ای (null = غیرفعال) */
    private ?array $allowlist = null;

    /** @var array<string, object>|null حالت Fake: class/name → آخرین payload */
    private ?array $faking = null;

    /** @var list<array{event: string, payload: mixed}> رویدادهای ضبط‌شده در Fake */
    private array $recorded = [];

    /** @var array<string, \Closure> extension registry (publisherها و …) */
    private array $extensions = [];

    /** @var list<string> رویدادهای push‌شده (M1: بدون queue → ضبط محلی) */
    private array $pushed = [];

    private bool $stopOnError = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly ContainerInterface $container,
        array $config = [],
    ) {
        $this->registry = new ListenerRegistry((int) ($config['max_listeners'] ?? 500));
        $this->resolver = new ListenerResolver($container);
        $this->stopOnError = (bool) ($config['stop_on_error'] ?? false);
    }

    // ─── ثبت ──────────────────────────────────────────────────────────────

    public function listen(string|array $events, callable|string|array $listener, int $priority = 0): void
    {
        foreach ((array) $events as $event) {
            $this->registry->add((string) $event, $listener, $priority);
        }
    }

    public function subscribe(string|object $subscriber): void
    {
        $instance = \is_object($subscriber) ? $subscriber : $this->container->make($subscriber);

        if (!$instance instanceof EventSubscriber) {
            throw new \InvalidArgumentException(\sprintf('Subscriber [%s] must implement EventSubscriber.', $instance::class));
        }

        foreach ($instance->subscribe($this) as $event => $methodOrPriority) {
            if (\is_string($methodOrPriority)) {
                $this->listen((string) $event, [$instance, $methodOrPriority]);
                continue;
            }
            // کلید عددی: event => priority و متد پیش‌فرض handle
            $this->listen((string) $methodOrPriority === '' ? '' : (string) $event, [$instance, 'handle'], (int) $methodOrPriority);
        }
    }

    public function hasListeners(string $event): bool
    {
        return $this->registry->hasListeners($event);
    }

    /**
     * @return list<RegisteredListener>
     */
    public function getListeners(string $event): array
    {
        return $this->registry->listenersFor($event);
    }

    public function forget(string $event): void
    {
        $this->registry->forget($event);
    }

    public function flush(): void
    {
        $this->registry->flush();
    }

    // ─── Dispatch ─────────────────────────────────────────────────────────

    public function dispatch(object|string $event, mixed $payload = [], bool $halt = false): mixed
    {
        $name = \is_string($event) ? $event : $event::class;

        // حالت Fake: فقط ضبط
        if ($this->faking !== null && $this->shouldFake($name)) {
            $this->recorded[] = ['event' => $name, 'payload' => $payload, 'object' => \is_object($event) ? $event : null];

            return $halt ? null : [];
        }

        $this->assertAllowlisted($name);

        // ShouldQueue → صف
        if (\is_object($event) && $event instanceof ShouldQueue) {
            $this->pushToQueue($name, $event, $payload);

            return $halt ? null : [];
        }

        $listeners = $this->registry->listenersFor($event);
        $responses = [];

        foreach ($listeners as $listener) {
            try {
                $response = $this->resolver->call($listener->listener, $event, $payload);
            } catch (\Throwable $e) {
                $this->handleListenerError($name, $listener, $e);
                $response = null;
                if ($this->stopOnError) {
                    throw ListenerException::for($name, $listener->describe(), $e);
                }
            }

            if ($halt && $response !== null) {
                return $response;
            }

            $responses[] = $response;

            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }

        return $halt ? ($responses[0] ?? null) : $responses;
    }

    public function until(string|object $event, mixed $payload = []): mixed
    {
        return $this->dispatch($event, $payload, halt: true);
    }

    public function push(string $event, mixed $payload = []): void
    {
        $this->pushToQueue($event, $event, $payload);
    }

    // ─── امنیت ────────────────────────────────────────────────────────────

    /**
     * فعال‌سازی allowlist برای dispatch رویدادهای رشته‌ای.
     *
     * @param list<string> $patterns
     */
    public function allowDispatch(array $patterns): void
    {
        $this->allowlist = $patterns;
    }

    // ─── Fake (تست) ───────────────────────────────────────────────────────

    /**
     * ضبط رویدادها به‌جای اجرای Listenerها.
     *
     * @param list<string>|null $events فقط این رویدادها (null = همه)
     */
    public function fake(?array $events = null): void
    {
        $this->faking = [];
        $this->recorded = [];
        if ($events !== null) {
            foreach ($events as $e) {
                $this->faking[$e] = new \stdClass();
            }
        }
    }

    public function isFaking(): bool
    {
        return $this->faking !== null;
    }

    /** @param (callable(object|string, mixed): bool)|null $callback */
    public function assertDispatched(string $event, ?callable $callback = null): void
    {
        $matched = $this->dispatchedCount($event, $callback);

        if ($matched === 0) {
            throw new \RuntimeException(\sprintf('The expected [%s] event was not dispatched.', $event));
        }
    }

    public function assertNotDispatched(string $event, ?callable $callback = null): void
    {
        if ($this->dispatchedCount($event, $callback) > 0) {
            throw new \RuntimeException(\sprintf('The unexpected [%s] event was dispatched.', $event));
        }
    }

    public function assertDispatchedTimes(string $event, int $times = 1): void
    {
        $actual = $this->dispatchedCount($event);
        if ($actual !== $times) {
            throw new \RuntimeException(\sprintf('Event [%s] dispatched %d times, expected %d.', $event, $actual, $times));
        }
    }

    /** @return list<array{event: string, payload: mixed}> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    // ─── Extension ────────────────────────────────────────────────────────

    /** ثبت افزونه (مانند `publisher.nats` برای Outbox در M3). */
    public function extend(string $name, \Closure $factory): void
    {
        $this->extensions[$name] = $factory;
    }

    public function extension(string $name): mixed
    {
        if (!isset($this->extensions[$name])) {
            return null;
        }

        return $this->extensions[$name] ??= ($this->extensions[$name])($this->container);
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function shouldFake(string $name): bool
    {
        \assert($this->faking !== null);

        return $this->faking === [] || isset($this->faking[$name]);
    }

    private function assertAllowlisted(string $name): void
    {
        if ($this->allowlist === null || \class_exists($name)) {
            return;   // رویدادهای شیئی (class-string واقعی) بدون محدودیت
        }

        foreach ($this->allowlist as $pattern) {
            if ($this->matchesPattern($pattern, $name)) {
                return;
            }
        }

        throw EventNotAllowlistedException::for($name);
    }

    private function matchesPattern(string $pattern, string $name): bool
    {
        if ($pattern === $name || $pattern === '*') {
            return true;
        }

        $regex = '/^' . \str_replace('\*', '[a-zA-Z0-9_\-.]+', \preg_quote($pattern, '/')) . '$/';

        return \preg_match($regex, $name) === 1;
    }

    private function handleListenerError(string $event, RegisteredListener $listener, \Throwable $e): void
    {
        if (!$this->container->bound('log')) {
            return;
        }

        $logger = $this->container->make('log');
        if ($logger instanceof LoggerInterface) {
            $logger->error('event.listener_failed', [
                'event' => $event,
                'listener' => $listener->describe(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function pushToQueue(string $name, object|string $event, mixed $payload): void
    {
        if ($this->container->bound('queue')) {
            $this->container->make('queue')->push($event, $payload);

            return;
        }

        // M1: صف هنوز موجود نیست — ضبط محلی برای تست/اشکال‌زدایی
        $this->pushed[] = $name;
    }

    /** @return list<string> */
    public function pushedEvents(): array
    {
        return $this->pushed;
    }

    private function dispatchedCount(string $event, ?callable $callback = null): int
    {
        $count = 0;
        foreach ($this->recorded as $record) {
            if ($record['event'] !== $event) {
                continue;
            }
            if ($callback === null || $callback($record['object'] ?? $event, $record['payload'])) {
                $count++;
            }
        }

        return $count;
    }
}
