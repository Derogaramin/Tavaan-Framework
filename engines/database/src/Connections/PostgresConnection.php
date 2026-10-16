<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Connections;

use Tavaan\Engine\Database\Connection;

/**
 * تَوان | Tavaan — Database
 * PostgresConnection — تفاوت‌های درایور PostgreSQL (timezone, search_path).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class PostgresConnection extends Connection
{
    public function driver(): string
    {
        return 'pgsql';
    }

    /** @param array<string, mixed> $config */
    protected function configure(\PDO $pdo, array $config): void
    {
        $pdo->exec("SET TIME ZONE 'UTC'");

        $schema = (string) ($config['schema'] ?? 'public');
        if ($schema !== '' && $schema !== 'public') {
            $safe = \str_replace("'", "''", $schema);
            $pdo->exec("SET search_path TO '{$safe}'");
        }

        if ((string) ($config['application_name'] ?? '') !== '') {
            $name = \str_replace("'", "''", (string) $config['application_name']);
            $pdo->exec("SET application_name TO '{$name}'");
        }
    }
}
