<?php

declare(strict_types=1);

namespace Tavaan\Engine\Migration\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Database\Migration\Migrator;

/**
 * تَوان | Tavaan — Migration
 * MigrateRefreshCommand — rollback همه + migrate (DOC-049 §6).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrateRefreshCommand extends Command
{
    protected string $signature = 'migrate:refresh {--step=0 : تعداد batch (0 = همه)} {--force : بدون تأیید}';

    protected string $description = 'بازگرداندن همهٔ migrationها و اجرای مجدد';

    public function handle(): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->make(Migrator::class);
        $migrator->setOutput(fn (string $line): mixed => $this->line($line));

        if (!(bool) $this->option('force') && !$this->confirm('همهٔ migrationها بازگردانده و دوباره اجرا می‌شوند. ادامه؟', false)) {
            $this->warn('لغو شد.');

            return self::SUCCESS;
        }

        $step = (int) ($this->option('step') ?? 0);
        $result = $migrator->refresh($step);

        $this->info(\sprintf('refresh کامل شد؛ %d migration اجرا شد.', $result->count()));

        return self::SUCCESS;
    }
}
