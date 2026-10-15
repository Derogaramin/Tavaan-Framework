<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Events;

/**
 * قرارداد Event Dispatcher (DOC-014 §2.2).
 *
 * @api 1.0
 * @see docs/03-engines/event.md
 */
interface DispatcherInterface
{
    /**
     * ثبت Listener.
     *
     * @param class-string|list<class-string>|string                               $events
     * @param callable|class-string|array{0: object|class-string, 1: string}       $listener
     */
    public function listen(string|array $events, callable|string|array $listener, int $priority = 0): void;

    /** ثبت Subscriber (کلاسی که چند رویداد را ثبت می‌کند). */
    public function subscribe(string|object $subscriber): void;

    public function hasListeners(string $event): bool;

    /**
     * Dispatch رویداد (شیء یا نام رشته‌ای).
     *
     * @param object|string $event
     * @param mixed         $payload
     * @param bool          $halt  توقف در اولین پاسخ غیر-null
     *
     * @return array<int, mixed>|mixed
     */
    public function dispatch(object|string $event, mixed $payload = [], bool $halt = false): mixed;

    /** dispatch با halt=true. */
    public function until(string|object $event, mixed $payload = []): mixed;

    public function forget(string $event): void;

    /** ارسال صریح به صف (Queue). */
    public function push(string $event, mixed $payload = []): void;
}
