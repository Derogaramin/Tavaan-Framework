<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\PluginManager;

/**
 * `plugin:install <path>` — verify → scan → lock → enable (DOC-079 §7).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginInstallCommand extends Command
{
    protected string $signature = 'plugin:install {path : مسیر دایرکتوری افزونه} {--force : تأیید صریح یافته‌های scan (admin)}';

    protected string $description = 'نصب افزونه (امضا + scan + lock)';

    public function handle(): int
    {
        $path = (string) $this->argument('path', '');
        if ($path === '') {
            $this->error('  ✗ مسیر افزونه الزامی است.');

            return self::FAILURE;
        }

        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);

        try {
            $plugin = $manager->install($path, (bool) $this->option('force'));
        } catch (PluginException $e) {
            $this->error('  ✗ نصب ناموفق: ' . $e->messageId);
            $this->output->writeln('    ' . ($e->hint ?? ''), 'comment');
            $this->output->writeln('    ' . (string) \json_encode($e->context, JSON_UNESCAPED_UNICODE), 'dim');

            return self::FAILURE;
        }

        $this->info('  ✓ افزونهٔ ' . $plugin->id . ' v' . $plugin->version . ' نصب و فعال شد.');
        $this->output->writeln('    مسیر: ' . $plugin->path, 'dim');

        return self::SUCCESS;
    }
}
