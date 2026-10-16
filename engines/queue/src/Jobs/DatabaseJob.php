<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Jobs;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Queue\Job;
use Tavaan\Engine\Queue\Queues\DatabaseQueue;

/**
 * تَوان | Tavaan — Queue
 * DatabaseJob — job برداشته‌شده از درایور database (DOC-061 §6).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class DatabaseJob extends Job
{
    public function __construct(
        ?ContainerInterface $container,
        string $connectionName,
        string $queueName,
        string $rawPayload,
        private readonly int $id,
        private readonly int $attemptsCount,
        private readonly DatabaseQueue $database,
    ) {
        parent::__construct($container, $connectionName, $queueName, $rawPayload);
    }

    public function attempts(): int
    {
        return $this->attemptsCount;
    }

    public function getJobId(): int|string|null
    {
        return $this->id;
    }

    protected function deleteFromBackend(): void
    {
        $this->database->deleteReserved($this->id);
    }

    protected function releaseToBackend(int $delay): void
    {
        $this->database->releaseReserved($this->id, $delay);
    }
}
