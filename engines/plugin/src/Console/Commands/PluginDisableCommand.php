<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\PluginManager;

/**
 * `plugin:disable <id>` — غیرفعال‌سازی (قطع hookها + عدم بارگذاری در بوت بعدی).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginDisableCommand extends Command
{
    protected string $signature = 'plugin:disable {id : شناسهٔ افزونه}';

    protected string $description = 'غیرفعال‌سازی افزونه';

    public function handle(): int
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);

        try {
            $manager->disable((string) $this->argument('id', ''));
        } catch (PluginException $e) {
            $this->error('  ✗ ' . $e->messageId . ' — ' . ($e->hint ?? ''));

            return self::FAILURE;
        }

        $this->info('  ✓ افزونه غیرفعال شد.');

        return self::SUCCESS;
    }
}
