<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin\Console\Commands;

use Tavaan\Engine\Console\Command;
use Tavaan\Engine\Plugin\Exceptions\PluginException;
use Tavaan\Engine\Plugin\PluginManager;

/**
 * `plugin:verify <id>` — بررسی امضا + hash فایل‌ها (DOC-079 §7).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginVerifyCommand extends Command
{
    protected string $signature = 'plugin:verify {id : شناسهٔ افزونه}';

    protected string $description = 'اعتبارسنجی امضا و یکپارچگی فایل‌های افزونه';

    public function handle(): int
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);
        $id = (string) $this->argument('id', '');

        try {
            $manager->verify($id);
        } catch (PluginException $e) {
            $this->error('  ✗ اعتبارسنجی ناموفق: ' . $e->messageId);
            $this->output->writeln('    ' . (string) \json_encode($e->context, JSON_UNESCAPED_UNICODE), 'dim');

            return self::FAILURE;
        }

        $this->info('  ✓ امضا و hash همهٔ فایل‌های ' . $id . ' معتبر است.');

        return self::SUCCESS;
    }
}
