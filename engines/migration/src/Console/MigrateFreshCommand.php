<?php

declare(strict_types=1);

namespace Tavaan\Engine\Migration\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Database\Migration\Exceptions\MigrationException;
use Tavaan\Engine\Database\Migration\Migrator;

/**
 * تَوان | Tavaan — Migration
 * MigrateFreshCommand — drop همه + migrate (خطرناک — DOC-049 §6/§7).
 *
 * 🔒 در production بدون --force رد می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrateFreshCommand extends Command
{
    protected string $signature = 'migrate:fresh {--force : بدون تأیید (production)}';

    protected string $description = 'حذف همهٔ جداول و اجرای مجدد migrationها (⚠️ خطرناک)';

    public function handle(): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->make(Migrator::class);
        $migrator->setOutput(fn (string $line): mixed => $this->line($line));

        $force = (bool) $this->option('force');

        if (!$force && !$this->confirm('همهٔ جداول و داده‌ها حذف می‌شوند! ادامه می‌دهید؟', false)) {
            $this->warn('لغو شد.');

            return self::SUCCESS;
        }

        try {
            $result = $migrator->fresh(force: $force);
        } catch (MigrationException $e) {
            $this->error($e->messageId . ($e->hint !== null ? ' — ' . $e->hint : ''));

            return self::FAILURE;
        }

        $this->info(\sprintf('دیتابیس بازسازی شد؛ %d migration اجرا شد.', $result->count()));

        return self::SUCCESS;
    }
}
