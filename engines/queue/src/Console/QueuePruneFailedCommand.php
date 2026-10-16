<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Queue\QueueManager;

/**
 * تَوان | Tavaan — Queue
 * QueuePruneFailedCommand — هرس رکوردهای قدیمی failed_jobs (DOC-061 §11).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueuePruneFailedCommand extends Command
{
    protected string $signature = 'queue:prune-failed {--hours=24 : رکوردهای قدیمی‌تر از این تعداد ساعت حذف شوند}';

    protected string $description = 'هرس jobهای شکست‌خوردهٔ قدیمی';

    public function handle(): int
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make(QueueManager::class);

        $hours = (int) ($this->option('hours') ?? 24);
        $deleted = $manager->failedProvider()->flush(\max(1, $hours));

        $this->info(\sprintf('%d رکورد قدیمی‌تر از %d ساعت حذف شد.', $deleted, \max(1, $hours)));

        return self::SUCCESS;
    }
}
