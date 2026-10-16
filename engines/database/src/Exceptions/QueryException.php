<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Database
 * QueryException — خطای اجرای کوئری (با sqlState و SQL — بدون نشت bindings خام در پیام).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
class QueryException extends TavaanException
{
    public readonly string $sqlState;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings,
        ?string $sqlState = null,
        ?string $hint = null,
        ?\Throwable $previous = null,
    ) {
        $this->sqlState = $sqlState ?? ($previous instanceof \PDOException ? (string) $previous->getCode() : 'HY000');

        parent::__construct(
            'database.query_failed',
            ['sql' => $sql, 'bindings' => $bindings, 'sql_state' => $this->sqlState],
            $hint,
            null,
            0,
            $previous,
        );
    }
}
