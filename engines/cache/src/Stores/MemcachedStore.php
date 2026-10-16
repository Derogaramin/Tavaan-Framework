<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Stores;

use Tavaan\Core\Contracts\Cache\StoreInterface;

/**
 * تَوان | Tavaan — Cache
 * MemcachedStore — درایور Memcached (ext-memcached).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class MemcachedStore implements StoreInterface
{
    private ?\Memcached $client = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly string $prefix = 'tvn:',
    ) {
        if (!\extension_loaded('memcached')) {
            throw new \RuntimeException('MemcachedStore requires the memcached extension.');
        }
    }

    private function memcached(): \Memcached
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $memcached = new \Memcached();
        $memcached->setOption(\Memcached::OPT_PREFIX_KEY, $this->prefix);
        $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
        $memcached->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);

        $servers = [];
        foreach ((array) ($this->config['servers'] ?? []) as $server) {
            $servers[] = [
                (string) ($server['host'] ?? '127.0.0.1'),
                (int) ($server['port'] ?? 11211),
                (int) ($server['weight'] ?? 100),
            ];
        }
        if ($servers === []) {
            $servers[] = ['127.0.0.1', 11211, 100];
        }
        $memcached->addServers($servers);

        return $this->client = $memcached;
    }

    public function get(string $key): mixed
    {
        $value = $this->memcached()->get($key);

        return $this->memcached()->getResultCode() === \Memcached::RES_NOTFOUND ? null : $value;
    }

    public function many(array $keys): array
    {
        $values = $this->memcached()->getMulti(\array_values($keys)) ?: [];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $values[$key] ?? null;
        }

        return $out;
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->memcached()->set($key, $value, \max(0, $ttl));
    }

    public function putMany(array $values, int $ttl = 0): bool
    {
        return $this->memcached()->setMulti($values, \max(0, $ttl));
    }

    public function has(string $key): bool
    {
        $this->memcached()->get($key);

        return $this->memcached()->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        $new = $this->memcached()->increment($key, $value);

        return $new === false ? false : (int) $new;
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        $new = $this->memcached()->decrement($key, $value);

        return $new === false ? false : (int) $new;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function forget(string $key): bool
    {
        return $this->memcached()->delete($key);
    }

    public function flush(): bool
    {
        return $this->memcached()->flush();
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** اتمیک: memcached add. */
    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->memcached()->add($key, $value, \max(0, $ttl));
    }
}
