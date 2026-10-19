<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\PluginManager;

/**
 * `plugin:uninstall <id>` — disable → cleanup با تأیید صریح `--purge` (DOC-079 §7).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginUninstallCommand extends Command
{
    protected string $signature = 'plugin:uninstall {id : شناسهٔ افزونه} {--purge : حذف فایل‌ها از دیسک (مخرب)}';

    protected string $description = 'حذف نصب افزونه (با تأیید)';

    public function handle(): int
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);
        $id = (string) $this->argument('id', '');

        try {
            $manager->uninstall($id, (bool) $this->option('purge'));
        } catch (PluginException $e) {
            $this->error('  ✗ ' . $e->messageId . ' — ' . ($e->hint ?? ''));

            return self::FAILURE;
        }

        $this->info('  ✓ افزونهٔ ' . $id . ' حذف نصب شد' . ($this->option('purge') ? ' (فایل‌ها پاک شدند)' : ' (فایل‌ها باقی‌اند)') . '.');

        return self::SUCCESS;
    }
}
