<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Optimizer\Optimizer;

/**
 * حذف کش پیکربندی.
 *
 * @api 1.0
 */
final class ConfigClearCommand extends Command
{
    protected string $signature = 'config:clear';

    protected string $description = 'حذف فایل کش پیکربندی';

    public function handle(): int
    {
        /** @var Optimizer $optimizer */
        $this->app->make(Optimizer::class)->clear('config');
        $this->info('  ✓ configuration cache cleared.');

        return self::SUCCESS;
    }
}
