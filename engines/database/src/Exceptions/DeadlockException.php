<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Exceptions;

use Tavaan\Core\Exceptions\RetryableException;

/**
 * تَوان | Tavaan — Database
 * DeadlockException — بن‌بست تراکنش (قابل retry با backoff).
 *
 * @api 1.0
 * @see docs/03-engines/database.md
 */
final class DeadlockException extends QueryException implements RetryableException
{
    public function retryAfter(): ?int
    {
        return null;   // backoff توسط TransactionManager: 50/150/450ms + jitter
    }

    public function maxRetries(): int
    {
        return 3;
    }
}
