<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup\Console;

use Tavaan\Engine\Backup\BackupManager;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Backup
 * BackupPruneCommand — هرس پشتیبان‌های قدیمی (DOC-068 §7: retention).
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class BackupPruneCommand extends Command
{
    protected string $signature = 'backup:prune {--days=30 : نگهداری پشتیبان‌های جدیدتر از این تعداد روز} {--retention : اعمال سیاست daily/weekly/monthly}';

    protected string $description = 'حذف پشتیبان‌های قدیمی (retention)';

    public function handle(): int
    {
        /** @var BackupManager $backup */
        $backup = $this->app->make(BackupManager::class);

        if ($this->option('retention')) {
            $deleted = $backup->applyRetention();
            $this->info(\sprintf('%d پشتیبان طبق سیاست retention حذف شد.', $deleted));

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?? 30);
        $deleted = $backup->prune(\max(0, $days));
        $this->info(\sprintf('%d پشتیبان قدیمی‌تر از %d روز حذف شد.', $deleted, \max(0, $days)));

        return self::SUCCESS;
    }
}
