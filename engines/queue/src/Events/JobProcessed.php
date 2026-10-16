<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Events;

use Tavaan\Core\Contracts\Queue\JobInterface;

/**
 * تَوان | Tavaan — Queue
 * JobProcessed — job با موفقیت پردازش و حذف شد (DOC-061 §4).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class JobProcessed
{
    public function __construct(
        public readonly string $connectionName,
        public readonly JobInterface $job,
    ) {
    }
}
