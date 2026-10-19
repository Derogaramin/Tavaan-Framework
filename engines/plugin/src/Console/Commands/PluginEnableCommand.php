<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\PluginManager;

/**
 * `plugin:enable <id>` — فعال‌سازی افزونهٔ نصب‌شده (DOC-079 §7).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginEnableCommand extends Command
{
    protected string $signature = 'plugin:enable {id : شناسهٔ افزونه}';

    protected string $description = 'فعال‌سازی افزونه';

    public function handle(): int
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);

        try {
            $manager->enable((string) $this->argument('id', ''));
        } catch (PluginException $e) {
            $this->error('  ✗ ' . $e->messageId . ' — ' . ($e->hint ?? ''));

            return self::FAILURE;
        }

        $this->info('  ✓ افزونه فعال شد.');

        return self::SUCCESS;
    }
}
