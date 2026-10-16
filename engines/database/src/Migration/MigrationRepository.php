<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Migration;

use Tavaan\Engine\Database\Connection;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Migration
 * MigrationRepository — جدول tvn_migrations (ثبت اجراشده‌ها + batch — DOC-049 §2).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrationRepository
{
    public const TABLE = 'tvn_migrations';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** ساخت جدول repository (idempotent). */
    public function createRepository(): void
    {
        if ($this->repositoryExists()) {
            return;
        }

        $driver = $this->connection->driver();

        $idColumn = $driver === 'sqlite'
            ? '"id" integer primary key autoincrement'
            : ($driver === 'pgsql'
                ? '"id" serial primary key'
                : '`id` bigint unsigned not null auto_increment primary key');

        $this->connection->statement('create table ' . $this->table() . ' ('
            . $idColumn . ', '
            . $this->q('migration') . ' varchar(190) not null, '
            . $this->q('batch') . ' int not null, '
            . $this->q('module') . ' varchar(64) null, '
            . $this->q('ran_at') . ' datetime not null, '
            . 'unique (' . $this->q('migration') . ')'
            . ')');
    }

    public function repositoryExists(): bool
    {
        $driver = $this->connection->driver();
        $table = 'tvn_migrations';

        [$sql, $bindings] = match ($driver) {
            'mysql' => [
                'select table_name from information_schema.tables where table_schema = database() and table_name = ?',
                [$this->rawTableName()],
            ],
            'pgsql' => [
                'select table_name from information_schema.tables where table_schema = current_schema() and table_name = ?',
                [$this->rawTableName()],
            ],
            default => [
                "select name from sqlite_master where type = 'table' and name = ?",
                [$this->rawTableName()],
            ],
        };

        try {
            return $this->connection->select($sql, $bindings) !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<string> نام migrationهای اجراشده (به ترتیب) */
    public function getRan(): array
    {
        if (!$this->repositoryExists()) {
            return [];
        }

        $rows = $this->connection->select('select ' . $this->q('migration') . ' from ' . $this->table() . ' order by ' . $this->q('id') . ' asc');

        return \array_map(static fn (array $r): string => (string) \array_values($r)[0], $rows);
    }

    public function getLastBatchNumber(): int
    {
        if (!$this->repositoryExists()) {
            return 0;
        }

        $row = $this->connection->selectOne('select max(' . $this->q('batch') . ') as b from ' . $this->table());

        return (int) ($row['b'] ?? 0);
    }

    public function getNextBatch(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * @return list<string> migrationهای آخرین batch (یا n تای آخر) — ترتیب اجرا
     */
    public function getLastBatch(int $steps = 1): array
    {
        $steps = \max(1, $steps);
        $last = $this->getLastBatchNumber();
        $from = $last - $steps + 1;

        $rows = $this->connection->select(
            'select ' . $this->q('migration') . ' from ' . $this->table()
            . ' where ' . $this->q('batch') . ' >= ? order by ' . $this->q('id') . ' desc',
            [$from],
        );

        return \array_map(static fn (array $r): string => (string) \array_values($r)[0], $rows);
    }

    public function log(string $migration, int $batch, ?string $module = null): void
    {
        $this->connection->table('tvn_migrations')->insert([
            'migration' => $migration,
            'batch'     => $batch,
            'module'    => $module,
            'ran_at'    => Clock::utcNow()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $migration): void
    {
        $this->connection->table('tvn_migrations')->where('migration', $migration)->delete();
    }

    /** حذف همه (migrate:fresh). */
    public function deleteAll(): void
    {
        $this->connection->table('tvn_migrations')->delete();
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        if (!$this->repositoryExists()) {
            return [];
        }

        return $this->connection->select('select * from ' . $this->table() . ' order by ' . $this->q('id') . ' asc');
    }

    private function table(): string
    {
        return $this->q($this->rawTableName());
    }

    /** نام خام فیزیکی (با prefix اتصال — برای introspection). */
    private function rawTableName(): string
    {
        return $this->connection->grammar()->prefix() . self::TABLE;
    }

    private function q(string $identifier): string
    {
        return match ($this->connection->driver()) {
            'mysql' => '`' . \str_replace('`', '``', $identifier) . '`',
            default => '"' . \str_replace('"', '""', $identifier) . '"',
        };
    }
}
