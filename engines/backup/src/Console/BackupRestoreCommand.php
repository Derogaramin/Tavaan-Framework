<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup\Console;

use Tavaan\Engine\Backup\BackupManager;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Backup
 * BackupRestoreCommand — بازیابی پشتیبان (DOC-068 §8).
 *
 * 🔒 مخرب است — بدون `--force` اجرا نمی‌شود و در maintenance mode انجام می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class BackupRestoreCommand extends Command
{
    protected string $signature = 'backup:restore {id : شناسهٔ پشتیبان} {--force : تأیید overwrite} {--files-only : فقط فایل‌ها} {--db-only : فقط دیتابیس}';

    protected string $description = 'بازیابی پشتیبان (⚠️ مخرب — نیازمند --force)';

    public function handle(): int
    {
        /** @var BackupManager $backup */
        $backup = $this->app->make(BackupManager::class);

        $id = (string) $this->argument('id');
        $force = (bool) $this->option('force');

        if (!$force && !$this->confirm('بازیابی پشتیبان ' . $id . ' دادهٔ موجود را بازنویسی می‌کند. ادامه؟', false)) {
            $this->comment('لغو شد.');

            return self::SUCCESS;
        }

        $this->warn('⚠️ در حال بازیابی پشتیبان ' . $id . ' — سایت در maintenance mode قرار می‌گیرد.');

        try {
            $backup->restore($id, force: true);
        } catch (\Throwable $e) {
            $this->error('بازیابی شکست خورد: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('✅ بازیابی کامل شد.');

        return self::SUCCESS;
    }
}
