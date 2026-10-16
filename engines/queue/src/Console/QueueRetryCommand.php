<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Queue\QueueManager;

/**
 * تَوان | Tavaan — Queue
 * QueueRetryCommand — بازگرداندن jobهای شکست‌خورده به صف (DOC-061 §11).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueueRetryCommand extends Command
{
    protected string $signature = 'queue:retry
        {id? : شناسه یا uuid رکورد failed}
        {--all : همهٔ jobهای شکست‌خورده}
        {--queue= : فقط یک صف مشخص}';

    protected string $description = 'retry یک/همهٔ jobهای شکست‌خورده';

    public function handle(): int
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make(QueueManager::class);
        $failed = $manager->failedProvider();

        $id = $this->argument('id');
        $queueFilter = $this->option('queue');
        $retryAll = (bool) $this->option('all') || $id === null;

        if (!$retryAll) {
            $record = $failed->find((string) $id);
            if ($record === null) {
                $this->error(\sprintf('رکورد failed با شناسهٔ [%s] یافت نشد.', (string) $id));

                return self::FAILURE;
            }

            return $this->retryRecord($manager, $record) ? self::SUCCESS : self::FAILURE;
        }

        $records = $failed->all(1000);
        $count = 0;
        foreach ($records as $record) {
            if (\is_string($queueFilter) && $queueFilter !== '' && (string) ($record['queue'] ?? '') !== $queueFilter) {
                continue;
            }
            if ($this->retryRecord($manager, $record)) {
                $count++;
            }
        }

        $this->info(\sprintf('%d job شکست‌خورده به صف برگشت.', $count));

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $record */
    private function retryRecord(QueueManager $manager, array $record): bool
    {
        $payload = (string) ($record['payload'] ?? '');
        if ($payload === '') {
            return false;
        }

        $decoded = \json_decode($payload, true);
        if (!\is_array($decoded)) {
            return false;
        }

        $queue = (string) ($record['queue'] ?? $decoded['queue'] ?? 'default');
        $connectionName = (string) ($record['connection'] ?? '');

        // attempts ریست می‌شود تا job فرصت تازه بگیرد
        $decoded['attempts'] = 0;
        $clean = \json_encode($decoded, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        try {
            $connection = $manager->connection($connectionName !== '' ? $connectionName : null);
            if (!\method_exists($connection, 'pushRaw')) {
                $this->warn(\sprintf('اتصال [%s] از pushRaw پشتیبانی نمی‌کند.', $connectionName));

                return false;
            }
            $connection->pushRaw($clean, $queue);
        } catch (\Throwable $e) {
            $this->error(\sprintf('retry ناموفق: %s', $e->getMessage()));

            return false;
        }

        $failed = $manager->failedProvider();
        $key = $record['uuid'] ?? $record['id'] ?? null;
        if ($key !== null) {
            $failed->forget(\is_int($key) ? $key : (string) $key);
        }

        return true;
    }
}
