<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Testing;

use Tavaan\Engine\Database\Exceptions\DeadlockException;

/**
 * تَوان | Tavaan — Database
 * FlakyDeadlockConnection — Fake با Deadlock ساختگی برای تست retry (DOC-047 T-06).
 *
 * N فراخوانی اول statement() با DeadlockException (sqlState=40001) شکست می‌خورد.
 *
 * @api internal
 * @see docs/03-engines/database.md
 */
final class FlakyDeadlockConnection extends FakeConnection
{
    private int $remainingFailures;

    public function __construct(int $failures = 1)
    {
        parent::__construct(driverName: 'sqlite', config: ['database' => ':memory:', 'prefix' => '']);
        $this->remainingFailures = $failures;
    }

    /** @param array<int|string, mixed> $bindings */
    public function statement(string $sql, array $bindings = [], bool $useWrite = true): bool
    {
        if ($this->remainingFailures > 0) {
            $this->remainingFailures--;

            throw new DeadlockException('fake-deadlocked-sql', $bindings, '40001');
        }

        return parent::statement($sql, $bindings, $useWrite);
    }
}
