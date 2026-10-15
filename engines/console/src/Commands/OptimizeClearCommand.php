<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Optimizer\Optimizer;

/**
 * حذف همهٔ کش‌ها.
 *
 * @api 1.0
 * @see docs/03-engines/optimizer.md
 */
final class OptimizeClearCommand extends Command
{
    protected string $signature = 'optimize:clear';

    protected string $description = 'حذف همهٔ کش‌های زمان اجرا';

    public function handle(): int
    {
        /** @var Optimizer $optimizer */
        $optimizer = $this->app->make(Optimizer::class);
        $optimizer->clear();

        $this->info('  ✓ همهٔ کش‌ها پاک شدند.');

        return self::SUCCESS;
    }
}
