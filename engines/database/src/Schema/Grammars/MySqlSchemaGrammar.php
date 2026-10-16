<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Schema\Grammars;

use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\ColumnDefinition;

/**
 * تَوان | Tavaan — Schema
 * MySqlSchemaGrammar — DDL مخصوص MySQL/MariaDB (DOC-048 §4.1).
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
final class MySqlSchemaGrammar extends SchemaGrammar
{
    public function driver(): string
    {
        return 'mysql';
    }

    public function wrap(string $value): string
    {
        if ($value === '*') {
            return '*';
        }

        $segments = \explode('.', $value);
        $wrapped = [];
        foreach ($segments as $segment) {
            $wrapped[] = '`' . \str_replace('`', '``', $segment) . '`';
        }

        return \implode('.', $wrapped);
    }

    /** @param array<string, mixed> $config */
    protected function createSuffix(array $config): string
    {
        $engine = (string) ($config['engine'] ?? 'InnoDB');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $collation = (string) ($config['collation'] ?? 'utf8mb4_0900_ai_ci');

        return ' engine=' . $engine . ' default charset=' . $charset . ' collate=' . $collation;
    }

    protected function supportsUnsigned(): bool
    {
        return true;
    }

    protected function unsignedSuffix(ColumnDefinition $column): string
    {
        return \in_array($column->type, ['integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'decimal', 'double', 'float'], true)
            ? ' unsigned'
            : '';
    }

    protected function autoIncrementSuffix(ColumnDefinition $column): string
    {
        return ' not null auto_increment primary key';
    }

    protected function supportsColumnComment(): bool
    {
        return true;
    }

    protected function supportsAfter(): bool
    {
        return true;
    }

    protected function fulltextIndexKeyword(): string
    {
        return 'fulltext index';
    }

    /**
     * در CREATE، index/unique/fulltext به‌صورت KEY درون جدول (سبک MySQL).
     *
     * @param array<string, mixed> $command
     */
    protected function compileCreateCommand(array $command, Blueprint $blueprint): ?string
    {
        return match ($command['type']) {
            'unique' => 'unique key ' . $this->wrap((string) $command['name']) . ' (' . $this->columnize($command['columns']) . ')',
            'index' => 'key ' . $this->wrap((string) $command['name']) . ' (' . $this->columnize($command['columns']) . ')',
            'fulltext' => 'fulltext key ' . $this->wrap((string) $command['name']) . ' (' . $this->columnize($command['columns']) . ')',
            default => parent::compileCreateCommand($command, $blueprint),
        };
    }

    /**
     * @param list<string> $columns
     */
    protected function createIndexSql(string $name, string $table, array $columns, bool $unique = false, bool $fulltext = false): string
    {
        // در CREATE به‌صورت inline است؛ این متد فقط برای ALTER استفاده می‌شود
        $type = $unique ? 'unique index' : ($fulltext ? 'fulltext index' : 'index');

        return 'create ' . $type . ' ' . $this->wrap($name)
            . ' on ' . $this->wrap($this->prefixedTable($table))
            . ' (' . $this->columnize($columns) . ')';
    }

    /**
     * @param array<string, mixed> $command
     *
     * @return list<string>
     */
    protected function compileAlterCommand(array $command, Blueprint $blueprint): array
    {
        $table = $this->wrap($this->prefixedTable($blueprint->table));

        if (\in_array($command['type'], ['index', 'fulltext', 'unique'], true)) {
            $keyword = match ($command['type']) {
                'unique' => 'unique key ',
                'fulltext' => 'fulltext key ',
                default => 'key ',
            };

            return ['alter table ' . $table . ' add ' . $keyword . $this->wrap((string) $command['name'])
                . ' (' . $this->columnize($command['columns']) . ')'];
        }

        return parent::compileAlterCommand($command, $blueprint);
    }

    /** index/unique/fulltext در CREATE به‌صورت KEY درون جدول است (statement جدا نمی‌خواهد). */
    protected function separateIndexStatements(): bool
    {
        return false;
    }

    protected function columnUniqueSql(string $name, string $column): string
    {
        return 'unique key ' . $this->wrap($name) . ' (' . $this->wrap($column) . ')';
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
            'dropIndex', 'dropUnique' => ['alter table ' . $table . ' drop index ' . $name],
            'dropPrimary' => ['alter table ' . $table . ' drop primary key'],
            'dropForeign' => ['alter table ' . $table . ' drop foreign key ' . $name],
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
        return 'mediumtext';
    }

    protected function typeLongText(ColumnDefinition $c): string
    {
        return 'longtext';
    }

    protected function typeInteger(ColumnDefinition $c): string
    {
        return 'int';
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
        return 'decimal(' . ($c->precision ?? 18) . ',' . ($c->scale ?? 4) . ')';
    }

    protected function typeDouble(ColumnDefinition $c): string
    {
        return 'double';
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
        return 'datetime' . (($c->precision ?? 0) > 0 ? '(' . $c->precision . ')' : '');
    }

    protected function typeTimestamp(ColumnDefinition $c): string
    {
        return 'timestamp' . (($c->precision ?? 0) > 0 ? '(' . $c->precision . ')' : '');
    }

    protected function typeTime(ColumnDefinition $c): string
    {
        return 'time';
    }

    protected function typeYear(ColumnDefinition $c): string
    {
        return 'year';
    }

    protected function typeJson(ColumnDefinition $c): string
    {
        return 'json';
    }

    protected function typeJsonb(ColumnDefinition $c): string
    {
        return 'json';
    }

    protected function typeEnum(ColumnDefinition $c): string
    {
        return 'enum(' . $this->enumValues($c) . ')';
    }

    protected function typeSet(ColumnDefinition $c): string
    {
        return 'set(' . $this->enumValues($c) . ')';
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

        return \implode(',', $values);
    }

    // ─── Introspection ────────────────────────────────────────────────────

    public function compileTableExists(string $table): array
    {
        return [
            'select table_name from information_schema.tables where table_schema = database() and table_name = ?',
            [$this->prefixedTable($table)],
        ];
    }

    public function compileGetTables(): array
    {
        return [
            'select table_name as name from information_schema.tables where table_schema = database() order by table_name',
            [],
        ];
    }

    public function compileGetColumns(string $table): array
    {
        return [
            'select column_name as name, column_type as type, is_nullable as nullable, column_default as default, extra from information_schema.columns where table_schema = database() and table_name = ? order by ordinal_position',
            [$this->prefixedTable($table)],
        ];
    }

    public function compileDropAllTables(): array
    {
        return ['SET FOREIGN_KEY_CHECKS=0'];
    }
}
