<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Jobs;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Queue\Job;
use Tavaan\Engine\Queue\Queues\FileQueue;

/**
 * تَوان | Tavaan — Queue
 * FileJob — job برداشته‌شده از درایور file (DOC-061 §2).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class FileJob extends Job
{
    public function __construct(
        ?ContainerInterface $container,
        string $connectionName,
        string $queueName,
        string $rawPayload,
        private readonly string $reservedPath,
        private readonly int $attemptsCount,
        private readonly FileQueue $fileQueue,
    ) {
        parent::__construct($container, $connectionName, $queueName, $rawPayload);
    }

    public function attempts(): int
    {
        return $this->attemptsCount;
    }

    public function getJobId(): int|string|null
    {
        return \basename($this->reservedPath);
    }

    protected function deleteFromBackend(): void
    {
        $this->fileQueue->deleteReserved($this->reservedPath);
    }

    protected function releaseToBackend(int $delay): void
    {
        $this->fileQueue->releaseReserved(
            $this->reservedPath,
            $this->queueName,
            $this->rawPayload,
            $this->attemptsCount,
            $delay,
        );
    }
}
