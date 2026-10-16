<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Database
 * DatabaseServiceProvider — ثبت ConnectionManager به‌عنوان `db` (Facade: DB).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectionManager::class, static function (ContainerInterface $c): ConnectionManager {
            return new ConnectionManager(
                $c->bound('config') ? $c->make('config') : null,
                $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null,
                // 🔒 resolving تنبل: LogManager نباید در boot (پیش از تنظیم کانال) ساخته شود
                static fn (): ?LoggerInterface => $c->bound(LoggerInterface::class) ? $c->make(LoggerInterface::class) : null,
            );
        });
        $this->app->alias(ConnectionManager::class, 'db');

        // ConnectionInterface → اتصال پیش‌فرض (تزریق مستقیم در سرویس‌ها)
        $this->app->bind(ConnectionInterface::class, static fn (ContainerInterface $c): ConnectionInterface => $c->make('db')->connection());
        $this->app->bind(QueryBuilder::class, static fn (ContainerInterface $c): QueryBuilder => $c->make('db')->connection()->query());
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [ConnectionManager::class, ConnectionInterface::class, QueryBuilder::class, 'db'];
    }
}
