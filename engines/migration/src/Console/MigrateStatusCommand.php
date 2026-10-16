<?php

declare(strict_types=1);

namespace Tavaan\Engine\Migration\Console;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Database\Migration\Migrator;

/**
 * تَوان | Tavaan — Migration
 * MigrateStatusCommand — وضعیت migrationها (DOC-049 §6).
 *
 * @api 1.0
 * @see docs/03-engines/migration.md
 */
final class MigrateStatusCommand extends Command
{
    protected string $signature = 'migrate:status';

    protected string $description = 'نمایش وضعیت migrationها (اجراشده/در انتظار)';

    public function handle(): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->make(Migrator::class);

        $rows = [];
        foreach ($migrator->status() as $item) {
            $rows[] = [
                $item['migration'],
                $item['ran'] ? '<info>Ran (batch ' . (string) $item['batch'] . ')</info>' : '<comment>Pending</comment>',
                (string) ($item['module'] ?? '-'),
            ];
        }

        if ($rows === []) {
            $this->warn('هیچ migration یافت نشد.');

            return self::SUCCESS;
        }

        $this->table(['Migration', 'وضعیت', 'ماژول'], $rows);

        return self::SUCCESS;
    }
}
