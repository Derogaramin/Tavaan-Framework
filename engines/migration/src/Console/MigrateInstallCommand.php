<?php

declare(strict_types=1);

namespace Tavaan\Engine\Migration\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Database\Migration\Migrator;

/**
 * تَوان | Tavaan — Migration
 * MigrateInstallCommand — ساخت جدول tvn_migrations (DOC-049 §6).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrateInstallCommand extends Command
{
    protected string $signature = 'migrate:install';

    protected string $description = 'ساخت جدول repository مهاجرت‌ها (tvn_migrations)';

    public function handle(): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->make(Migrator::class);
        $migrator->setOutput(fn (string $line): mixed => $this->line($line));

        $migrator->install();
        $this->info('انجام شد.');

        return self::SUCCESS;
    }
}
