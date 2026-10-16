<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Contracts\Queue\QueueInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Queue
 * QueueServiceProvider — ثبت QueueManager به‌عنوان `queue` (Facade: Queue).
 *
 * 🔒 EventDispatcher از قبل `container->make('queue')->push(...)` را برای
 * رویدادهای ShouldQueue فراخوانی می‌کند — این binding آن را فعال می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueueManager::class, static function (ContainerInterface $c): QueueManager {
            return new QueueManager(
                $c->bound('config') ? $c->make('config') : null,
                $c,
                // 🔒 تنبل: ConnectionManager اتصال PDO را تا اولین query نمی‌سازد
                $c->bound(ConnectionInterface::class) ? $c->make(ConnectionInterface::class) : null,
                $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
                $c->bound(CacheRepository::class) ? $c->make(CacheRepository::class) : ($c->bound('cache') ? $c->make('cache') : null),
            );
        });
        $this->app->alias(QueueManager::class, 'queue');
        $this->app->alias(QueueManager::class, QueueInterface::class);

        // Worker — برای queue:work و Queue::worker()
        $this->app->bind(Worker::class, static function (ContainerInterface $c): Worker {
            return new Worker(
                $c->make(QueueManager::class),
                $c,
                $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
                $c->bound(CacheRepository::class) ? $c->make(CacheRepository::class) : ($c->bound('cache') ? $c->make('cache') : null),
                $c->bound(LoggerInterface::class) ? $c->make(LoggerInterface::class) : null,
            );
        });
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [QueueManager::class, QueueInterface::class, Worker::class, 'queue'];
    }
}
