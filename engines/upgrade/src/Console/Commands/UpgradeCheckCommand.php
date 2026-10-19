<?php

declare(strict_types=1);

namespace Tavaan\Engine\Upgrade\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Upgrade\Upgrader;

/**
 * `tvn upgrade:check [--to=X.Y.Z]` — بررسی سازگاری بدون اجرا (DOC-081E §7).
 *
 * @api 1.0
 * @see docs/03-engines/upgrade.md
 */
final class UpgradeCheckCommand extends Command
{
    protected string $signature = 'upgrade:check {--to= : نسخهٔ هدف برای محاسبهٔ مسیر}';

    protected string $description = 'بررسی سازگاری ارتقا (PHP/افزونه‌ها/فضا/مسیر نسخه)';

    public function handle(): int
    {
        /** @var Upgrader $upgrader */
        $upgrader = $this->app->make(Upgrader::class);

        $to = $this->option('to');
        $report = $upgrader->check(\is_string($to) && $to !== '' ? $to : null);

        $this->info('  PHP: ' . $report['php']);
        $this->info('  نسخهٔ جاری: ' . $upgrader->currentVersion());

        if ($report['path'] !== null) {
            $this->info('  مسیر ارتقا: ' . \implode(' → ', $report['path']));
        }

        if ($report['ok']) {
            $this->info('  ✓ سازگار — آمادهٔ ارتقا.');

            return self::SUCCESS;
        }

        foreach ($report['problems'] as $problem) {
            $this->error('  ✗ ' . $problem);
        }

        return self::FAILURE;
    }
}
