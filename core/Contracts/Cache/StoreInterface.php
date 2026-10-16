<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Cache;

/**
 * تَوان | Tavaan — Contracts
 * StoreInterface — قرارداد درایور کش (DOC-014 §2.5).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
interface StoreInterface
{
    public function get(string $key): mixed;

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function many(array $keys): array;

    public function put(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * @param array<string, mixed> $values
     */
    public function putMany(array $values, int $ttl = 0): bool;

    public function has(string $key): bool;

    public function increment(string $key, int $value = 1): int|false;

    public function decrement(string $key, int $value = 1): int|false;

    public function forever(string $key, mixed $value): bool;

    /** put اتمیک فقط در صورت نبود کلید (put-if-absent) — مبنای atomic lock. */
    public function add(string $key, mixed $value, int $ttl = 0): bool;

    public function forget(string $key): bool;

    public function flush(): bool;

    public function getPrefix(): string;
}
