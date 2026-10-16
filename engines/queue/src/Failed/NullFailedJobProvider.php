<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Failed;

use Tavaan\Core\Contracts\Queue\FailedJobProviderInterface;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Queue
 * NullFailedJobProvider — بدون ثبت (config: failed.driver = null؛ DOC-061 §8).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class NullFailedJobProvider implements FailedJobProviderInterface
{
    public function log(string $connection, string $queue, string $payload, \Throwable $e, int|string|null $id = null): string
    {
        $decoded = \json_decode($payload, true);

        return \is_array($decoded) && \is_string($decoded['uuid'] ?? null)
            ? $decoded['uuid']
            : (string) Ulid::generate();
    }

    /** @return list<array<string, mixed>> */
    public function all(int $limit = 100): array
    {
        return [];
    }

    /** @return array<string, mixed>|null */
    public function find(int|string $idOrUuid): ?array
    {
        return null;
    }

    public function forget(int|string $idOrUuid): bool
    {
        return false;
    }

    public function flush(?int $hours = null): int
    {
        return 0;
    }

    public function count(): int
    {
        return 0;
    }
}
