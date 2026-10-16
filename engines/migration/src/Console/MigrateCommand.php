<?php

declare(strict_types=1);

namespace Tavaan\Engine\Migration\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Database\Migration\Migrator;

/**
 * تَوان | Tavaan — Migration
 * MigrateCommand — اجرای migrationهای در انتظار (DOC-049 §6).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrateCommand extends Command
{
    protected string $signature = 'migrate {--dry-run : نمایش بدون اجرا} {--module= : فقط یک ماژول} {--path= : مسیر سفارشی}';

    protected string $description = 'اجرای migrationهای در انتظار';

    public function handle(): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->make(Migrator::class);
        $migrator->setOutput(fn (string $line): mixed => $this->line($line));

        $paths = [];
        $path = $this->option('path');
        if (\is_string($path) && $path !== '') {
            $paths[] = $path;
        }

        $options = ['dry_run' => (bool) $this->option('dry-run')];
        $module = $this->option('module');
        if (\is_string($module) && $module !== '') {
            $options['module'] = $module;
        }

        try {
            $result = $migrator->run($paths, $options);
        } catch (\Tavaan\Engine\Database\Migration\Exceptions\MigrationLockedException $e) {
            $this->error($e->hint ?? 'migration.locked');

            return self::FAILURE;
        }

        $this->info(\sprintf('%d migration %s.', $result->count(), $result->dryRun ? 'در صف است (dry-run)' : 'اجرا شد'));

        return self::SUCCESS;
    }
}
