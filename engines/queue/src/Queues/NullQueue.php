<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Queues;

use Tavaan\Core\Contracts\Queue\JobInterface;

/**
 * تَوان | Tavaan — Queue
 * NullQueue — همه‌چیز دور ریخته می‌شود (خاموش؛ DOC-061 §2).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class NullQueue extends Queue
{
    public function pushRaw(string $payload, ?string $queue = null): int|string|null
    {
        return null;
    }

    public function laterRaw(int $availableAt, string $payload, ?string $queue = null): int|string|null
    {
        return null;
    }

    public function pop(?string $queue = null): ?JobInterface
    {
        return null;
    }

    public function size(?string $queue = null): int
    {
        return 0;
    }
}
