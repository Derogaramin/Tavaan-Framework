<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Config;

/**
 * قرارداد مخزن پیکربندی با dot-notation (DOC-014).
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
interface RepositoryInterface
{
    /** @param array<string, mixed>|string $key */
    public function get(string $key, mixed $default = null): mixed;

    /** @param array<string, mixed>|string $key */
    public function set(array|string $key, mixed $value = null): void;

    public function has(string $key): bool;

    public function missing(string $key): bool;

    public function forget(string $key): void;

    public function pull(string $key, mixed $default = null): mixed;

    public function push(string $key, mixed $value): void;

    public function prepend(string $key, mixed $value): void;

    /** @return array<string, mixed> */
    public function all(): array;

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function only(array $keys): array;

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function except(array $keys): array;

    /** @return array<string, mixed> */
    public function namespace(string $ns): array;

    public function freeze(): void;

    public function isFrozen(): bool;

    public function toJson(bool $maskSecrets = true): string;

    /**
     * بررسی وجود کلیدهای اجباری در بوت.
     *
     * @param list<string> $keys
     */
    public function requiredKeys(array $keys): void;
}
