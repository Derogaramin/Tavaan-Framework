<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database;

use Tavaan\Core\Contracts\Database\TransactionManagerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Database\Exceptions\DeadlockException;
use Tavaan\Engine\Database\Exceptions\QueryException;

/**
 * تَوان | Tavaan — Database
 * TransactionManager — begin/commit/rollback با Nesting (Savepoint) و
 * Deadlock Retry با backoff (DOC-047 §7).
 *
 * 🔒 SQLite savepoint: `SAVEPOINT transN` / `RELEASE transN` / `ROLLBACK TO transN`.
 * 🔒 Deadlock retry با backoff: ۵۰ms، ۱۵۰ms، ۴۵۰ms + jitter.
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class TransactionManager implements TransactionManagerInterface
{
    private int $level = 0;

    /** @var list<int> backoff پایه (ms) */
    private const RETRY_BACKOFF_MS = [50, 150, 450];

    public function __construct(
        private readonly Connection $connection,
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    /**
     * @param \Closure(TransactionManagerInterface): mixed $callback
     */
    public function transaction(\Closure $callback, int $attempts = 1, ?string $isolation = null): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            $this->begin($isolation);

            try {
                $result = $callback($this);
                $this->commit();

                return $result;
            } catch (\Throwable $e) {
                $this->rollBack();

                if ($this->isRetryable($e) && $attempt < $attempts) {
                    $this->sleepBackoff($attempt);

                    continue;
                }

                throw $e;
            }
        }
    }

    public function begin(?string $isolation = null): void
    {
        if ($this->level === 0) {
            if ($isolation !== null) {
                $this->connection->setIsolationLevel($isolation);
            }
            $this->connection->pdo()->beginTransaction();
            $this->events?->dispatch('database.transaction.begin', ['connection' => $this->connection->name()]);
        } else {
            $this->connection->pdo()->exec('SAVEPOINT trans' . ($this->level + 1));
        }

        $this->level++;
    }

    public function commit(): void
    {
        if ($this->level <= 0) {
            throw new \LogicException('commit() without an active transaction.');
        }

        if ($this->level === 1) {
            $this->connection->pdo()->commit();
            $this->events?->dispatch('database.transaction.commit', ['connection' => $this->connection->name()]);
        } else {
            $this->connection->pdo()->exec('RELEASE SAVEPOINT trans' . $this->level);
        }

        $this->level--;
    }

    public function rollBack(?int $toLevel = null): void
    {
        $toLevel ??= $this->level - 1;
        $toLevel = \max(0, \min($toLevel, $this->level - 1));

        if ($toLevel === 0) {
            if ($this->connection->pdo()->inTransaction()) {
                $this->connection->pdo()->rollBack();
            }
            $this->events?->dispatch('database.transaction.rollback', [
                'connection' => $this->connection->name(),
                'to_level'   => 0,
            ]);
        } else {
            $this->connection->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . ($toLevel + 1));
        }

        $this->level = $toLevel;
    }

    public function level(): int
    {
        return $this->level;
    }

    private function isRetryable(\Throwable $e): bool
    {
        return $e instanceof DeadlockException
            || ($e instanceof QueryException && \in_array($e->sqlState, ['40001', '40P01', '1213', '1205'], true));
    }

    private function sleepBackoff(int $attempt): void
    {
        $baseMs = self::RETRY_BACKOFF_MS[\min($attempt - 1, \count(self::RETRY_BACKOFF_MS) - 1)];
        $jitter = \random_int(0, (int) ($baseMs / 2));
        \usleep(($baseMs + $jitter) * 1000);
    }
}
