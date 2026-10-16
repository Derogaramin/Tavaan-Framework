<?php

declare(strict_types=1);

namespace Tavaan\Engine\Audit\Console;

use Tavaan\Engine\Audit\Auditor;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Audit
 * AuditPruneCommand — حذف رکوردهای قدیمی‌تر از retention (DOC-058 §8).
 *
 * ⚠️ حذف از زنجیره، verify را روی رکوردهای باقی‌مانده از نقطهٔ GENESIS
 * بازنشانی نمی‌کند — prune باید با policy نگهداری مطابق باشد.
 *
 * @api 1.0
 * @see docs/03-engines/audit.md
 */
final class AuditPruneCommand extends Command
{
    protected string $signature = 'audit:prune {--older-than= : روز (پیش‌فرض از config retention_days)} {--force}';

    protected string $description = 'حذف رکوردهای ممیزی قدیمی';

    public function handle(): int
    {
        /** @var Auditor $auditor */
        $auditor = $this->app->make(Auditor::class);

        $days = $this->option('older-than');
        $days = \is_string($days) && $days !== ''
            ? (int) $days
            : (int) ($this->app->bound('config') ? $this->app->make('config')->get('audit.retention_days', 1825) : 1825);

        if (!(bool) $this->option('force') && !$this->confirm(\sprintf('%d روز اخیر نگه داشته می‌شود؛ قدیمی‌ترها حذف شوند؟', $days), false)) {
            $this->warn('لغو شد.');

            return self::SUCCESS;
        }

        $deleted = $auditor->prune($days);
        $this->info(\sprintf('%d رکورد حذف شد.', $deleted));

        return self::SUCCESS;
    }
}
