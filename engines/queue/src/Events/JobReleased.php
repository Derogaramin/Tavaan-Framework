<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Events;

use Tavaan\Core\Contracts\Queue\JobInterface;

/**
 * تَوان | Tavaan — Queue
 * JobReleased — job برای retry با تأخیر به صف برگشت (DOC-061 §4: backoff).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class JobReleased
{
    public function __construct(
        public readonly string $connectionName,
        public readonly JobInterface $job,
        public readonly int $delay,
        public readonly ?\Throwable $exception = null,
    ) {
    }
}
