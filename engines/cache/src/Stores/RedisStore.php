<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Stores;

use Tavaan\Core\Contracts\Cache\StoreInterface;

/**
 * تَوان | Tavaan — Cache
 * RedisStore — درایور Redis (ext-redis) با SET NX/PX اتمیک.
 *
 * ⚠️ فقط وقتی ساخته شود که extension redis موجود باشد (CacheManager
 * در غیر این صورت به file fallback می‌کند).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class RedisStore implements StoreInterface
{
    private ?\Redis $client = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly string $prefix = 'tvn:',
    ) {
        if (!\extension_loaded('redis')) {
            throw new \RuntimeException('RedisStore requires the redis extension.');
        }
    }

    private function redis(): \Redis
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $redis = new \Redis();
        $redis->connect(
            (string) ($this->config['host'] ?? '127.0.0.1'),
            (int) ($this->config['port'] ?? 6379),
            (float) ($this->config['timeout'] ?? 2.0),
        );

        $password = (string) ($this->config['password'] ?? '');
        if ($password !== '') {
            $redis->auth($password);
        }

        $db = (int) ($this->config['database'] ?? 0);
        if ($db > 0) {
            $redis->select($db);
        }

        $redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);

        return $this->client = $redis;
    }

    public function get(string $key): mixed
    {
        $value = $this->redis()->get($key);

        return $value === false ? null : $value;
    }

    public function many(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $values = $this->redis()->mget($keys);
        $out = [];
        foreach (\array_values($keys) as $i => $key) {
            $out[$key] = ($values[$i] ?? false) === false ? null : $values[$i];
        }

        return $out;
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($ttl > 0) {
            return (bool) $this->redis()->setex($key, $ttl, $value);
        }

        return (bool) $this->redis()->set($key, $value);
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
        return (bool) $this->redis()->exists($key);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $result = $this->redis()->incrBy($key, $value);

        return \is_int($result) ? $result : false;
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
        $this->redis()->del($key);

        return true;
    }

    public function flush(): bool
    {
        // فقط کلیدهای دارای prefix (نه FLUSHDB کامل)
        $iterator = null;
        while (($keys = $this->redis()->scan($iterator, $this->prefix . '*', 500)) !== false) {
            foreach ($keys as $key) {
                $this->redis()->del((string) $key);
            }
            if ($iterator === 0 || $iterator === null) {
                break;
            }
        }

        return true;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** 🔒 اتمیک: SET key val NX [EX ttl]. */
    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        $options = $ttl > 0 ? ['nx', 'ex' => $ttl] : ['nx'];

        return (bool) $this->redis()->set($key, $value, $options);
    }
}
