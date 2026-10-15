<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Module\ModuleRegistry;

/**
 * فهرست ماژول‌ها/موتورهای کشف‌شده.
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class ModuleListCommand extends Command
{
    protected string $signature = 'module:list {--all : شامل غیرفعال‌ها}';

    protected string $description = 'فهرست ماژول‌ها و موتورها با وضعیت';

    public function handle(): int
    {
        /** @var ModuleRegistry $registry */
        $registry = $this->app->make(ModuleRegistry::class);

        $rows = [];
        foreach ($registry->sorted() as $module) {
            $rows[] = [
                $module->id(),
                $module->version(),
                $module->type(),
                $module->enabled() ? '✅ active' : '⛔ disabled',
            ];
        }
        foreach ($registry->all() as $module) {
            if (!$module->enabled() && $this->option('all')) {
                $rows[] = [$module->id(), $module->version(), $module->type(), '⛔ disabled'];
            }
        }

        $this->table(['ID', 'Version', 'Type', 'Status'], $rows);

        return self::SUCCESS;
    }
}
