<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Events;

use Tavaan\Core\Contracts\Queue\JobInterface;

/**
 * تَوان | Tavaan — Queue
 * JobFailed — job پس از اتمام تلاش‌ها شکست نهایی خورد (→ failed_jobs + DLQ).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class JobFailed
{
    public function __construct(
        public readonly string $connectionName,
        public readonly ?JobInterface $job,
        public readonly \Throwable $exception,
        public readonly ?string $uuid = null,
    ) {
    }
}
