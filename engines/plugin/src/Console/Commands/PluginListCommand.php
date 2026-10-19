<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\PluginManager;

/**
 * `plugin:list` — فهرست افزونه‌ها با وضعیت (DOC-079 §7).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginListCommand extends Command
{
    protected string $signature = 'plugin:list';

    protected string $description = 'فهرست افزونه‌ها';

    public function handle(): int
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);

        $rows = [];
        foreach ($manager->list() as $plugin) {
            $rows[] = [
                $plugin->id,
                $plugin->version,
                $plugin->authorName !== '' ? $plugin->authorName : '-',
                $plugin->installed ? ($plugin->enabled ? '✅ فعال' : '⏸ غیرفعال') : '📦 نصب‌نشده',
                $manager->isLoaded($plugin->id) ? 'loaded' : '-',
            ];
        }

        if ($rows === []) {
            $this->info('  هیچ افزونه‌ای یافت نشد.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Version', 'Author', 'Status', 'Runtime'], $rows);

        return self::SUCCESS;
    }
}
