<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Database;

/**
 * تَوان | Tavaan — Contracts
 * GrammarInterface — قرارداد کامپایل SQL مخصوص هر درایور (DOC-014 §2.4).
 *
 * ورودی همهٔ compileها «اجزای نرمال‌شده» است (نه خود Builder) تا
 * Grammar از API سیال مستقل بماند.
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
interface GrammarInterface
{
    /** @return string mysql|pgsql|sqlite */
    public function driver(): string;

    /** پیشوند جداول (از config). */
    public function prefix(): string;

    /** پوشش شناسه (ستون/جدول) با escape امن؛ `*` و Expression دست‌نخورده. */
    public function wrap(string|\Stringable $value): string;

    /** پوشش نام جدول + اعمال prefix. */
    public function wrapTable(string $table): string;

    /**
     * @param list<string|\Stringable> $values
     *
     * @return list<string>
     */
    public function wrapArray(array $values): array;

    /**
     * DSN از پیکربندی اتصال.
     *
     * @param array<string, mixed> $config
     */
    public function dsn(array $config): string;

    /**
     * کامپایل SELECT کامل از اجزای Builder.
     *
     * @param array<string, mixed> $components
     */
    public function compileSelect(array $components): string;

    /**
     * کامپایل INSERT (یک یا چند سطری).
     *
     * @param array<string, mixed>       $values  سطری برای insert ساده
     * @param list<array<string, mixed>> $rows    برای insert چندسطری/OrIgnore/Upsert
     */
    public function compileInsert(string $table, array $values = [], array $rows = [], string $mode = ''): string;

    /**
     * کامپایل UPSERT.
     *
     * @param list<string> $uniqueBy
     * @param list<string> $update
     */
    public function compileUpsert(string $table, array $columns, array $uniqueBy, array $update, int $rowCount = 1): string;

    /**
     * کامپایل UPDATE از اجزا + مقادیر.
     *
     * @param array<string, mixed> $components
     * @param array<string, mixed> $values
     */
    public function compileUpdate(array $components, array $values): string;

    /**
     * کامپایل DELETE از اجزا.
     *
     * @param array<string, mixed> $components
     */
    public function compileDelete(array $components): string;

    public function compileTruncate(string $table): string;

    /**
     * placeholders: `?, ?, ?`
     *
     * @param array<int|string, mixed> $values
     */
    public function parameterize(array $values): string;
}
