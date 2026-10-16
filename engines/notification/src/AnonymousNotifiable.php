<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification;

use Tavaan\Core\Contracts\Notification\Notifiable;

/**
 * تَوان | Tavaan — Notification
 * AnonymousNotifiable — آگاه‌سازی بدون مدل (DOC-066 §3).
 *
 * ```php
 * Notification::route('mail', 'user@example.com')->notify(new Welcome());
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class AnonymousNotifiable implements Notifiable
{
    /** @var array<string, mixed> */
    private array $routes = [];

    public function route(string $channel, mixed $route): self
    {
        $this->routes[$channel] = $route;

        return $this;
    }

    /** ارسال مستقیم. */
    public function notify(Notification $notification): void
    {
        // resolves lazily تا بدون container هم قابل ساخت باشد
        $sender = \Tavaan\Core\Facade\Facades\Notification::getFacadeRoot();
        $sender->send($this, $notification);
    }

    public function routeNotificationFor(string $driver, ?object $notification = null): mixed
    {
        return $this->routes[$driver] ?? null;
    }

    public function getNotificationKey(): int|string
    {
        // کلید پایدار از routeها (برای rate limit / assert)
        return \substr(\sha1((string) \json_encode($this->routes, \JSON_UNESCAPED_UNICODE)), 0, 20);
    }

    /** @return array<string, mixed> */
    public function routes(): array
    {
        return $this->routes;
    }
}
