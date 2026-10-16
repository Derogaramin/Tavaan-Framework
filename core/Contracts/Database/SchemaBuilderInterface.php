<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Database;

/**
 * تَوان | Tavaan — Contracts
 * SchemaBuilderInterface — قرارداد ساخت/تغییر ساختار دیتابیس (DOC-048).
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
interface SchemaBuilderInterface
{
    /** @param \Closure(object): void $callback Blueprint receiver */
    public function create(string $table, \Closure $callback): void;

    /** @param \Closure(object): void $callback */
    public function table(string $table, \Closure $callback): void;

    public function drop(string $table): void;

    public function dropIfExists(string $table): void;

    public function rename(string $from, string $to): void;

    public function hasTable(string $table): bool;

    public function hasColumn(string $table, string $column): bool;

    /** @return list<array<string, mixed>> */
    public function getColumns(string $table): array;

    /** @return list<string> */
    public function getTables(): array;

    public function getColumnType(string $table, string $column): ?string;

    /**
     * معکوس‌مهندسی ساختار یک جدول.
     *
     * @return array<string, mixed>
     */
    public function introspect(string $table): array;
}
