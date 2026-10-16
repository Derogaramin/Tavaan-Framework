<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Schema\Grammars;

use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\ColumnDefinition;

/**
 * تَوان | Tavaan — Schema
 * SqliteSchemaGrammar — DDL مخصوص SQLite (DOC-048 §4.1).
 *
 * تفاوت‌ها: decimal→numeric، enum→text+check، integer primary key autoincrement.
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
final class SqliteSchemaGrammar extends SchemaGrammar
{
    public function driver(): string
    {
        return 'sqlite';
    }

    public function wrap(string $value): string
    {
        if ($value === '*') {
            return '*';
        }

        $segments = \explode('.', $value);
        $wrapped = [];
        foreach ($segments as $segment) {
            $quoted = \str_replace('"', '""', $segment);
            $wrapped[] = '"' . $quoted . '"';
        }

        return \implode('.', $wrapped);
    }

    protected function autoIncrementSuffix(ColumnDefinition $column): string
    {
        return ' primary key autoincrement';
    }

    protected function compileType(ColumnDefinition $column): string
    {
        // rowid alias فقط با INTEGER (نه BIGINT)
        if ($column->autoIncrement) {
            return 'integer';
        }

        return parent::compileType($column);
    }

    protected function supportsAlterForeignKeys(): bool
    {
        return false;   // SQLite: ADD CONSTRAINT پشتیبانی نمی‌شود
    }

    /**
     * @param array<string, mixed> $command
     *
     * @return list<string>
     */
    protected function compileDropConstraint(array $command, Blueprint $blueprint): array
    {
        // SQLite فقط DROP INDEX دارد
        return \in_array($command['type'], ['dropIndex', 'dropUnique'], true)
            ? ['drop index if exists ' . $this->wrap((string) $command['name'])]
            : [];
    }

    // ─── انواع ────────────────────────────────────────────────────────────

    protected function typeString(ColumnDefinition $c): string
    {
        return 'varchar(' . ($c->length ?? 255) . ')';
    }

    protected function typeChar(ColumnDefinition $c): string
    {
        return 'char(' . ($c->length ?? 255) . ')';
    }

    protected function typeText(ColumnDefinition $c): string
    {
        return 'text';
    }

    protected function typeMediumText(ColumnDefinition $c): string
    {
        return 'text';
    }

    protected function typeLongText(ColumnDefinition $c): string
    {
        return 'text';
    }

    protected function typeInteger(ColumnDefinition $c): string
    {
        return 'integer';
    }

    protected function typeBigInteger(ColumnDefinition $c): string
    {
        return 'bigint';
    }

    protected function typeSmallInteger(ColumnDefinition $c): string
    {
        return 'smallint';
    }

    protected function typeTinyInteger(ColumnDefinition $c): string
    {
        return 'tinyint';
    }

    protected function typeBoolean(ColumnDefinition $c): string
    {
        return 'tinyint(1)';
    }

    protected function typeDecimal(ColumnDefinition $c): string
    {
        return 'numeric';
    }

    protected function typeDouble(ColumnDefinition $c): string
    {
        return 'float';
    }

    protected function typeFloat(ColumnDefinition $c): string
    {
        return 'float';
    }

    protected function typeDate(ColumnDefinition $c): string
    {
        return 'date';
    }

    protected function typeDateTime(ColumnDefinition $c): string
    {
        return 'datetime';
    }

    protected function typeTimestamp(ColumnDefinition $c): string
    {
        return 'datetime';
    }

    protected function typeTime(ColumnDefinition $c): string
    {
        return 'time';
    }

    protected function typeYear(ColumnDefinition $c): string
    {
        return 'integer';
    }

    protected function typeJson(ColumnDefinition $c): string
    {
        return 'text';
    }

    protected function typeJsonb(ColumnDefinition $c): string
    {
        return 'text';
    }

    protected function typeEnum(ColumnDefinition $c): string
    {
        return 'text check (' . $this->wrap($c->name) . ' in (' . $this->enumValues($c) . '))';
    }

    protected function typeSet(ColumnDefinition $c): string
    {
        return 'text';
    }

    protected function typeBlob(ColumnDefinition $c): string
    {
        return 'blob';
    }

    protected function typeBinary(ColumnDefinition $c): string
    {
        return 'blob';
    }

    private function enumValues(ColumnDefinition $c): string
    {
        $values = \array_map(fn (string $v): string => "'" . $this->escapeEnumValue($v) . "'", $c->enumValues ?? []);

        return \implode(', ', $values);
    }

    // ─── Introspection ────────────────────────────────────────────────────

    public function compileTableExists(string $table): array
    {
        return [
            "select name from sqlite_master where type = 'table' and name = ?",
            [$this->prefixedTable($table)],
        ];
    }

    public function compileGetTables(): array
    {
        return [
            "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name",
            [],
        ];
    }

    public function compileGetColumns(string $table): array
    {
        // PRAGMA جای bind نمی‌پذیرد → نام جدول با allowlist اعتبارسنجی و quote می‌شود
        $name = $this->prefixedTable($table);
        if (\preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid table name for pragma: ' . $table);
        }

        return ['pragma table_info("' . $name . '")', []];
    }

    public function compileDropAllTables(): array
    {
        return ['pragma foreign_keys = off'];
    }
}
