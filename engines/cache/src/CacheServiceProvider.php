<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache;

use Tavaan\Core\Contracts\Cache\RepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Cache
 * CacheServiceProvider — ثبت CacheManager به‌عنوان `cache` (Facade: Cache).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheManager::class, static function (ContainerInterface $c): CacheManager {
            return new CacheManager(
                $c->bound('config') ? $c->make('config') : null,
                // 🔒 db تنبل: فقط برای درایور database — اتصال در زمان boot ساخته نشود
                $c->bound(ConnectionInterface::class) ? $c->make(ConnectionInterface::class) : null,
            );
        });
        $this->app->alias(CacheManager::class, 'cache');
        $this->app->alias(CacheManager::class, RepositoryInterface::class);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [CacheManager::class, RepositoryInterface::class, 'cache'];
    }
}
