<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup\Console;

use Tavaan\Engine\Backup\BackupManager;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Backup
 * BackupRunCommand — ساخت پشتیبان (DOC-068 §11).
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class BackupRunCommand extends Command
{
    protected string $signature = 'backup:run {--type=full : نوع پشتیبان (full|database|files)}';

    protected string $description = 'ساخت پشتیبان رمزنگاری‌شده (DB + فایل‌ها)';

    public function handle(): int
    {
        /** @var BackupManager $backup */
        $backup = $this->app->make(BackupManager::class);

        $type = (string) ($this->option('type') ?? 'full');
        if (!\in_array($type, ['full', 'database', 'files'], true)) {
            $this->error('نوع نامعتبر: ' . $type);

            return self::FAILURE;
        }

        $this->info('در حال ساخت پشتیبان (' . $type . ')…');
        $started = \microtime(true);

        try {
            $manifest = $backup->run($type);
        } catch (\Throwable $e) {
            $this->error('پشتیبان‌گیری شکست خورد: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(\sprintf(
            '✅ پشتیبان %s ساخته شد (%s — %.1fKB — %dms)',
            $manifest->id,
            $manifest->type,
            $manifest->size / 1024,
            (int) \round((\microtime(true) - $started) * 1000),
        ));
        $this->comment('hash: ' . $manifest->hash);

        return self::SUCCESS;
    }
}
