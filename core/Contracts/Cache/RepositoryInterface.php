<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Cache;

/**
 * تَوان | Tavaan — Contracts
 * RepositoryInterface — قرارداد مخزن کش (لایهٔ روی Store — DOC-014 §2.5).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
interface RepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    /** @param array<string, mixed>|list<string> $keys @return array<string, mixed> */
    public function many(array $keys): array;

    /** @param array<string, mixed> $values */
    public function putMany(array $values, int $ttl = 0): bool;

    public function put(string $key, mixed $value, int $ttl = 0): bool;

    public function has(string $key): bool;

    public function missing(string $key): bool;

    public function forever(string $key, mixed $value): bool;

    /** @param callable(): mixed $callback */
    public function remember(string $key, int $ttl, callable $callback): mixed;

    /** @param callable(): mixed $callback */
    public function rememberForever(string $key, callable $callback): mixed;

    public function forget(string $key): bool;

    public function pull(string $key, mixed $default = null): mixed;

    public function add(string $key, mixed $value, int $ttl = 0): bool;

    public function increment(string $key, int $value = 1): int|false;

    public function decrement(string $key, int $value = 1): int|false;

    public function flush(): bool;

    public function lock(string $name, int $seconds = 0, ?string $owner = null): LockInterface;

    public function store(?string $name = null): RepositoryInterface;
}
