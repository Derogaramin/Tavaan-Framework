<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Schema;

use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Core\Contracts\Database\SchemaBuilderInterface;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\ConnectionManager;
use Tavaan\Engine\Database\Schema\Grammars\MySqlSchemaGrammar;
use Tavaan\Engine\Database\Schema\Grammars\PostgresSchemaGrammar;
use Tavaan\Engine\Database\Schema\Grammars\SchemaGrammar;
use Tavaan\Engine\Database\Schema\Grammars\SqliteSchemaGrammar;

/**
 * تَوان | Tavaan — Schema
 * SchemaBuilder — نقطهٔ ورود DDL (create/table/drop/hasTable/… — DOC-048 §2).
 *
 * @api 1.0
 * @see docs/03-engines/schema.md
 */
final class SchemaBuilder implements SchemaBuilderInterface
{
    private ?Connection $connection = null;

    public function __construct(private readonly ConnectionManager $manager)
    {
    }

    /** انتخاب اتصال (پس از آن همهٔ عملیات روی همان اتصال است). */
    public function connection(?string $name = null): static
    {
        $clone = clone $this;
        $connection = $name === null ? $this->manager->connection() : $this->manager->connection($name);
        $clone->connection = $connection instanceof Connection ? $connection : null;

        return $clone;
    }

    private function db(): Connection
    {
        return $this->connection ??= ($this->manager->connection() instanceof Connection
            ? $this->manager->connection()
            : throw new \LogicException('Schema requires a full Connection.'));
    }

    private function grammar(): SchemaGrammar
    {
        $db = $this->db();
        $prefix = $db->grammar()->prefix();

        return match ($db->driver()) {
            'mysql' => new MySqlSchemaGrammar($prefix),
            'pgsql' => new PostgresSchemaGrammar($prefix, (string) ($db->configValue('schema', 'public'))),
            default => new SqliteSchemaGrammar($prefix),
        };
    }

    /** @param \Closure(Blueprint): void $callback */
    public function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table, creating: true);
        $callback($blueprint);

        $grammar = $this->grammar();
        foreach ($grammar->compileCreate($blueprint, $this->db()->config()) as $sql) {
            $this->db()->statement($sql);
        }
        $blueprint->freeze();
    }

    /** @param \Closure(Blueprint): void $callback */
    public function table(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        foreach ($this->grammar()->compileAlter($blueprint) as $sql) {
            $this->db()->statement($sql);
        }
        $blueprint->freeze();
    }

    public function drop(string $table): void
    {
        $this->db()->statement($this->grammar()->compileDrop($table));
    }

    public function dropIfExists(string $table): void
    {
        $this->db()->statement($this->grammar()->compileDropIfExists($table));
    }

    public function rename(string $from, string $to): void
    {
        $this->db()->statement($this->grammar()->compileRename($from, $to));
    }

    public function hasTable(string $table): bool
    {
        [$sql, $bindings] = $this->grammar()->compileTableExists($table);

        return $this->db()->select($sql, $bindings) !== [];
    }

    public function hasColumn(string $table, string $column): bool
    {
        foreach ($this->getColumns($table) as $col) {
            if (\strtolower((string) ($col['name'] ?? '')) === \strtolower($column)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    public function getColumns(string $table): array
    {
        [$sql, $bindings] = $this->grammar()->compileGetColumns($table);
        $rows = $this->db()->select($sql, $bindings);

        return $this->normalizeColumns($rows);
    }

    /** @return list<string> */
    public function getTables(): array
    {
        [$sql, $bindings] = $this->grammar()->compileGetTables();
        $rows = $this->db()->select($sql, $bindings);

        $prefix = $this->grammar()->prefix();
        $names = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? \array_values($row)[0] ?? '');
            if ($prefix !== '' && \str_starts_with($name, $prefix)) {
                $name = \substr($name, \strlen($prefix));
            }
            $names[] = $name;
        }

        return $names;
    }

    public function getColumnType(string $table, string $column): ?string
    {
        foreach ($this->getColumns($table) as $col) {
            if (\strtolower((string) ($col['name'] ?? '')) === \strtolower($column)) {
                return (string) ($col['type'] ?? '');
            }
        }

        return null;
    }

    /**
     * معکوس‌مهندسی ساختار جدول (DOC-048 §5).
     *
     * @return array<string, mixed>
     */
    public function introspect(string $table): array
    {
        $columns = $this->getColumns($table);

        $indexes = [];
        if ($this->db()->driver() === 'sqlite') {
            $name = $this->grammar()->prefixedTable($table);
            if (\preg_match('/^[A-Za-z0-9_]+$/', $name) === 1) {
                $indexes = $this->db()->select('pragma index_list("' . $name . '")');
            }
        } else {
            $indexes = $this->db()->driver() === 'mysql'
                ? $this->db()->select(
                    'select index_name as name, column_name as column, non_unique from information_schema.statistics where table_schema = database() and table_name = ? order by seq_in_index',
                    [$this->grammar()->prefixedTable($table)],
                )
                : $this->db()->select(
                    'select indexname as name, indexdef as definition from pg_indexes where schemaname = ? and tablename = ?',
                    [(string) $this->db()->configValue('schema', 'public'), $this->grammar()->prefixedTable($table)],
                );
        }

        return ['table' => $table, 'columns' => $columns, 'indexes' => $indexes];
    }

    /** حذف همهٔ جداول (migrate:fresh). */
    public function dropAllTables(): void
    {
        $grammar = $this->grammar();

        foreach ($grammar->compileDropAllTables() as $sql) {
            $this->db()->statement($sql);
        }

        if ($this->db()->driver() === 'sqlite') {
            foreach ($this->getTables() as $table) {
                $this->db()->statement('drop table if exists "' . \str_replace('"', '""', $table) . '"');
            }
            $this->db()->statement('pragma foreign_keys = on');

            return;
        }

        foreach ($this->getTables() as $table) {
            $this->drop($table);
        }

        if ($this->db()->driver() === 'mysql') {
            $this->db()->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * یکسان‌سازی خروجی introspection ستون‌ها بین درایورها.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeColumns(array $rows): array
    {
        $driver = $this->db()->driver();
        $out = [];

        foreach ($rows as $row) {
            if ($driver === 'sqlite') {
                // pragma table_info: cid,name,type,notnull,dflt_value,pk
                $out[] = [
                    'name'     => (string) ($row['name'] ?? ''),
                    'type'     => \strtolower((string) ($row['type'] ?? '')),
                    'nullable' => (int) ($row['notnull'] ?? 0) === 0,
                    'default'  => $row['dflt_value'] ?? null,
                    'primary'  => (int) ($row['pk'] ?? 0) > 0,
                ];
                continue;
            }

            $out[] = [
                'name'     => (string) ($row['name'] ?? ''),
                'type'     => \strtolower((string) ($row['type'] ?? '')),
                'nullable' => \strtoupper((string) ($row['nullable'] ?? 'NO')) === 'YES',
                'default'  => $row['default'] ?? null,
                'primary'  => $driver === 'mysql' && \str_contains((string) ($row['extra'] ?? ''), 'auto_increment'),
            ];
        }

        return $out;
    }
}
