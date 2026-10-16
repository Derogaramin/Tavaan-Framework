<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Stores;

use Tavaan\Core\Contracts\Cache\StoreInterface;

/**
 * تَوان | Tavaan — Cache
 * ApcuStore — درایور APCu (per-node؛ بدون اشتراک بین سرورها).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class ApcuStore implements StoreInterface
{
    public function __construct(private readonly string $prefix = 'tvn:')
    {
        if (!\extension_loaded('apcu')) {
            throw new \RuntimeException('ApcuStore requires the apcu extension.');
        }
    }

    public function get(string $key): mixed
    {
        $success = false;
        $value = \apcu_fetch($this->prefix . $key, $success);

        return $success ? $value : null;
    }

    public function many(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        return \apcu_store($this->prefix . $key, $value, \max(0, $ttl));
    }

    public function putMany(array $values, int $ttl = 0): bool
    {
        $prefixed = [];
        foreach ($values as $key => $value) {
            $prefixed[$this->prefix . (string) $key] = $value;
        }

        return \apcu_store($prefixed, null, \max(0, $ttl)) !== false;
    }

    public function has(string $key): bool
    {
        return \apcu_exists($this->prefix . $key);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $success = false;
        $new = \apcu_inc($this->prefix . $key, $value, $success);

        return $success ? (int) $new : false;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        $success = false;
        $new = \apcu_dec($this->prefix . $key, $value, $success);

        return $success ? (int) $new : false;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function forget(string $key): bool
    {
        return \apcu_delete($this->prefix . $key);
    }

    public function flush(): bool
    {
        if (!\class_exists(\APCUIterator::class)) {
            return \apcu_clear_cache();
        }

        $pattern = '/^' . \preg_quote($this->prefix, '/') . '/';
        \apcu_delete(new \APCUIterator($pattern));

        return true;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** اتمیک: apcu_add فقط در نبود کلید موفق است. */
    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        return \apcu_add($this->prefix . $key, $value, \max(0, $ttl));
    }
}
