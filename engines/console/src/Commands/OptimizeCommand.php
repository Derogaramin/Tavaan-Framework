<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Optimizer\Optimizer;

/**
 * تولید همهٔ کش‌ها (AC-BOOT-10).
 *
 * @api 1.0
 * @see docs/03-engines/optimizer.md
 */
final class OptimizeCommand extends Command
{
    protected string $signature = 'optimize';

    protected string $description = 'تولید همهٔ کش‌های زمان اجرا (config, autoload, …)';

    public function handle(): int
    {
        /** @var Optimizer $optimizer */
        $optimizer = $this->app->make(Optimizer::class);

        $this->info('  در حال بهینه‌سازی…');
        $result = $optimizer->optimize();

        $rows = [];
        foreach ($result->timings as $name => $ms) {
            $rows[] = [$name, \sprintf('%.1f ms', $ms), '✅'];
        }
        $this->table(['Cache', 'Time', 'Status'], $rows);
        $this->info(\sprintf('  ✓ انجام شد (%.1f ms).', $result->total()));

        return self::SUCCESS;
    }
}
