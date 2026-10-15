<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Optimizer\Optimizer;

/**
 * کش پیکربندی (AC-CFG-02).
 *
 * @api 1.0
 */
final class ConfigCacheCommand extends Command
{
    protected string $signature = 'config:cache';

    protected string $description = 'ذخیرهٔ کل پیکربندی در یک فایل کش';

    public function handle(): int
    {
        /** @var Optimizer $optimizer */
        $optimizer = $this->app->make(Optimizer::class);
        $optimizer->cache('config');
        $this->info('  ✓ configuration cached successfully.');

        return self::SUCCESS;
    }
}
