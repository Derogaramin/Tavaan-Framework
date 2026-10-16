<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Database;

/**
 * تَوان | Tavaan — Contracts
 * ConnectionInterface — قرارداد اتصال دیتابیس (DOC-014 §2.4).
 *
 * @api 1.0
 * @see docs/01-standards/contracts-interfaces.md
 */
interface ConnectionInterface
{
    public function name(): string;

    /** @return string mysql|pgsql|sqlite */
    public function driver(): string;

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array;

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array;

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function cursor(string $sql, array $bindings = []): \Generator;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function insert(string $sql, array $bindings = []): bool;

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @return int تعداد سطرهای متأثر
     */
    public function update(string $sql, array $bindings = []): int;

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @return int تعداد سطرهای حذف‌شده
     */
    public function delete(string $sql, array $bindings = []): int;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): bool;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function affectingStatement(string $sql, array $bindings = []): int;

    public function lastInsertId(?string $name = null): string|int;

    /**
     * اجرای اتمیک در تراکنش (Nesting با Savepoint + Deadlock retry).
     *
     * @param \Closure(static): mixed $callback
     */
    public function transaction(\Closure $callback, int $attempts = 1, ?string $isolation = null): mixed;

    public function beginTransaction(?string $isolation = null): void;

    public function commit(): void;

    public function rollBack(int $toLevel = 0): void;

    public function transactionLevel(): int;

    public function pdo(): \PDO;

    /**
     * شروع Query Builder روی یک جدول.
     */
    public function table(string $table, ?string $as = null): QueryBuilderInterface;

    /** @return list<array{sql: string, bindings: array<int|string, mixed>, time: float, connection: string}> */
    public function queryLog(): array;
}
