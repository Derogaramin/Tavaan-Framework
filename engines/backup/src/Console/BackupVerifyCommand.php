<?php

declare(strict_types=1);

namespace Tavaan\Engine\Backup\Console;

use Tavaan\Engine\Backup\BackupManager;
use Tavaan\Engine\Console\Command;

/**
 * تَوان | Tavaan — Backup
 * BackupVerifyCommand — بررسی یکپارچگی پشتیبان (DOC-068 §2).
 *
 * @api 1.0
 * @see docs/03-engines/backup.md
 */
final class BackupVerifyCommand extends Command
{
    protected string $signature = 'backup:verify {id : شناسهٔ پشتیبان}';

    protected string $description = 'بررسی یکپارچگی پشتیبان (hash + decrypt)';

    public function handle(): int
    {
        /** @var BackupManager $backup */
        $backup = $this->app->make(BackupManager::class);

        $id = (string) $this->argument('id');

        try {
            $backup->verify($id);
        } catch (\Throwable $e) {
            $this->error('❌ بررسی ناموفق: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('✅ پشتیبان ' . $id . ' سالم است (hash + decrypt).');

        return self::SUCCESS;
    }
}
