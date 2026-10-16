<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Stores;

use Tavaan\Core\Contracts\Cache\StoreInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Cache
 * DatabaseStore — درایور دیتابیس (بدون Redis) با add اتمیک بر پایهٔ UNIQUE constraint.
 *
 * 🔒 payload فقط JSON.
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class DatabaseStore implements StoreInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'cache',
        private readonly string $prefix = '',
    ) {
    }

    public function get(string $key): mixed
    {
        $row = $this->connection->table($this->table)
            ->where('cache_key', $this->key($key))
            ->first();

        if ($row === null) {
            return null;
        }

        $expires = (int) $row['expires_at'];
        if ($expires !== 0 && $expires <= Clock::timestamp()) {
            $this->forget($key);

            return null;
        }

        try {
            $decoded = \json_decode((string) $row['cache_value'], true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($decoded) && \array_key_exists('v', $decoded) ? $decoded['v'] : null;
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
        $full = $this->key($key);
        $expires = $ttl > 0 ? Clock::timestamp() + $ttl : 0;
        $encoded = $this->encode($value);

        $this->connection->table($this->table)->updateOrInsert(
            ['cache_key' => $full],
            ['cache_value' => $encoded, 'expires_at' => $expires],
        );

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
        $full = $this->key($key);

        return $this->connection->table($this->table)
            ->where('cache_key', $full)
            ->where(static fn ($q) => $q->where('expires_at', 0)->orWhere('expires_at', '>', Clock::timestamp()))
            ->exists();
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $current = $this->get($key);
        $new = (int) ($current ?? 0) + $value;
        $expires = $this->expirationOf($key);

        $this->connection->table($this->table)->updateOrInsert(
            ['cache_key' => $this->key($key)],
            ['cache_value' => $this->encode($new), 'expires_at' => $expires],
        );

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
        $this->connection->table($this->table)->where('cache_key', $this->key($key))->delete();

        return true;
    }

    public function flush(): bool
    {
        $like = $this->prefix . '%';
        $this->connection->table($this->table)->where('cache_key', 'like', $like)->delete();

        return true;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** افزودن اتمیک: INSERT با تکیه بر UNIQUE — نقض = کلید موجود. */
    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        $full = $this->key($key);
        $expires = $ttl > 0 ? Clock::timestamp() + $ttl : 0;

        // ابتدا رکورد منقضی احتمالی را پاک کن (تا UNIQUE آزاد شود)
        $this->connection->table($this->table)
            ->where('cache_key', $full)
            ->where('expires_at', '!=', 0)
            ->where('expires_at', '<=', Clock::timestamp())
            ->delete();

        try {
            $this->connection->table($this->table)->insert([
                'cache_key' => $full,
                'cache_value' => $this->encode($value),
                'expires_at' => $expires,
            ]);

            return true;
        } catch (\Tavaan\Engine\Database\Exceptions\QueryException) {
            return false;   // UNIQUE violation → کلید موجود است
        }
    }

    private function expirationOf(string $key): int
    {
        $row = $this->connection->table($this->table)
            ->where('cache_key', $this->key($key))
            ->first();

        return (int) ($row['expires_at'] ?? 0);
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    private function encode(mixed $value): string
    {
        return (string) \json_encode(['v' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
