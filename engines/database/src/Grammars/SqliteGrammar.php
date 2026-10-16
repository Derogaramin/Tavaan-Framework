<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Grammars;

/**
 * تَوان | Tavaan — Database
 * SqliteGrammar — SQL مخصوص SQLite (DOC-047 §5).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class SqliteGrammar extends Grammar
{
    public function driver(): string
    {
        return 'sqlite';
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
        return 'sqlite:' . (string) ($config['database'] ?? ':memory:');
    }

    /** SQLite قفل سطری ندارد (کل DB در تراکنش قفل است) → clause خالی. */
    protected function compileLock(array $components): string
    {
        return '';
    }

    /** در SQLite اگر offset بدون limit باشد، limit باید -1 باشد. */
    protected function compileLimitOffset(array $components): string
    {
        $limit = $components['limit'] ?? null;
        $offset = $components['offset'] ?? null;

        if ($limit === null && $offset !== null) {
            return 'limit -1 offset ' . \max(0, (int) $offset);
        }

        return parent::compileLimitOffset($components);
    }

    public function compileTruncate(string $table): string
    {
        return 'delete from ' . $this->wrapTable($table);
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
