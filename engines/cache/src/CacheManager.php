<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache;

use Tavaan\Core\Contracts\Cache\LockInterface;
use Tavaan\Core\Contracts\Cache\RepositoryInterface;
use Tavaan\Core\Contracts\Cache\StoreInterface;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Engine\Cache\Stores\ArrayStore;
use Tavaan\Engine\Cache\Stores\ApcuStore;
use Tavaan\Engine\Cache\Stores\DatabaseStore;
use Tavaan\Engine\Cache\Stores\FileStore;
use Tavaan\Engine\Cache\Stores\MemcachedStore;
use Tavaan\Engine\Cache\Stores\RedisStore;

/**
 * تَوان | Tavaan — Cache
 * CacheManager — مدیر ۶ درایور با **fallback خودکار به file** وقتی
 * extension (redis/apcu/memcached) موجود نیست (DOC-060 §2).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class CacheManager implements RepositoryInterface
{
    /** @var array<string, CacheRepository> */
    private array $repositories = [];

    /** @var array<string, \Closure(array<string, mixed>): StoreInterface> */
    private array $extensions = [];

    /** @var list<string> fallbackهای انجام‌شده (برای doctor/log) */
    private array $fallbacks = [];

    public function __construct(
        private readonly ?ConfigRepository $config = null,
        private readonly ?ConnectionInterface $db = null,
    ) {
    }

    public function getDefaultDriver(): string
    {
        return (string) ($this->config?->get('cache.default', 'file') ?? 'file');
    }

    /** مخزن پیش‌فرض (facade root). */
    public function repository(?string $store = null): CacheRepository
    {
        $store ??= $this->getDefaultDriver();

        return $this->repositories[$store] ??= new CacheRepository($this->resolveStore($store), $this->cacheConfig());
    }

    public function store(?string $name = null): CacheRepository
    {
        return $this->repository($name);
    }

    public function driver(?string $name = null): CacheRepository
    {
        return $this->repository($name);
    }

    /**
     * ثبت درایور سفارشی.
     *
     * @param \Closure(array<string, mixed>): StoreInterface $factory
     */
    public function extend(string $driver, \Closure $factory): void
    {
        $this->extensions[$driver] = $factory;
    }

    /** @return list<string> */
    public function fallbacks(): array
    {
        return $this->fallbacks;
    }

    /** @param list<string> $tags */
    public function tags(array $tags): TaggedCache
    {
        return $this->repository()->tags($tags);
    }

    public function lock(string $name, int $seconds = 0, ?string $owner = null): LockInterface
    {
        return $this->repository()->lock($name, $seconds, $owner);
    }

    // ─── ساخت store ───────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function storeConfig(string $name): array
    {
        $config = (array) ($this->config?->get('cache.stores.' . $name, []) ?? []);
        if ($config === []) {
            throw new \InvalidArgumentException(\sprintf('Cache store [%s] is not configured.', $name));
        }

        return $config;
    }

    private function resolveStore(string $name): StoreInterface
    {
        $config = $this->storeConfig($name);
        $driver = (string) ($config['driver'] ?? $name);

        if (isset($this->extensions[$driver])) {
            return ($this->extensions[$driver])($config);
        }

        try {
            return match ($driver) {
                'array' => new ArrayStore((string) ($config['prefix'] ?? '')),
                'file' => new FileStore(
                    (string) ($config['path'] ?? $this->storagePath('cache/data')),
                    (string) ($config['prefix'] ?? ''),
                    (int) ($config['permission'] ?? 0664),
                ),
                'database' => $this->makeDatabaseStore($config),
                'redis' => $this->requireExtension('redis', $name) ? new RedisStore($config, (string) ($config['prefix'] ?? 'tvn:')) : $this->fallback($name),
                'apcu' => $this->requireExtension('apcu', $name) ? new ApcuStore((string) ($config['prefix'] ?? 'tvn:')) : $this->fallback($name),
                'memcached' => $this->requireExtension('memcached', $name) ? new MemcachedStore($config, (string) ($config['prefix'] ?? 'tvn:')) : $this->fallback($name),
                default => throw new \InvalidArgumentException(\sprintf('Unknown cache driver [%s].', $driver)),
            };
        } catch (\RuntimeException) {
            // خطای اتصال در زمان ساخت → fallback
            return $this->fallback($name);
        }
    }

    /** @param array<string, mixed> $config */
    private function makeDatabaseStore(array $config): StoreInterface
    {
        if ($this->db === null) {
            return $this->fallback('database');
        }

        return new DatabaseStore(
            $this->db,
            (string) ($config['table'] ?? 'cache'),
            (string) ($config['prefix'] ?? ''),
        );
    }

    private function requireExtension(string $extension, string $store): bool
    {
        return \extension_loaded($extension);
    }

    /** 🔒 fallback به file بدون crash (AC-CACHE-09/11). */
    private function fallback(string $requested): FileStore
    {
        $this->fallbacks[] = $requested . '→file';

        return new FileStore($this->storagePath('cache/data'), '', 0664);
    }

    private function storagePath(string $relative): string
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \sys_get_temp_dir();

        return $base . '/storage/' . $relative;
    }

    /** @return array<string, mixed> */
    private function cacheConfig(): array
    {
        return (array) ($this->config?->get('cache', []) ?? []);
    }

    // ─── forwarding به درایور پیش‌فرض ─────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository()->get($key, $default);
    }

    public function many(array $keys): array
    {
        return $this->repository()->many($keys);
    }

    public function putMany(array $values, int $ttl = 0): bool
    {
        return $this->repository()->putMany($values, $ttl);
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->repository()->put($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->repository()->has($key);
    }

    public function missing(string $key): bool
    {
        return $this->repository()->missing($key);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->repository()->forever($key, $value);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return $this->repository()->remember($key, $ttl, $callback);
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->repository()->rememberForever($key, $callback);
    }

    public function forget(string $key): bool
    {
        return $this->repository()->forget($key);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->repository()->pull($key, $default);
    }

    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->repository()->add($key, $value, $ttl);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->repository()->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->repository()->decrement($key, $value);
    }

    public function flush(): bool
    {
        return $this->repository()->flush();
    }
}
