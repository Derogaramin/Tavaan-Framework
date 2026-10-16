<?php

declare(strict_types=1);

namespace Tavaan\Engine\Migration\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Database\Migration\Migrator;

/**
 * تَوان | Tavaan — Migration
 * MigrateRollbackCommand — بازگشت آخرین batch (DOC-049 §6).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrateRollbackCommand extends Command
{
    protected string $signature = 'migrate:rollback {--step=1 : تعداد batch}';

    protected string $description = 'بازگرداندن آخرین batch از migrationها';

    public function handle(): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->make(Migrator::class);
        $migrator->setOutput(fn (string $line): mixed => $this->line($line));

        if (!$this->confirm('rollback داده‌ها را تغییر می‌دهد. ادامه می‌دهید؟', false)) {
            $this->warn('لغو شد.');

            return self::SUCCESS;
        }

        $step = (int) ($this->option('step') ?? 1);
        $result = $migrator->rollback(\max(1, $step));

        $this->info(\sprintf('%d migration بازگردانده شد.', $result->count()));

        return self::SUCCESS;
    }
}
