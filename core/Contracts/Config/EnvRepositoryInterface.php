<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Config;

/**
 * قرارداد مخزن متغیرهای محیطی (بدون putenv — DOC-027 §4.1 E2).
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
interface EnvRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function string(string $key, string $default = ''): string;

    public function bool(string $key, bool $default = false): bool;

    public function int(string $key, int $default = 0): int;

    public function float(string $key, float $default = 0.0): float;

    /** @return list<string> */
    public function list(string $key, string $separator = ','): array;

    /** @return array<string, mixed>|null */
    public function json(string $key): ?array;

    /** @param list<string> $keys */
    public function required(array $keys): void;

    /** @return array<string, mixed> */
    public function all(bool $masked = false): array;
}
