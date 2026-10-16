<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database;

/**
 * تَوان | Tavaan — Database
 * QueryRecord — رکورد یک کوئری اجراشده (SQL + bindings ماسک‌شده + زمان).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final readonly class QueryRecord
{
    /**
     * @param array<int|string, mixed> $bindings ماسک‌شده
     * @param list<string>             $trace    خلاصهٔ backtrace
     */
    public function __construct(
        public string $sql,
        public array $bindings,
        public float $timeMs,
        public string $connection,
        public array $trace = [],
    ) {
    }

    /** @return array{sql: string, bindings: array<int|string, mixed>, time: float, connection: string} */
    public function toArray(): array
    {
        return [
            'sql'        => $this->sql,
            'bindings'   => $this->bindings,
            'time'       => \round($this->timeMs, 3),
            'connection' => $this->connection,
        ];
    }
}
