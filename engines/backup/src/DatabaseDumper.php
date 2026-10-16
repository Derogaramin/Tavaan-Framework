<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup;

use Tavaan\Core\Contracts\Database\ConnectionInterface;

/**
 * تَوان | Tavaan — Backup
 * DatabaseDumper — dump خالص SQL **بدون mysqldump خارجی** (DOC-068 §4).
 *
 * 🔒 حافظهٔ ثابت: داده با `cursor()` و نوشتن روی handle خوانده/نوشته می‌شود
 * (AC-BKP-08 — حتی برای دیتابیس‌های بزرگ).
 *
 * Schema DDL:
 * - sqlite: `sqlite_master.sql`
 * - mysql:  `SHOW CREATE TABLE`
 * - pgsql:  از introspection (محدود) — در غیر این صورت فقط داده
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class DatabaseDumper
{
    /** جداول سیستمی/داخلی که dump نمی‌شوند */
    private const EXCLUDED = ['sqlite_sequence'];

    /**
     * dump کامل روی فایل (streaming).
     *
     * @param list<string>|null $tables فقط این جداول (null = همه)
     */
    public function dumpToFile(ConnectionInterface $conn, string $path, ?array $tables = null): int
    {
        $handle = \fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open dump file: ' . $path);
        }

        try {
            return $this->dumpToHandle($conn, $handle, $tables);
        } finally {
            \fclose($handle);
        }
    }

    /**
     * dump روی handle — تعداد جداول نوشته‌شده را برمی‌گرداند.
     *
     * @param resource          $handle
     * @param list<string>|null $tables
     */
    public function dumpToHandle(ConnectionInterface $conn, $handle, ?array $tables = null): int
    {
        $driver = $conn->driver();

        \fwrite($handle, "-- Tavaan Backup | SQL Dump\n");
        \fwrite($handle, '-- driver: ' . $driver . "\n");
        \fwrite($handle, '-- created_at: ' . \Tavaan\Support\Time\Clock::isoNow() . "\n");
        if ($driver === 'mysql') {
            \fwrite($handle, "SET NAMES utf8mb4;\n");
        }
        \fwrite($handle, "\n");

        $count = 0;
        foreach ($this->tables($conn, $tables) as $table) {
            $ddl = $this->createTableSql($conn, $table);

            \fwrite($handle, '-- table: ' . $table . "\n");
            \fwrite($handle, 'DROP TABLE IF EXISTS ' . $this->quoteIdent($table, $driver) . ";\n");
            if ($ddl !== null) {
                \fwrite($handle, \rtrim($ddl, ';') . ";\n\n");
            } else {
                \fwrite($handle, "-- (schema DDL unavailable for this driver — data only)\n\n");
            }

            $this->dumpTableData($conn, $handle, $table, $driver);
            $count++;
        }

        return $count;
    }

    /** dump به رشته (برای دیتابیس‌های کوچک/تست). */
    public function dump(ConnectionInterface $conn, ?array $tables = null): string
    {
        $handle = \fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new \RuntimeException('php://temp unavailable');
        }

        try {
            $this->dumpToHandle($conn, $handle, $tables);
            \rewind($handle);

            return (string) \stream_get_contents($handle);
        } finally {
            \fclose($handle);
        }
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    /**
     * @param list<string>|null $filter
     *
     * @return list<string>
     */
    private function tables(ConnectionInterface $conn, ?array $filter): array
    {
        $tables = match ($conn->driver()) {
            'sqlite' => \array_column(
                $conn->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"),
                'name',
            ),
            'mysql' => \array_map(
                static fn (array $row): string => (string) \array_values($row)[0],
                $conn->select('SHOW TABLES'),
            ),
            'pgsql' => \array_column(
                $conn->select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename"),
                'tablename',
            ),
            default => [],
        };

        $tables = \array_values(\array_filter(
            \array_map(strval(...), $tables),
            static fn (string $t): bool => !\in_array($t, self::EXCLUDED, true),
        ));

        if ($filter !== null) {
            $tables = \array_values(\array_intersect($tables, $filter));
        }

        return $tables;
    }

    private function createTableSql(ConnectionInterface $conn, string $table): ?string
    {
        try {
            return match ($conn->driver()) {
                'sqlite' => (string) ($conn->selectOne('SELECT sql FROM sqlite_master WHERE type=\'table\' AND name = ?', [$table])['sql'] ?? ''),
                'mysql' => (string) ($conn->selectOne('SHOW CREATE TABLE ' . $this->quoteIdent($table, 'mysql'))['Create Table'] ?? ''),
                default => null,   // pgsql: ساخت DDL قابل‌حمل خارج از scope این نسخه
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param resource $handle */
    private function dumpTableData(ConnectionInterface $conn, $handle, string $table, string $driver): void
    {
        $quotedTable = $this->quoteIdent($table, $driver);
        $rowsInStatement = 0;
        $columns = null;

        // 🔒 cursor = حافظهٔ ثابت (بدون بارگذاری کل جدول)
        foreach ($conn->table($table)->cursor() as $row) {
            if ($columns === null) {
                $columns = \array_map(
                    fn (string $c): string => $this->quoteIdent($c, $driver),
                    \array_keys($row),
                );
            }

            $values = [];
            foreach ($row as $value) {
                $values[] = $this->escapeValue($conn, $value);
            }

            if ($rowsInStatement === 0) {
                \fwrite($handle, 'INSERT INTO ' . $quotedTable . ' (' . \implode(', ', $columns) . ") VALUES\n");
            } else {
                \fwrite($handle, ",\n");
            }

            \fwrite($handle, '(' . \implode(', ', $values) . ')');
            $rowsInStatement++;

            // batchهای ۱۰۰ تایی (DOC-068 §4: chunked)
            if ($rowsInStatement >= 100) {
                \fwrite($handle, ";\n");
                $rowsInStatement = 0;
            }
        }

        if ($rowsInStatement > 0) {
            \fwrite($handle, ";\n");
        }
        \fwrite($handle, "\n");
    }

    private function escapeValue(ConnectionInterface $conn, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        // 🔒 quote با PDO — escape مخصوص درایور
        try {
            return $conn->pdo()->quote((string) $value);
        } catch (\Throwable) {
            return "'" . \str_replace("'", "''", (string) $value) . "'";
        }
    }

    private function quoteIdent(string $identifier, string $driver): string
    {
        // 🔒 فقط شناسه‌های امن؛ هرگز مقدار کاربر داخل DDL
        $clean = \preg_replace('/[^\w$]/', '', $identifier) ?? '';

        return match ($driver) {
            'mysql' => '`' . $clean . '`',
            'pgsql' => '"' . $clean . '"',
            default => '"' . $clean . '"',
        };
    }
}
