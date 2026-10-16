<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Grammars;

/**
 * تَوان | Tavaan — Database
 * MySqlGrammar — SQL مخصوص MySQL/MariaDB (DOC-047 §5).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class MySqlGrammar extends Grammar
{
    public function driver(): string
    {
        return 'mysql';
    }

    public function wrapValue(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        return '`' . \str_replace('`', '``', $value) . '`';
    }

    /** @param array<string, mixed> $config */
    public function dsn(array $config): string
    {
        $socket = (string) ($config['unix_socket'] ?? '');
        if ($socket !== '') {
            return \sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $config['database'] ?? '', $config['charset'] ?? 'utf8mb4');
        }

        return \sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 3306),
            $config['database'] ?? '',
            $config['charset'] ?? 'utf8mb4',
        );
    }

    protected function sharedLockClause(): string
    {
        return 'lock in share mode';
    }

    protected function supportsUpdateLimit(): bool
    {
        return true;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $uniqueBy
     * @param list<string> $update
     */
    public function compileUpsert(string $table, array $columns, array $uniqueBy, array $update, int $rowCount = 1): string
    {
        $rowPlaceholder = '(' . $this->parameterize(\array_fill_keys($columns, null)) . ')';

        $sql = 'insert into ' . $this->wrapTable($table)
            . ' (' . $this->columnize($columns) . ') values '
            . \implode(', ', \array_fill(0, \max(1, $rowCount), $rowPlaceholder));

        return $sql . ' on duplicate key update ' . \implode(', ', $this->upsertSets($columns, $uniqueBy, $update));
    }

    protected function upsertValueReference(string $wrappedColumn): string
    {
        return 'values(' . $wrappedColumn . ')';
    }

    /** @param list<string> $uniqueBy */
    protected function upsertConflictClause(array $uniqueBy): string
    {
        return 'on conflict (' . $this->columnize($uniqueBy) . ')';   // در mysql استفاده نمی‌شود
    }

    /** @param array<string, mixed> $components */
    public function compileDelete(array $components): string
    {
        if (($components['joins'] ?? []) === []) {
            return parent::compileDelete($components);
        }

        // DELETE t FROM t join … (سبک MySQL)
        $from = $components['from'];
        $table = $from['as'] !== null ? $from['table'] . ' as ' . $from['as'] : $from['table'];
        $wrapped = $this->wrapTable($table);

        $sql = 'delete ' . ($from['as'] !== null ? $this->wrapValue($from['as']) : $wrapped)
            . ' from ' . $wrapped . ' ' . $this->compileJoins($components);

        if (($components['wheres'] ?? []) !== []) {
            $sql .= ' ' . $this->compileWhereSection($components);
        }

        return $sql;
    }
}
