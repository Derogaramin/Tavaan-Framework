<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\PluginManager;

/**
 * `plugin:scan <path>` — اسکن توابع خطرناک پیش از نصب (DOC-079 §5.1).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginScanCommand extends Command
{
    protected string $signature = 'plugin:scan {path : مسیر دایرکتوری افزونه}';

    protected string $description = 'اسکن کد خطرناک در افزونه';

    public function handle(): int
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);

        $report = $manager->scan((string) $this->argument('path', ''));

        if ($report->clean()) {
            $this->info('  ✓ ' . $report->scannedFiles . ' فایل اسکن شد — هیچ مورد خطرناکی یافت نشد.');

            return self::SUCCESS;
        }

        $this->error('  ⚠ ' . $report->count() . ' یافته در ' . $report->scannedFiles . ' فایل:');
        foreach ($report->summary(30) as $line) {
            $this->output->writeln('    ' . $line, 'comment');
        }

        return self::FAILURE;
    }
}
