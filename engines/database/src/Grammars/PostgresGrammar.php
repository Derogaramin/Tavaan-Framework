<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Grammars;

/**
 * تَوان | Tavaan — Database
 * PostgresGrammar — SQL مخصوص PostgreSQL (DOC-047 §5).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class PostgresGrammar extends Grammar
{
    public function __construct(
        string $tablePrefix = '',
        private readonly string $searchPath = 'public',
    ) {
        parent::__construct($tablePrefix);
    }

    public function driver(): string
    {
        return 'pgsql';
    }

    public function wrapValue(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        return '"' . \str_replace('"', '""', $value) . '"';
    }

    /** @param array<string, mixed> $config */
    public function dsn(array $config): string
    {
        return \sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;options=--search_path=%s',
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 5432),
            $config['database'] ?? '',
            (string) ($config['schema'] ?? $this->searchPath),
        );
    }

    protected function sharedLockClause(): string
    {
        return 'for share';
    }

    /**
     * INSERT ... RETURNING برای lastInsertId بدون sequence جدا.
     *
     * @param list<string> $columns
     */
    public function compileInsertGetId(string $table, array $columns, string $sequence): string
    {
        $rowPlaceholder = '(' . $this->parameterize(\array_fill_keys($columns, null)) . ')';

        return 'insert into ' . $this->wrapTable($table)
            . ' (' . $this->columnize($columns) . ') values ' . $rowPlaceholder
            . ' returning ' . $this->wrapValue($sequence);
    }

    protected function upsertValueReference(string $wrappedColumn): string
    {
        return 'excluded.' . $wrappedColumn;
    }

    /** @param list<string> $uniqueBy */
    protected function upsertConflictClause(array $uniqueBy): string
    {
        return 'on conflict (' . $this->columnize($uniqueBy) . ')';
    }
}
