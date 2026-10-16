<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Events;

use Tavaan\Core\Contracts\Queue\JobInterface;

/**
 * تَوان | Tavaan — Queue
 * JobProcessing — worker پردازش job را آغاز کرد (DOC-061 §4).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class JobProcessing
{
    public function __construct(
        public readonly string $connectionName,
        public readonly JobInterface $job,
    ) {
    }
}
