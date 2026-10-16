<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache;

use Tavaan\Core\Contracts\Cache\RepositoryInterface;
use Tavaan\Core\Contracts\Cache\StoreInterface;

/**
 * تَوان | Tavaan — Cache
 * TaggedCache — کش با تگ (الگوریتم namespace — DOC-060 §6).
 *
 * هر مجموعهٔ تگ یک «identifier» تصادفی در store دارد؛ کلید آیتم‌ها با
 * پیشوند identifierها ساخته می‌شود. flush تگ = ساخت identifier جدید
 * (آیتم‌های قدیمی orphan و با TTL پاک می‌شوند) — بدون نیاز به SADD.
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class TaggedCache implements RepositoryInterface
{
    private readonly string $namespace;

    /** @param list<string> $tags */
    public function __construct(
        private readonly CacheRepository $repository,
        private readonly array $tags,
        private readonly string $tagPrefix = 'tvn:tag:',
    ) {
        $this->namespace = $this->resolveNamespace();
    }

    /** @return list<string> */
    public function tags(): array
    {
        return $this->tags;
    }

    private function resolveNamespace(): string
    {
        $parts = [];
        foreach ($this->tags as $tag) {
            $idKey = $this->tagPrefix . $tag . ':id';
            $id = $this->repository->getStore()->get($idKey);
            if (!\is_string($id) || $id === '') {
                $id = \bin2hex(\random_bytes(8));
                $this->repository->getStore()->forever($idKey, $id);
            }
            $parts[] = $tag . ':' . $id;
        }
        \sort($parts);   // ترتیب تگ‌ها روی namespace اثر نداشته باشد

        return \implode('|', $parts) . '::';
    }

    private function itemKey(string $key): string
    {
        return $this->namespace . $key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->repository->get($this->itemKey($key), $default);
    }

    public function many(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[(string) $key] = $this->get((string) $key);
        }

        return $out;
    }

    public function putMany(array $values, int $ttl = 0): bool
    {
        $mapped = [];
        foreach ($values as $key => $value) {
            $mapped[$this->itemKey((string) $key)] = $value;
        }

        return $this->repository->getStore()->putMany($mapped, $ttl);
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->repository->put($this->itemKey($key), $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->repository->has($this->itemKey($key));
    }

    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->repository->forever($this->itemKey($key), $value);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return $this->repository->remember($this->itemKey($key), $ttl, $callback);
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->repository->rememberForever($this->itemKey($key), $callback);
    }

    public function forget(string $key): bool
    {
        return $this->repository->forget($this->itemKey($key));
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        return $this->repository->pull($this->itemKey($key), $default);
    }

    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->repository->add($this->itemKey($key), $value, $ttl);
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->repository->increment($this->itemKey($key), $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->repository->decrement($this->itemKey($key), $value);
    }

    /** flush فقط تگ‌های این مجموعه (identifier جدید). */
    public function flush(): bool
    {
        foreach ($this->tags as $tag) {
            $this->repository->getStore()->forever($this->tagPrefix . $tag . ':id', \bin2hex(\random_bytes(8)));
        }

        return true;
    }

    public function lock(string $name, int $seconds = 0, ?string $owner = null): \Tavaan\Core\Contracts\Cache\LockInterface
    {
        return $this->repository->lock($this->itemKey($name), $seconds, $owner);
    }

    public function store(?string $name = null): \Tavaan\Core\Contracts\Cache\RepositoryInterface
    {
        return $this;
    }

    public function getStore(): StoreInterface
    {
        return $this->repository->getStore();
    }
}
