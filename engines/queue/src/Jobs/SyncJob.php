<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Jobs;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Queue\Job;

/**
 * تَوان | Tavaan — Queue
 * SyncJob — job درایور sync: همان لحظه اجرا می‌شود؛ delete/release بی‌اثر (DOC-061 §2).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class SyncJob extends Job
{
    public function __construct(
        ?ContainerInterface $container,
        string $connectionName,
        string $queueName,
        string $rawPayload,
        private readonly ?string $jobUuid = null,
    ) {
        parent::__construct($container, $connectionName, $queueName, $rawPayload);
    }

    public function attempts(): int
    {
        return 1;   // sync هیچ retry ندارد
    }

    public function getJobId(): int|string|null
    {
        return $this->jobUuid ?? $this->uuid();
    }

    protected function deleteFromBackend(): void
    {
        // no-op: job همزمان اجرا و تمام شده است
    }

    protected function releaseToBackend(int $delay): void
    {
        // no-op: در حالت sync، release معنا ندارد (Laravel-parity)
    }
}
