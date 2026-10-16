<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Jobs;

use Tavaan\Core\Contracts\Queue\JobInterface;

/**
 * تَوان | Tavaan — Queue
 * CallQueuedHandler — wrapper داخلی اجرای jobهای کاربر (DOC-061 §7).
 *
 * - `handle()`: بازسازی دستور (Serializer + Allowlist) → فراخوانی handle با DI
 *   → ادامهٔ Chain → ثبت موفقیت Batch.
 * - `failed()`: hook کاربر `failed(Throwable)` + ثبت شکست Batch.
 *
 * 🔒 این کلاس هرگز مستقیم توسط کاربر push نمی‌شود؛ payload.job است.
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class CallQueuedHandler
{
    public function __construct(private readonly ?\Tavaan\Engine\Queue\QueueManager $manager = null)
    {
    }

    /**
     * @param JobInterface         $job
     * @param array<string, mixed> $data {commandName, command}
     */
    public function handle(JobInterface $job, array $data = []): void
    {
        $manager = $this->manager
            ?? throw new \RuntimeException('CallQueuedHandler requires QueueManager.');

        $command = $manager->serializer()->restoreCommand($data);

        $manager->container()->call([$command, 'handle']);

        // Chain: عضو بعدی فقط پس از موفقیت push می‌شود (DOC-061 §3)
        $this->dispatchNextInChain($job);

        // Batch: ثبت موفقیت + finalize در پایان
        $this->recordBatchSuccess($job);
    }

    /**
     * شکست نهایی (تلاش‌ها تمام شد) — توسط Worker/SyncQueue فراخوانی می‌شود.
     *
     * @param array<string, mixed> $payload
     */
    public function failed(array $payload, \Throwable $e): void
    {
        if ($this->manager === null) {
            return;
        }

        // hook کاربر: failed(Throwable) — بهترین تلاش (بازسازی ممکن است شکست بخورد)
        try {
            $command = $this->manager->serializer()->restoreCommand($payload['data'] ?? []);
            if (\method_exists($command, 'failed')) {
                $this->manager->container()->call([$command, 'failed'], ['e' => $e]);
            }
        } catch (\Throwable) {
            // 🔒 خطای بازسازی نباید خطای اصلی job را بپوشاند
        }

        $this->recordBatchFailure($payload);
    }

    private function dispatchNextInChain(JobInterface $job): void
    {
        if ($this->manager === null) {
            return;
        }

        $chain = $job->payload()['chain'] ?? [];
        if (!\is_array($chain) || $chain === []) {
            return;
        }

        $next = \array_shift($chain);
        if (!\is_string($next)) {
            return;
        }

        // باقی زنجیره در payload عضو بعدی بازنویسی می‌شود
        $decoded = \json_decode($next, true);
        if (\is_array($decoded)) {
            $decoded['chain'] = \array_values($chain);
            $next = \json_encode($decoded, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        $connection = $this->manager->connection($job->getConnectionName());
        $connection->pushRaw($next, $job->getQueue());
    }

    private function recordBatchSuccess(JobInterface $job): void
    {
        $batchId = $job->payload()['batchId'] ?? null;
        if (!\is_string($batchId) || $batchId === '' || $this->manager === null) {
            return;
        }

        $repository = $this->manager->batchRepository();
        $finished = $repository->recordSuccess($batchId, (string) ($job->uuid() ?? ''));

        if ($finished) {
            $this->manager->finalizeBatch($batchId);
        }
    }

    /** @param array<string, mixed> $payload */
    private function recordBatchFailure(array $payload): void
    {
        $batchId = $payload['batchId'] ?? null;
        if (!\is_string($batchId) || $batchId === '' || $this->manager === null) {
            return;
        }

        $repository = $this->manager->batchRepository();
        $record = $repository->find($batchId);
        $options = \is_array($record['options'] ?? null) ? $record['options'] : [];
        $allowFailures = (bool) ($options['allowFailures'] ?? false);

        $finished = $repository->recordFailure($batchId, (string) ($payload['uuid'] ?? ''), $allowFailures);

        if (!$allowFailures) {
            $repository->cancel($batchId);

            return;
        }

        if ($finished) {
            $this->manager->finalizeBatch($batchId);
        }
    }
}
