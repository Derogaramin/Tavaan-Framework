<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Connections;

use Tavaan\Engine\Database\Connection;

/**
 * تَوان | Tavaan — Database
 * SqliteConnection — تفاوت‌های درایور SQLite (foreign keys, busy timeout).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class SqliteConnection extends Connection
{
    public function driver(): string
    {
        return 'sqlite';
    }

    /** @param array<string, mixed> $config */
    protected function configure(\PDO $pdo, array $config): void
    {
        if ((bool) ($config['foreign_key_constraints'] ?? true)) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        $busyTimeout = (int) ($config['busy_timeout'] ?? 5000);
        if ($busyTimeout > 0) {
            $pdo->exec('PRAGMA busy_timeout = ' . $busyTimeout);
        }

        // WAL برای همزمانی بهتر (فقط فایل؛ :memory: پشتیبانی نمی‌کند)
        if ((bool) ($config['wal'] ?? true) && (string) ($config['database'] ?? '') !== ':memory:') {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }
    }
}
