<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Queue\QueueManager;

/**
 * تَوان | Tavaan — Queue
 * QueueFailedCommand — فهرست jobهای شکست‌خورده (DOC-061 §11).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueueFailedCommand extends Command
{
    protected string $signature = 'queue:failed {--limit=50 : تعداد رکورد}';

    protected string $description = 'فهرست jobهای شکست‌خورده (failed_jobs)';

    public function handle(): int
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make(QueueManager::class);
        $records = $manager->failedProvider()->all((int) ($this->option('limit') ?? 50));

        if ($records === []) {
            $this->info('هیچ job شکست‌خورده‌ای ثبت نشده است. ✅');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($records as $record) {
            $payload = \json_decode((string) ($record['payload'] ?? '{}'), true);
            $exception = \explode("\n", (string) ($record['exception'] ?? ''))[0] ?? '';

            $rows[] = [
                (string) ($record['id'] ?? ''),
                \mb_substr((string) ($record['uuid'] ?? ''), 0, 12) . '…',
                (string) ($record['connection'] ?? ''),
                (string) ($record['queue'] ?? ''),
                \is_array($payload) ? (string) ($payload['displayName'] ?? '') : '',
                \mb_substr($exception, 0, 60),
                (string) ($record['failed_at'] ?? ''),
            ];
        }

        $this->table(['ID', 'UUID', 'اتصال', 'صف', 'Job', 'خطا', 'زمان'], $rows);

        return self::SUCCESS;
    }
}
