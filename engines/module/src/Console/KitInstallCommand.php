<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — Module
 * KitInstallCommand — `tvn kit:install <kit>` (DOC-090 §8 / AC-APP-01).
 */

namespace Tavaan\Engine\Module\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Module\Kit\KitException;
use Tavaan\Engine\Module\Kit\KitManager;

/**
 * نصب کیت اپلیکیشنی: requirements → modules → preset → migrate →
 * rbac/seed → theme → status.
 *
 * ```
 * tvn kit:install blog
 * tvn kit:install blog --preset=enterprise --with-demo
 * ```
 *
 * @api 1.0
 * @see docs/05-applications/README.md
 */
final class KitInstallCommand extends Command
{
    protected string $signature = 'kit:install {kit : نام/شناسهٔ کیت (مثلاً blog)}'
        . ' {--preset= : نام preset (پیش‌فرض: default_preset کیت)}'
        . ' {--with-demo : نصب دادهٔ نمونه}'
        . ' {--skip-migrate : بدون اجرای migration}'
        . ' {--skip-seed : بدون اجرای seederها}';

    protected string $description = 'نصب کیت اپلیکیشنی (ماژول‌ها + preset + migrate + seed + theme)';

    public function handle(): int
    {
        /** @var KitManager $kits */
        $kits = $this->app->make(KitManager::class);

        $kit = (string) $this->argument('kit');
        if (!$kits->has($kit)) {
            $this->error('کیت یافت نشد: ' . $kit);
            $this->comment('کیت‌های موجود را با `tvn kit:list` ببینید.');

            return self::FAILURE;
        }

        $preset = $this->option('preset');

        try {
            $report = $kits->install($kit, [
                'preset' => \is_string($preset) && $preset !== '' ? $preset : null,
                'with_demo' => (bool) $this->option('with-demo'),
                'skip_migrate' => (bool) $this->option('skip-migrate'),
                'skip_seed' => (bool) $this->option('skip-seed'),
            ]);
        } catch (KitException $e) {
            $this->error($e->messageId);
            foreach ((array) ($e->context['problems'] ?? $e->context['missing'] ?? []) as $problem) {
                $this->line('  • ' . (string) $problem);
            }
            if (isset($e->hint)) {
                $this->comment($e->hint);
            }

            return self::FAILURE;
        }

        $this->info('✓ کیت «' . $report['kit'] . '» (v' . $report['version'] . ') نصب شد — preset: ' . $report['preset']);
        foreach ((array) $report['steps'] as $step) {
            $this->line('  • ' . (string) $step);
        }
        $this->comment('  💡 برای عملکرد production: `tvn optimize` را اجرا کنید.');

        return self::SUCCESS;
    }
}
