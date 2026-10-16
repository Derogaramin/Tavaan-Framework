<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Queue
 * MaxAttemptsExceededException — job از حداکثر تلاش‌ها یا retryUntil گذشت (DOC-061 §4).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class MaxAttemptsExceededException extends TavaanException
{
    public function __construct(string $jobName = '', int $tries = 0)
    {
        parent::__construct(
            'queue.max_attempts_exceeded',
            ['job' => $jobName, 'tries' => $tries],
            'job پس از آخرین تلاش به failed_jobs منتقل شد.',
        );
    }
}
