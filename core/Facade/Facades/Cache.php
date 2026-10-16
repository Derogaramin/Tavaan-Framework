<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade حافظهٔ نهان (Cache).
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, int $ttl = 0)
 * @method static bool has(string $key)
 * @method static bool missing(string $key)
 * @method static bool forget(string $key)
 * @method static bool flush()
 * @method static bool forever(string $key, mixed $value)
 * @method static mixed remember(string $key, int $ttl, callable $callback)
 * @method static mixed rememberForever(string $key, callable $callback)
 * @method static bool add(string $key, mixed $value, int $ttl = 0)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static int|false increment(string $key, int $value = 1)
 * @method static int|false decrement(string $key, int $value = 1)
 * @method static array many(array $keys)
 * @method static bool putMany(array $values, int $ttl = 0)
 * @method static \Tavaan\Engine\Cache\TaggedCache tags(array $tags)
 * @method static \Tavaan\Core\Contracts\Cache\LockInterface lock(string $name, int $seconds = 0, ?string $owner = null)
 * @method static \Tavaan\Engine\Cache\CacheRepository store(?string $name = null)
 * @mixin \Tavaan\Engine\Cache\CacheManager
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class Cache extends Facade
{
    protected static function accessor(): string
    {
        return 'cache';
    }
}
