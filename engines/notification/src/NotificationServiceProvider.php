<?php

declare(strict_types=1);

namespace Tavaan\Engine\Notification;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Notification
 * NotificationServiceProvider — ثبت NotificationSender به‌عنوان `notification` (Facade: Notification).
 *
 * @api 1.0
 * @see docs/03-engines/notification.md
 */
final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationSender::class, static function (ContainerInterface $c): NotificationSender {
            return new NotificationSender(
                $c,
                $c->bound('config') ? $c->make('config') : null,
                $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
                $c->bound(CacheRepository::class) ? $c->make(CacheRepository::class) : ($c->bound('cache') ? $c->make('cache') : null),
                $c->bound(LoggerInterface::class) ? $c->make(LoggerInterface::class) : null,
            );
        });
        $this->app->alias(NotificationSender::class, 'notification');

        // اعلان عمومی آرایه‌محور — نقطهٔ ورود ماژول‌ها/کیت‌ها (M9، ARCH-003):
        // $app->make('notification.message', ['payload' => [...]])
        $this->app->bind('notification.message', static fn (ContainerInterface $c, array $parameters = []): ArrayNotification => new ArrayNotification((array) ($parameters['payload'] ?? [])));
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [NotificationSender::class, 'notification', 'notification.message'];
    }
}
