<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Connections;

use Tavaan\Engine\Database\Connection;

/**
 * تَوان | Tavaan — Database
 * MySqlConnection — تفاوت‌های درایور MySQL/MariaDB (charset, sql_mode, timezone).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class MySqlConnection extends Connection
{
    public function driver(): string
    {
        return 'mysql';
    }

    /** @param array<string, mixed> $config */
    protected function configure(\PDO $pdo, array $config): void
    {
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $collation = (string) ($config['collation'] ?? 'utf8mb4_0900_ai_ci');
        $pdo->exec("SET NAMES '{$charset}' COLLATE '{$collation}'");

        if ((bool) ($config['strict'] ?? false)) {
            $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }

        if (isset($config['timezone']) && (string) $config['timezone'] !== '') {
            $timezone = \str_replace("'", '', (string) $config['timezone']);
            $pdo->exec("SET time_zone = '{$timezone}'");
        }
    }
}
