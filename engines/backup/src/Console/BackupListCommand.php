<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup\Console;

use Tavaan\Engine\Backup\BackupManager;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Backup
 * BackupListCommand — فهرست پشتیبان‌ها (DOC-068 §2).
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class BackupListCommand extends Command
{
    protected string $signature = 'backup:list';

    protected string $description = 'فهرست پشتیبان‌های موجود';

    public function handle(): int
    {
        /** @var BackupManager $backup */
        $backup = $this->app->make(BackupManager::class);

        $manifests = $backup->list();
        if ($manifests === []) {
            $this->comment('هیچ پشتیبانی یافت نشد.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($manifests as $manifest) {
            $rows[] = [
                $manifest->id,
                $manifest->type,
                (string) $manifest->createdAt,
                \sprintf('%.1fKB', $manifest->size / 1024),
                $manifest->encrypted ? '🔒' : '—',
                \sprintf('db:%s files:%d', $manifest->contents['database'] ? '✅' : '—', \count($manifest->contents['files'])),
            ];
        }

        $this->table(['ID', 'نوع', 'زمان', 'اندازه', 'رمزنگاری', 'محتوا'], $rows);

        return self::SUCCESS;
    }
}
