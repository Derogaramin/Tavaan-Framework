<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Database;

/**
 * تَوان | Tavaan — Contracts
 * QueryBuilderInterface — قرارداد Query Builder سیال (DOC-014 §2.4).
 *
 * 🔒 همهٔ مقادیر کاربر از راه Binding (Prepared Statement ۱۰۰٪) منتقل می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
interface QueryBuilderInterface
{
    public function table(string $table, ?string $as = null): static;

    public function select(string ...$columns): static;

    /** @param array<int|string, mixed> $bindings */
    public function selectRaw(string $expression, array $bindings = []): static;

    public function selectSub(self|\Closure|string $query, string $as): static;

    public function distinct(): static;

    public function from(string $table, ?string $as = null): static;

    public function join(string $table, \Closure|string $first, ?string $op = null, ?string $second = null, string $type = 'inner'): static;

    public function leftJoin(string $table, \Closure|string $first, ?string $op = null, ?string $second = null): static;

    public function rightJoin(string $table, \Closure|string $first, ?string $op = null, ?string $second = null): static;

    public function crossJoin(string $table, \Closure|string|null $first = null, ?string $op = null, ?string $second = null): static;

    public function where(string|\Closure|self $column, mixed $op = null, mixed $value = null, string $boolean = 'and'): static;

    public function orWhere(string|\Closure|self $column, mixed $op = null, mixed $value = null): static;

    /** @param array<int, mixed>|self|\Closure $values */
    public function whereIn(string $column, array|self|\Closure $values, string $boolean = 'and', bool $not = false): static;

    /** @param array<int, mixed>|self|\Closure $values */
    public function whereNotIn(string $column, array|self|\Closure $values, string $boolean = 'and'): static;

    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static;

    public function whereNotNull(string $column, string $boolean = 'and'): static;

    /** @param array{0: mixed, 1: mixed} $values */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static;

    public function whereExists(\Closure|self $callback, string $boolean = 'and', bool $not = false): static;

    /** @param array<int|string, mixed> $bindings */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): static;

    public function groupBy(string ...$columns): static;

    /** @param array<int|string, mixed> $bindings */
    public function groupByRaw(string $sql, array $bindings = []): static;

    public function having(string $column, mixed $op = null, mixed $value = null, string $boolean = 'and'): static;

    /** @param array<int|string, mixed> $bindings */
    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'and'): static;

    public function orderBy(string $column, string $direction = 'asc'): static;

    public function orderByDesc(string $column): static;

    /** @param array<int|string, mixed> $bindings */
    public function orderByRaw(string $sql, array $bindings = []): static;

    public function limit(int $limit): static;

    public function offset(int $offset): static;

    public function forUpdate(): static;

    /** قفل ردیف با SKIP LOCKED (MySQL 8+/PG 9.5+؛ در SQLite خالی) — DOC-061 §6. */
    public function forUpdateSkipLocked(): static;

    /** نام مستعار forUpdateSkipLocked. */
    public function lockForUpdateSkipLocked(): static;

    public function sharedLock(): static;

    public function union(self|\Closure $query, bool $all = false): static;

    public function withCte(string $name, \Closure|self $query): static;

    public function toSql(): string;

    /** @return array<int|string, mixed> */
    public function getBindings(): array;

    /** @param list<string> $columns @return list<array<string, mixed>> */
    public function get(array $columns = ['*']): array;

    /** @return \Generator<int, array<string, mixed>> */
    public function cursor(): \Generator;

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>|null
     */
    public function first(array $columns = ['*']): ?array;

    /**
     * @param list<string> $columns
     *
     * @return array<string, mixed>|null
     */
    public function find(int|string $id, array $columns = ['*']): ?array;

    public function value(string $column): mixed;

    /** @return array<int|string, mixed> */
    public function pluck(string $column, ?string $key = null): array;

    public function count(string $column = '*'): int;

    public function sum(string $column): int|float|null;

    public function avg(string $column): int|float|null;

    public function min(string $column): int|float|null;

    public function max(string $column): int|float|null;

    public function exists(): bool;

    /** @param array<string, mixed>|list<array<string, mixed>> $values */
    public function insert(array $values): bool;

    /** @param array<string, mixed> $values */
    public function insertGetId(array $values, ?string $sequence = null): int|string;

    /** @param list<array<string, mixed>> $values */
    public function insertOrIgnore(array $values): int;

    /**
     * @param list<array<string, mixed>> $values
     * @param list<string>               $uniqueBy
     * @param list<string>|null          $update
     */
    public function upsert(array $values, array $uniqueBy, ?array $update = null): int;

    /** @param array<string, mixed> $values */
    public function update(array $values): int;

    /**
     * به‌روزرسانی رکورد منطبق بر attributes یا درج آن در صورت نبود (اتمیک در حد ممکن).
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public function updateOrInsert(array $attributes, array $values = []): bool;

    /** @param array<string, mixed> $extra */
    public function increment(string $column, int|float $amount = 1, array $extra = []): int;

    /** @param array<string, mixed> $extra */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): int;

    public function delete(): int;

    public function truncate(): void;

    /** @param list<string> $columns */
    public function paginate(int $perPage = 15, int $page = 1, array $columns = ['*']): PaginatorInterface;

    /** @param callable(list<array<string, mixed>>): bool|void $callback */
    public function chunk(int $size, callable $callback): bool;

    /** @return \Generator<int, array<string, mixed>> */
    public function lazy(int $chunkSize = 1000): \Generator;

    public function clone(): static;
}
