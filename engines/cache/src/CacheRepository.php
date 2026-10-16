<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache;

use Tavaan\Core\Contracts\Cache\LockInterface;
use Tavaan\Core\Contracts\Cache\LockProviderInterface;
use Tavaan\Core\Contracts\Cache\RepositoryInterface;
use Tavaan\Core\Contracts\Cache\StoreInterface;
use Tavaan\Engine\Cache\Locks\CacheLock;

/**
 * تَوان | Tavaan — Cache
 * CacheRepository — لایهٔ روی Store: remember با **Stampede Protection**،
 * Lock اتمیک، max_value_size و کلید امن (DOC-060 §3/§5).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class CacheRepository implements RepositoryInterface, LockProviderInterface
{
    /** @var array<string, int> شمارش retry برای stampede (جلوگیری از حلقهٔ بی‌پایان) */
    private array $stampedeRetries = [];

    /**
     * @param array<string, mixed> $config cache.stampede_protection و سقف‌ها
     */
    public function __construct(
        private readonly StoreInterface $store,
        private readonly array $config = [],
    ) {
    }

    public function getStore(): StoreInterface
    {
        return $this->store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertKeySize($key);
        $value = $this->store->get($key);

        return $value ?? $default;
    }

    public function many(array $keys): array
    {
        // ورودی: list<string> یا map کلید→default
        $defaults = [];
        $names = [];
        foreach ($keys as $key => $value) {
            if (\is_int($key)) {
                $names[] = (string) $value;
                $defaults[(string) $value] = null;
            } else {
                $names[] = (string) $key;
                $defaults[(string) $key] = $value;
            }
        }

        $values = $this->store->many($names);
        foreach ($values as $name => $value) {
            if ($value === null) {
                $values[$name] = $defaults[$name] ?? null;
            }
        }

        return $values;
    }

    public function putMany(array $values, int $ttl = 0): bool
    {
        foreach ($values as $key => $value) {
            $this->assertKeySize((string) $key);
            $this->assertValueSize($value);
        }

        $ok = $this->store->putMany($values, $ttl);

        if ($this->serveStaleEnabled() && $ttl > 0) {
            foreach ($values as $key => $value) {
                $this->store->put($this->staleKey((string) $key), $value, $ttl + $this->staleGrace());
            }
        }

        return $ok;
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->assertKeySize($key);
        $this->assertValueSize($value);

        $ok = $this->store->put($key, $value, $ttl);

        if ($this->serveStaleEnabled() && $ttl > 0) {
            $this->store->put($this->staleKey($key), $value, $ttl + $this->staleGrace());
        }

        return $ok;
    }

    public function has(string $key): bool
    {
        return $this->store->has($key);
    }

    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    public function forever(string $key, mixed $value): bool
    {
        $this->assertKeySize($key);
        $this->assertValueSize($value);

        return $this->store->forever($key, $value);
    }

    /**
     * remember با Stampede Protection (DOC-060 §5):
     * فقط یک فرایند بازسازی می‌کند؛ بقیه stale می‌گیرند یا کوتاه صبر می‌کنند.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->store->get($key);
        if ($value !== null) {
            return $value;
        }

        if (!$this->stampedeEnabled()) {
            $value = $callback();
            $this->put($key, $value, $ttl);

            return $value;
        }

        $lock = $this->lock($this->lockKey($key), (int) ($this->config['stampede_protection']['lock_ttl'] ?? 10));
        if ($lock->acquire()) {
            try {
                // double-check
                $value = $this->store->get($key);
                if ($value !== null) {
                    return $value;
                }

                $value = $callback();
                $this->put($key, $value, $ttl);

                return $value;
            } finally {
                $lock->release();
                unset($this->stampedeRetries[$key]);
            }
        }

        // فرایند دوم: serve stale اگر موجود
        if ($this->serveStaleEnabled()) {
            $stale = $this->store->get($this->staleKey($key));
            if ($stale !== null) {
                return $stale;
            }
        }

        $retries = ($this->stampedeRetries[$key] ?? 0) + 1;
        $this->stampedeRetries[$key] = $retries;

        if ($retries > (int) ($this->config['stampede_protection']['max_wait_retries'] ?? 3)) {
            // تسلیم: خودمان بازسازی می‌کنیم (بدون کش تا تلاش بعدی)
            unset($this->stampedeRetries[$key]);

            return $callback();
        }

        \usleep(50_000);

        return $this->remember($key, $ttl, $callback);
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        $value = $this->store->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->forever($key, $value);

        return $value;
    }

    public function forget(string $key): bool
    {
        $this->store->forget($this->staleKey($key));

        return $this->store->forget($key);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->assertKeySize($key);
        $this->assertValueSize($value);

        if (!\method_exists($this->store, 'add')) {
            // fallback غیراتومیک برای storeهای فاقد add
            if ($this->store->has($key)) {
                return false;
            }

            return $this->store->put($key, $value, $ttl);
        }

        /** @var object{add(string, mixed, int): bool} $store */
        $store = $this->store;

        return $store->add($key, $value, $ttl);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->store->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->store->decrement($key, $value);
    }

    public function flush(): bool
    {
        return $this->store->flush();
    }

    // ─── Lock ─────────────────────────────────────────────────────────────

    public function lock(string $name, int $seconds = 0, ?string $owner = null): LockInterface
    {
        return new CacheLock(
            $this->store,
            $name,
            $seconds,
            $owner ?? CacheLock::generateOwnerToken(),
            (int) ($this->config['lock']['retry'] ?? 100),
        );
    }

    public function restoreLock(string $name, string $owner): LockInterface
    {
        return $this->lock($name, 0, $owner);
    }

    /** @param list<string> $tags */
    public function tags(array $tags): TaggedCache
    {
        $prefix = (string) ($this->config['tags']['prefix'] ?? 'tvn:tag:');

        return new TaggedCache($this, $tags, $prefix);
    }

    public function store(?string $name = null): RepositoryInterface
    {
        return $this;   // تعویض store در سطح CacheManager انجام می‌شود
    }

    /** GC کلیدهای منقضی (درایور file/database). */
    public function gc(): int
    {
        return 0;   // انقضا در read-path انجام می‌شود (lazy expiration)
    }

    // ─── درونی ────────────────────────────────────────────────────────────

    private function stampedeEnabled(): bool
    {
        return (bool) ($this->config['stampede_protection']['enabled'] ?? true);
    }

    private function serveStaleEnabled(): bool
    {
        return $this->stampedeEnabled() && (bool) ($this->config['stampede_protection']['serve_stale'] ?? true);
    }

    private function staleGrace(): int
    {
        return (int) ($this->config['stampede_protection']['stale_grace'] ?? 60);
    }

    private function lockKey(string $key): string
    {
        return 'lock:stampede:' . $key;
    }

    private function staleKey(string $key): string
    {
        return 'stale:' . $key;
    }

    private function assertKeySize(string $key): void
    {
        $max = (int) ($this->config['max_key_length'] ?? 512);
        if (\strlen($key) > $max) {
            throw new \InvalidArgumentException(\sprintf('Cache key too long (max %d).', $max));
        }
    }

    private function assertValueSize(mixed $value): void
    {
        $max = (int) ($this->config['max_value_size'] ?? 5242880);
        if (\is_string($value) && \strlen($value) > $max) {
            throw new \InvalidArgumentException(\sprintf('Cache value too large (max %d bytes).', $max));
        }
    }
}
