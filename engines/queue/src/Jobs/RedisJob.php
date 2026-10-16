<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Jobs;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Queue\Job;
use Tavaan\Engine\Queue\Queues\RedisQueue;

/**
 * تَوان | Tavaan — Queue
 * RedisJob — job برداشته‌شده از درایور redis (DOC-061 §2).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class RedisJob extends Job
{
    public function __construct(
        ?ContainerInterface $container,
        string $connectionName,
        string $queueName,
        string $rawPayload,
        private readonly int $attemptsCount,
        private readonly RedisQueue $redis,
    ) {
        parent::__construct($container, $connectionName, $queueName, $rawPayload);
    }

    public function attempts(): int
    {
        return $this->attemptsCount;
    }

    public function getJobId(): int|string|null
    {
        return $this->uuid();
    }

    protected function deleteFromBackend(): void
    {
        $this->redis->deleteReserved($this->queueName, $this->rawPayload);
    }

    protected function releaseToBackend(int $delay): void
    {
        $this->redis->releaseReserved($this->queueName, $this->rawPayload, $delay);
    }
}
