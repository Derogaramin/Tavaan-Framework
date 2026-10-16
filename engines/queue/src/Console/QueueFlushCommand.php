<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Queue\QueueManager;

/**
 * تَوان | Tavaan — Queue
 * QueueFlushCommand — حذف همهٔ رکوردهای failed_jobs (DOC-061 §11).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueueFlushCommand extends Command
{
    protected string $signature = 'queue:flush {--hours= : فقط قدیمی‌تر از این تعداد ساعت} {--force : بدون تأیید}';

    protected string $description = 'حذف رکوردهای job شکست‌خورده';

    public function handle(): int
    {
        /** @var QueueManager $manager */
        $manager = $this->app->make(QueueManager::class);

        $hours = $this->option('hours');
        $hours = \is_numeric($hours) ? (int) $hours : null;

        if (!$this->option('force') && !$this->confirm('همهٔ رکوردهای failed_jobs حذف شوند؟', false)) {
            $this->comment('لغو شد.');

            return self::SUCCESS;
        }

        $deleted = $manager->failedProvider()->flush($hours);
        $this->info(\sprintf('%d رکورد شکست حذف شد.', $deleted));

        return self::SUCCESS;
    }
}
