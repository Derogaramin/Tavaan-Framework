<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache\Console;

use Tavaan\Engine\Cache\CacheManager;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Cache
 * CacheClearCommand — پاک‌سازی کش (DOC-060).
 *
 * @api 1.0
 * @see docs/03-engines/cache.md
 */
final class CacheClearCommand extends Command
{
    protected string $signature = 'cache:clear {--store= : درایور خاص}';

    protected string $description = 'پاک‌سازی حافظهٔ نهان';

    public function handle(): int
    {
        /** @var CacheManager $cache */
        $cache = $this->app->make(CacheManager::class);

        $store = $this->option('store');
        $repository = \is_string($store) && $store !== '' ? $cache->store($store) : $cache->repository();

        $repository->flush();
        $this->info('کش پاک شد (' . (\is_string($store) && $store !== '' ? $store : $cache->getDefaultDriver()) . ').');

        return self::SUCCESS;
    }
}
