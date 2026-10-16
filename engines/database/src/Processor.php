<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database;

/**
 * تَوان | Tavaan — Database
 * Processor — پس‌پردازش نتیجهٔ کوئری (cast سبک — DOC-047 §2.1).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
class Processor
{
    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public function processSelect(array $rows): array
    {
        return $rows;
    }

    public function processInsertGetId(Connection $connection, string $sql, array $bindings, ?string $sequence = null): int|string
    {
        $connection->insert($sql, $bindings);

        return $connection->lastInsertId($sequence);
    }
}
