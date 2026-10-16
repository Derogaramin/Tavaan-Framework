<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Schema\Grammars;

use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Engine\Database\Schema\ColumnDefinition;
use Tavaan\Engine\Database\Schema\ForeignKeyDefinition;

/**
 * تَوان | Tavaan — Schema
 * SchemaGrammar — کامپایل Blueprint به DDL (پایهٔ اشتراکی — DOC-048 §4).
 *
 * خروجی هر compile فهرستی از statements است (ترتیب اجرا قطعی).
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
abstract class SchemaGrammar
{
    public function __construct(protected readonly string $tablePrefix = '')
    {
    }

    /** @return string mysql|pgsql|sqlite */
    abstract public function driver(): string;

    abstract public function wrap(string $value): string;

    public function prefix(): string
    {
        return $this->tablePrefix;
    }

    public function prefixedTable(string $table): string
    {
        return $this->tablePrefix . $table;
    }

    // ─── CREATE / ALTER / DROP ────────────────────────────────────────────

    /**
     * @param array<string, mixed> $config تنظیمات اتصال (engine/charset برای mysql)
     *
     * @return list<string>
     */
    public function compileCreate(Blueprint $blueprint, array $config = []): array
    {
        $parts = [];

        $primaryColumns = [];
        foreach ($blueprint->getColumns() as $column) {
            $parts[] = $this->compileColumn($column);
            if ($column->primary && !$column->autoIncrement) {
                $primaryColumns[] = $column->name;
            }
        }

        if ($primaryColumns !== []) {
            $parts[] = 'primary key (' . $this->columnize($primaryColumns) . ')';
        }

        foreach ($blueprint->getCommands() as $command) {
            $compiled = $this->compileCreateCommand($command, $blueprint);
            if ($compiled !== null) {
                $parts[] = $compiled;
            }
        }

        // uniqueهای ستونی (column->unique)
        foreach ($blueprint->getColumns() as $column) {
            if ($column->unique) {
                $parts[] = $this->columnUniqueSql($blueprint->table . '_' . $column->name . '_unique', $column->name);
            }
        }

        $sql = 'create table ' . $this->wrap($this->prefixedTable($blueprint->table))
            . " (\n  " . \implode(",\n  ", $parts) . "\n)" . $this->createSuffix($config);

        $statements = [$sql];

        // ایندکس‌های جدا (index/fulltext و index ستونی)
        foreach ($blueprint->getColumns() as $column) {
            if ($column->index) {
                $statements[] = $this->createIndexSql(
                    $blueprint->table . '_' . $column->name . '_index',
                    $blueprint->table,
                    [$column->name],
                    false,
                );
            }
        }
        if ($this->separateIndexStatements()) {
            foreach ($blueprint->getCommands() as $command) {
                if (\in_array($command['type'], ['index', 'fulltext', 'unique'], true)) {
                    $statements[] = $this->createIndexSql(
                        (string) $command['name'],
                        $blueprint->table,
                        $command['columns'],
                        $command['type'] === 'unique',
                        $command['type'] === 'fulltext',
                    );
                }
            }
        }

        return $statements;
    }

    /** @param array<string, mixed> $config */
    protected function createSuffix(array $config): string
    {
        return '';
    }

    /** آیا index/unique/fulltext با CREATE INDEX جدا صادر شوند (pg/sqlite) یا inline (mysql)؟ */
    protected function separateIndexStatements(): bool
    {
        return true;
    }

    /**
     * دستور درون CREATE (constraint خطی) یا null اگر statement جداست.
     *
     * @param array<string, mixed> $command
     */
    protected function compileCreateCommand(array $command, Blueprint $blueprint): ?string
    {
        return match ($command['type']) {
            'primary' => 'primary key (' . $this->columnize($command['columns']) . ')',
            'foreign' => $this->foreignKeySql($command['fk'], $blueprint->table),
            // unique/index/fulltext → statement جدا (createIndexSql)
            default => null,
        };
    }

    /**
     * @param list<string> $columns
     */
    protected function uniqueConstraintSql(string $name, array $columns): string
    {
        return 'constraint ' . $this->wrap($name) . ' unique (' . $this->columnize($columns) . ')';
    }

    /** unique ستونی درون CREATE (hook برای سبک MySQL). */
    protected function columnUniqueSql(string $name, string $column): string
    {
        return $this->uniqueConstraintSql($name, [$column]);
    }

    /** SQL محدودیت foreign key (in-line در CREATE TABLE). */
    protected function foreignKeySql(ForeignKeyDefinition $fk, string $table): string
    {
        return 'constraint ' . $this->wrap($fk->name)
            . ' foreign key (' . $this->wrap($fk->column) . ')'
            . ' references ' . $this->wrap($this->prefixedTable($fk->getOnTable()))
            . ' (' . $this->columnize($fk->getReferences()) . ')'
            . ' on delete ' . \strtoupper($fk->getOnDelete())
            . ' on update ' . \strtoupper($fk->getOnUpdate());
    }

    /**
     * @return list<string>
     */
    public function compileAlter(Blueprint $blueprint): array
    {
        $statements = [];
        $table = $this->wrap($this->prefixedTable($blueprint->table));

        // rename column (قبل از بقیه)
        foreach ($blueprint->getRenamedColumns() as $from => $to) {
            $statements[] = 'alter table ' . $table . ' rename column ' . $this->wrap((string) $from) . ' to ' . $this->wrap($to);
        }

        // add column (یکی‌یکی برای سازگاری SQLite)
        foreach ($blueprint->getColumns() as $column) {
            $statements[] = 'alter table ' . $table . ' add column ' . $this->compileColumn($column, forAlter: true);
        }

        // drop column
        foreach ($blueprint->getDroppedColumns() as $column) {
            $statements[] = 'alter table ' . $table . ' drop column ' . $this->wrap($column);
        }

        foreach ($blueprint->getCommands() as $command) {
            $statements = \array_merge($statements, $this->compileAlterCommand($command, $blueprint));
        }

        return $statements;
    }

    /**
     * @param array<string, mixed> $command
     *
     * @return list<string>
     */
    protected function compileAlterCommand(array $command, Blueprint $blueprint): array
    {
        $table = $this->wrap($this->prefixedTable($blueprint->table));

        return match ($command['type']) {
            'index', 'fulltext', 'unique' => [$this->createIndexSql(
                (string) $command['name'],
                $blueprint->table,
                $command['columns'],
                $command['type'] === 'unique',
                $command['type'] === 'fulltext',
            )],
            'primary' => ['alter table ' . $table . ' add primary key (' . $this->columnize($command['columns']) . ')'],
            'foreign' => $this->supportsAlterForeignKeys()
                ? ['alter table ' . $table . ' add ' . $this->foreignKeySql($command['fk'], $blueprint->table)]
                : [],
            'dropIndex', 'dropUnique', 'dropPrimary', 'dropForeign' => $this->compileDropConstraint($command, $blueprint),
            default => [],
        };
    }

    protected function supportsAlterForeignKeys(): bool
    {
        return true;
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

    /**
     * @param list<string> $columns
     */
    protected function createIndexSql(string $name, string $table, array $columns, bool $unique = false, bool $fulltext = false): string
    {
        $type = $unique ? 'unique index' : ($fulltext ? $this->fulltextIndexKeyword() : 'index');

        return 'create ' . $type . ' ' . $this->wrap($name)
            . ' on ' . $this->wrap($this->prefixedTable($table))
            . ' (' . $this->columnize($columns) . ')';
    }

    protected function fulltextIndexKeyword(): string
    {
        return 'index';
    }

    public function compileDrop(string $table): string
    {
        return 'drop table ' . $this->wrap($this->prefixedTable($table));
    }

    public function compileDropIfExists(string $table): string
    {
        return 'drop table if exists ' . $this->wrap($this->prefixedTable($table));
    }

    public function compileRename(string $from, string $to): string
    {
        return 'alter table ' . $this->wrap($this->prefixedTable($from)) . ' rename to ' . $this->wrap($this->prefixedTable($to));
    }

    // ─── ستون ─────────────────────────────────────────────────────────────

    public function compileColumn(ColumnDefinition $column, bool $forAlter = false): string
    {
        $sql = $this->wrap($column->name) . ' ' . $this->compileType($column);

        $sql .= $this->compileModifiers($column, $forAlter);

        return $sql;
    }

    protected function compileType(ColumnDefinition $column): string
    {
        $method = 'type' . \ucfirst($column->type);
        if (!\method_exists($this, $method)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported column type [%s] for driver [%s].', $column->type, $this->driver()));
        }

        return $this->{$method}($column);
    }

    protected function compileModifiers(ColumnDefinition $column, bool $forAlter): string
    {
        $sql = '';

        if ($column->unsigned && $this->supportsUnsigned()) {
            $sql .= $this->unsignedSuffix($column);
        }

        if ($column->autoIncrement) {
            $sql .= $this->autoIncrementSuffix($column);
        } else {
            $sql .= $column->nullable ? ' null' : ' not null';
        }

        if ($column->useCurrent) {
            $sql .= ' default ' . $this->currentTimestamp($column);
        } elseif ($column->hasDefault) {
            $sql .= ' default ' . $this->compileDefault($column);
        }

        if ($column->comment !== null && $this->supportsColumnComment()) {
            $escaped = \str_replace("'", "''", $column->comment);
            $sql .= " comment '{$escaped}'";
        }

        if ($forAlter && $column->after !== null && $this->supportsAfter()) {
            $sql .= ' after ' . $this->wrap($column->after);
        }

        return $sql;
    }

    protected function compileDefault(ColumnDefinition $column): string
    {
        $default = $column->default;

        return match (true) {
            $default === null => 'null',
            \is_bool($default) => $this->booleanLiteral($default),
            \is_int($default) => (string) $default,
            \is_float($default) => (string) $default,
            $default instanceof \UnitEnum => "'" . $this->escapeEnumValue($default instanceof \BackedEnum ? (string) $default->value : $default->name) . "'",
            default => "'" . $this->escapeEnumValue((string) $default) . "'",
        };
    }

    protected function booleanLiteral(bool $value): string
    {
        return $value ? '1' : '0';
    }

    protected function escapeEnumValue(string $value): string
    {
        return \str_replace("'", "''", $value);
    }

    protected function currentTimestamp(ColumnDefinition $column): string
    {
        $precision = $column->precision ?? 0;

        return $precision > 0 ? 'current_timestamp(' . $precision . ')' : 'current_timestamp';
    }

    protected function supportsUnsigned(): bool
    {
        return false;
    }

    protected function unsignedSuffix(ColumnDefinition $column): string
    {
        return '';
    }

    protected function autoIncrementSuffix(ColumnDefinition $column): string
    {
        return ' primary key autoincrement';
    }

    protected function supportsColumnComment(): bool
    {
        return false;
    }

    protected function supportsAfter(): bool
    {
        return false;
    }

    // ─── انواع (type map) ─────────────────────────────────────────────────

    abstract protected function typeString(ColumnDefinition $c): string;

    abstract protected function typeChar(ColumnDefinition $c): string;

    abstract protected function typeText(ColumnDefinition $c): string;

    abstract protected function typeMediumText(ColumnDefinition $c): string;

    abstract protected function typeLongText(ColumnDefinition $c): string;

    abstract protected function typeInteger(ColumnDefinition $c): string;

    abstract protected function typeBigInteger(ColumnDefinition $c): string;

    abstract protected function typeSmallInteger(ColumnDefinition $c): string;

    abstract protected function typeTinyInteger(ColumnDefinition $c): string;

    abstract protected function typeBoolean(ColumnDefinition $c): string;

    abstract protected function typeDecimal(ColumnDefinition $c): string;

    abstract protected function typeDouble(ColumnDefinition $c): string;

    abstract protected function typeFloat(ColumnDefinition $c): string;

    abstract protected function typeDate(ColumnDefinition $c): string;

    abstract protected function typeDateTime(ColumnDefinition $c): string;

    abstract protected function typeTimestamp(ColumnDefinition $c): string;

    abstract protected function typeTime(ColumnDefinition $c): string;

    abstract protected function typeYear(ColumnDefinition $c): string;

    abstract protected function typeJson(ColumnDefinition $c): string;

    abstract protected function typeJsonb(ColumnDefinition $c): string;

    abstract protected function typeEnum(ColumnDefinition $c): string;

    abstract protected function typeSet(ColumnDefinition $c): string;

    abstract protected function typeBlob(ColumnDefinition $c): string;

    abstract protected function typeBinary(ColumnDefinition $c): string;

    protected function typeUlid(ColumnDefinition $c): string
    {
        return 'char(26)';
    }

    protected function typeUuid(ColumnDefinition $c): string
    {
        return 'char(36)';
    }

    // ─── Introspection ────────────────────────────────────────────────────

    /** @return array{0: string, 1: list<mixed>} sql + bindings */
    abstract public function compileTableExists(string $table): array;

    /** @return array{0: string, 1: list<mixed>} */
    abstract public function compileGetTables(): array;

    /** @return array{0: string, 1: list<mixed>} */
    abstract public function compileGetColumns(string $table): array;

    /** @return list<string> */
    abstract public function compileDropAllTables(): array;

    /**
     * @param list<string> $columns
     */
    protected function columnize(array $columns): string
    {
        return \implode(', ', \array_map(fn (string $c): string => $this->wrap($c), $columns));
    }
}
