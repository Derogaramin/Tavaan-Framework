<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Schema\Grammars;

use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\ColumnDefinition;

/**
 * تَوان | Tavaan — Schema
 * PostgresSchemaGrammar — DDL مخصوص PostgreSQL (DOC-048 §4.1).
 *
 * تفاوت‌ها با MySQL: json→jsonb، enum→varchar+check، serial برای auto-increment.
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
final class PostgresSchemaGrammar extends SchemaGrammar
{
    public function __construct(
        string $tablePrefix = '',
        private readonly string $schema = 'public',
    ) {
        parent::__construct($tablePrefix);
    }

    public function driver(): string
    {
        return 'pgsql';
    }

    public function wrap(string $value): string
    {
        if ($value === '*') {
            return '*';
        }

        $segments = \explode('.', $value);
        $wrapped = [];
        foreach ($segments as $segment) {
            $wrapped[] = '"' . \str_replace('"', '""', $segment) . '"';
        }

        return \implode('.', $wrapped);
    }

    protected function booleanLiteral(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    protected function autoIncrementSuffix(ColumnDefinition $column): string
    {
        return ' primary key';
    }

    protected function compileType(ColumnDefinition $column): string
    {
        // serial/bigserial برای auto-increment
        if ($column->autoIncrement) {
            return $column->type === 'bigInteger' ? 'bigserial' : 'serial';
        }

        return parent::compileType($column);
    }

    protected function fulltextIndexKeyword(): string
    {
        return 'index';
    }

    /**
     * fulltext در PG → GIN روی to_tsvector (ساده: ستون اول).
     *
     * @param list<string> $columns
     */
    protected function createIndexSql(string $name, string $table, array $columns, bool $unique = false, bool $fulltext = false): string
    {
        if ($fulltext) {
            $expr = \implode(" || ' ' || ", \array_map(
                fn (string $c): string => 'coalesce(' . $this->wrap($c) . "::text, '')",
                $columns,
            ));

            return 'create index ' . $this->wrap($name)
                . ' on ' . $this->wrap($this->prefixedTable($table))
                . " using gin (to_tsvector('simple', " . $expr . '))';
        }

        return parent::createIndexSql($name, $table, $columns, $unique, $fulltext);
    }

    /**
     * @param array<string, mixed> $command
     *
     * @return list<string>
     */
    protected function compileDropConstraint(array $command, Blueprint $blueprint): array
    {
        $table = $this->wrap($this->prefixedTable($blueprint->table));
        $name = $this->wrap((string) $command['name']);

        return match ($command['type']) {
            'dropIndex' => ['drop index if exists ' . $name],
            'dropUnique' => ['alter table ' . $table . ' drop constraint if exists ' . $name],
            'dropPrimary' => ['alter table ' . $table . ' drop constraint if exists ' . $this->wrap($blueprint->table . '_pkey')],
            'dropForeign' => ['alter table ' . $table . ' drop constraint if exists ' . $name],
            default => [],
        };
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
        return 'smallint';
    }

    protected function typeBoolean(ColumnDefinition $c): string
    {
        return 'boolean';
    }

    protected function typeDecimal(ColumnDefinition $c): string
    {
        return 'decimal(' . ($c->precision ?? 18) . ',' . ($c->scale ?? 4) . ')';
    }

    protected function typeDouble(ColumnDefinition $c): string
    {
        return 'double precision';
    }

    protected function typeFloat(ColumnDefinition $c): string
    {
        return 'real';
    }

    protected function typeDate(ColumnDefinition $c): string
    {
        return 'date';
    }

    protected function typeDateTime(ColumnDefinition $c): string
    {
        return 'timestamp(' . ($c->precision ?? 0) . ') without time zone';
    }

    protected function typeTimestamp(ColumnDefinition $c): string
    {
        return 'timestamp(' . ($c->precision ?? 0) . ') without time zone';
    }

    protected function typeTime(ColumnDefinition $c): string
    {
        return 'time(0) without time zone';
    }

    protected function typeYear(ColumnDefinition $c): string
    {
        return 'smallint';
    }

    protected function typeJson(ColumnDefinition $c): string
    {
        return 'jsonb';   // PG: jsonb بهتر (DOC-048 §4.1)
    }

    protected function typeJsonb(ColumnDefinition $c): string
    {
        return 'jsonb';
    }

    protected function typeEnum(ColumnDefinition $c): string
    {
        // varchar + check (بدون CREATE TYPE — مهاجرت‌پذیرتر)
        return 'varchar(255) check (' . $this->wrap($c->name) . ' in (' . $this->enumValues($c) . '))';
    }

    protected function typeSet(ColumnDefinition $c): string
    {
        return 'text';
    }

    protected function typeBlob(ColumnDefinition $c): string
    {
        return 'bytea';
    }

    protected function typeBinary(ColumnDefinition $c): string
    {
        return 'bytea';
    }

    protected function typeUuid(ColumnDefinition $c): string
    {
        return 'uuid';
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
            'select table_name from information_schema.tables where table_schema = ? and table_name = ?',
            [$this->schema, $this->prefixedTable($table)],
        ];
    }

    public function compileGetTables(): array
    {
        return [
            'select table_name as name from information_schema.tables where table_schema = ? and table_type = \'BASE TABLE\' order by table_name',
            [$this->schema],
        ];
    }

    public function compileGetColumns(string $table): array
    {
        return [
            'select column_name as name, data_type as type, is_nullable as nullable, column_default as default, \'\' as extra from information_schema.columns where table_schema = ? and table_name = ? order by ordinal_position',
            [$this->schema, $this->prefixedTable($table)],
        ];
    }

    public function compileDropAllTables(): array
    {
        return ['drop schema if exists "' . $this->schema . '" cascade', 'create schema "' . $this->schema . '"'];
    }
}
