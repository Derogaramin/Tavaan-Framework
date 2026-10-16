<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Events;

/**
 * تَوان | Tavaan — Queue
 * JobQueued — job با موفقیت به صف سپرده شد (DOC-061).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class JobQueued
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $connectionName,
        public readonly string $queue,
        public readonly int|string|null $jobId,
        public readonly array $payload,
    ) {
    }
}
