<?php

declare(strict_types=1);

namespace Tavaan\Engine\Event;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * تَوان | Tavaan — Event
 * ListenerResolver — تبدیل ثبت Listener به callable اجرایی.
 *
 * انواع پشتیبانی: Closure/callable، class-string (متد handle یا __invoke)،
 * `[Class, method]`، آبجکت آماده. Resolve تنبل از Container.
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
final class ListenerResolver
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * اجرای Listener با آرگومان‌های متناسب.
     *
     * @param callable|class-string|array{0: object|class-string, 1: string} $listener
     * @param object|string                                                  $event
     * @param mixed                                                          $payload
     */
    public function call(mixed $listener, object|string $event, mixed $payload): mixed
    {
        if (\is_string($listener) && \class_exists($listener)) {
            $instance = $this->container->make($listener);
            $method = \method_exists($instance, 'handle') ? 'handle' : '__invoke';

            return $instance->{$method}(...$this->argumentsFor($event, $payload));
        }

        if (\is_array($listener)) {
            [$target, $method] = $listener;
            $instance = \is_object($target) ? $target : $this->container->make($target);

            return $instance->{$method}(...$this->argumentsFor($event, $payload));
        }

        if ($listener instanceof \Closure || \is_callable($listener)) {
            return $listener(...$this->argumentsFor($event, $payload));
        }

        if (\is_object($listener) && \method_exists($listener, '__invoke')) {
            return $listener(...$this->argumentsFor($event, $payload));
        }

        throw new \InvalidArgumentException('Unsupported listener type: ' . \get_debug_type($listener));
    }

    /**
     * @return list<mixed>
     */
    private function argumentsFor(object|string $event, mixed $payload): array
    {
        if (\is_string($event)) {
            // قرارداد: listener رویداد رشته‌ای → ($eventName, $payload)
            return [$event, $payload];
        }

        if ($payload === [] || $payload === null) {
            return [$event];
        }

        return [$event, $payload];
    }
}
