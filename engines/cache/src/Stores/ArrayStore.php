<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Stores;

use Tavaan\Core\Contracts\Cache\StoreInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Cache
 * ArrayStore — سریع‌ترین درایور (فقط یک درخواست/فرایند — تست و دادهٔ موقت).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class ArrayStore implements StoreInterface
{
    /** @var array<string, array{0: mixed, 1: int}> key → [value, expiration(0=forever)] */
    private array $storage = [];

    public function __construct(private readonly string $prefix = '')
    {
    }

    public function get(string $key): mixed
    {
        $entry = $this->storage[$this->key($key)] ?? null;
        if ($entry === null) {
            return null;
        }

        if ($this->expired($entry[1])) {
            unset($this->storage[$this->key($key)]);

            return null;
        }

        return $entry[0];
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
        $this->storage[$this->key($key)] = [$value, $this->expiration($ttl)];

        return true;
    }

    public function putMany(array $values, int $ttl = 0): bool
    {
        foreach ($values as $key => $value) {
            $this->put((string) $key, $value, $ttl);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $entry = $this->storage[$this->key($key)] ?? null;

        return $entry !== null && !$this->expired($entry[1]);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $current = (int) ($this->get($key) ?? 0);
        $new = $current + $value;
        $expiration = $this->storage[$this->key($key)][1] ?? 0;
        $this->storage[$this->key($key)] = [$new, $expiration];

        return $new;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function forget(string $key): bool
    {
        unset($this->storage[$this->key($key)]);

        return true;
    }

    public function flush(): bool
    {
        $this->storage = [];

        return true;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** افزودن اتمیک فقط اگر کلید معتبر نباشد (مبنای Lock). */
    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        $full = $this->key($key);
        $entry = $this->storage[$full] ?? null;
        if ($entry !== null && !$this->expired($entry[1])) {
            return false;
        }

        $this->storage[$full] = [$value, $this->expiration($ttl)];

        return true;
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    private function expiration(int $ttl): int
    {
        return $ttl > 0 ? Clock::timestamp() + $ttl : 0;
    }

    private function expired(int $expiration): bool
    {
        return $expiration !== 0 && $expiration <= Clock::timestamp();
    }
}
