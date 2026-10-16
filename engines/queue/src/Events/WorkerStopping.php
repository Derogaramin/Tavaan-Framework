<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Events;

/**
 * تَوان | Tavaan — Queue
 * WorkerStopping — worker در حال خروج است (signal/maxJobs/maxTime/memory/recycle).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class WorkerStopping
{
    public function __construct(
        public readonly int $status = 0,
        public readonly string $reason = '',
    ) {
    }
}
