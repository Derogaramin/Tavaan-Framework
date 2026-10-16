<?php

declare(strict_types=1);

namespace Tavaan\Engine\Queue\Console;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Queue\Worker;

/**
 * تَوان | Tavaan — Queue
 * QueueRestartCommand — سیگنال بازراه‌اندازی آرام workerها (DOC-061 §11).
 *
 * workerها پس از اتمام job جاری، در چرخهٔ بعد timestamp را می‌بینند و
 * با کد ۱ خارج می‌شوند (supervisor دوباره بالا می‌آورد → کد جدید فعال).
 *
 * @api 1.0
 * @see docs/03-engines/queue.md
 */
final class QueueRestartCommand extends Command
{
    protected string $signature = 'queue:restart';

    protected string $description = 'سیگنال بازراه‌اندازی آرام به همهٔ workerهای صف';

    public function handle(): int
    {
        $cache = $this->app->bound(CacheRepository::class)
            ? $this->app->make(CacheRepository::class)
            : ($this->app->bound('cache') ? $this->app->make('cache') : null);

        if (!$cache instanceof CacheRepository) {
            $this->error('موتور cache برای سیگنال restart لازم است (کلید tvn:queue:restart).');

            return self::FAILURE;
        }

        // 🔒 forever: سیگنال تا restart همهٔ workerها باقی بماند
        $cache->forever(Worker::RESTART_CACHE_KEY, \time());

        $this->info('سیگنال restart ارسال شد — workerها پس از job جاری خارج می‌شوند.');

        return self::SUCCESS;
    }
}
