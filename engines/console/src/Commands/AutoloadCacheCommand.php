<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Optimizer\Optimizer;

/**
 * تولید ClassMap cache (AC-BOOT-04).
 *
 * @api 1.0
 */
final class AutoloadCacheCommand extends Command
{
    protected string $signature = 'autoload:cache {--clear : حذف کش}';

    protected string $description = 'تولید/حذف کش ClassMap بارگذار خودکار';

    public function handle(): int
    {
        /** @var Optimizer $optimizer */
        $optimizer = $this->app->make(Optimizer::class);

        if ($this->option('clear')) {
            $optimizer->clear('autoload');
            $this->info('  ✓ classmap cache cleared.');

            return self::SUCCESS;
        }

        $optimizer->cache('autoload');
        $this->info('  ✓ classmap cache generated (storage/cache/classmap.php).');

        return self::SUCCESS;
    }
}
