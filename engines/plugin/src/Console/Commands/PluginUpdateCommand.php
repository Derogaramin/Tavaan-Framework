<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\PluginManager;

/**
 * `plugin:update <id> <path>` — backup → verify → migrate → enable (DOC-079 §7).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginUpdateCommand extends Command
{
    protected string $signature = 'plugin:update {id : شناسهٔ افزونه} {path : مسیر نسخهٔ جدید} {--force : تأیید صریح یافته‌های scan}';

    protected string $description = 'به‌روزرسانی افزونه (با پشتیبان‌گیری)';

    public function handle(): int
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);

        try {
            $plugin = $manager->update(
                (string) $this->argument('id', ''),
                (string) $this->argument('path', ''),
                (bool) $this->option('force'),
            );
        } catch (PluginException $e) {
            $this->error('  ✗ ' . $e->messageId . ' — ' . ($e->hint ?? ''));
            $this->output->writeln('    ' . (string) \json_encode($e->context, JSON_UNESCAPED_UNICODE), 'dim');

            return self::FAILURE;
        }

        $this->info('  ✓ افزونهٔ ' . $plugin->id . ' به v' . $plugin->version . ' به‌روزرسانی شد (backup در storage/backups/plugins).');

        return self::SUCCESS;
    }
}
