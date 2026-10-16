<?php

declare(strict_types=1);

namespace Tavaan\Engine\Console\Commands;

use Tavaan\Core\Autoloading\AutoloaderRegistry;
use Tavaan\Engine\Compat\Compat;
use Tavaan\Engine\Console\Command;

/**
 * اطلاعات کامل پروژه/محیط (AC-STR-02، AC-CLI-02).
 *
 * @api 1.0
 * @see docs/04-structure/cli-reference.md
 */
final class AboutCommand extends Command
{
    protected string $signature = 'about {--json : خروجی JSON (با mask شدن secretها)}';

    protected string $description = 'نمایش اطلاعات نسخه، محیط، موتورها و ماژول‌ها';

    public function handle(): int
    {
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '';
        $config = $this->app->bound('config') ? $this->app->make('config') : null;
        $modules = $this->app->bound('modules') ? $this->app->make('modules') : null;
        $bootMs = (\microtime(true) - (float) (\defined('TVN_START') ? TVN_START : \microtime(true))) * 1000;

        $env = (string) ($config?->get('app.env', 'production') ?? 'production');
        $debug = (bool) ($config?->get('app.debug', false) ?? false);
        $counts = $modules?->counts() ?? ['enabled' => 0, 'total' => 0];

        if ($this->option('json')) {
            $data = [
                'tavaan' => \defined('TVN_VERSION') ? TVN_VERSION : 'dev',
                'php' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'env' => $env,
                'debug' => $debug,
                'timezone' => \date_default_timezone_get(),
                'base_path' => $base,
                'engines' => $counts,
                'boot_ms' => \round($bootMs, 2),
                'config' => $config?->masked(),
            ];
            $this->line((string) \json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $autoloadStats = AutoloaderRegistry::has() ? AutoloaderRegistry::get()->stats()->toArray() : [];

        $this->newLine();
        $this->output->writeln('  تَوان | Tavaan — About', 'bold');
        $this->newLine();

        $this->table(['کلید', 'مقدار'], [
            ['Version', \defined('TVN_VERSION') ? TVN_VERSION : 'dev'],
            ['PHP', PHP_VERSION . ' (' . PHP_SAPI . ')'],
            ['Environment', $env],
            ['Debug', $debug ? 'true' : 'false'],
            ['Timezone', \date_default_timezone_get()],
            ['Base Path', $base],
            ['Boot Time', \sprintf('%.2f ms', $bootMs)],
            ['Memory', \round(\memory_get_peak_usage(true) / 1048576, 2) . ' MB'],
            ['Engines/Modules', $counts['enabled'] . '/' . $counts['total'] . ' enabled'],
            ['Autoload', \sprintf('classmap:%d prefix:%d wildcard:%d miss:%d', $autoloadStats['classmap_hits'] ?? 0, $autoloadStats['prefix_hits'] ?? 0, $autoloadStats['wildcard_hits'] ?? 0, $autoloadStats['misses'] ?? 0)],
        ]);

        $this->newLine();
        $this->output->writeln('  Storage', 'info');
        $this->table(['مسیر', 'وضعیت'], [
            ['storage/logs', \is_writable($base . '/storage/logs') ? '✅ writable' : '❌ not writable'],
            ['storage/cache', \is_writable($base . '/storage/cache') ? '✅ writable' : '❌ not writable'],
            ['storage/framework', \is_writable($base . '/storage/framework') ? '✅ writable' : '❌ not writable'],
            ['.env', \is_file($base . '/.env') ? '✅ found' : '⚠️ missing (cp .env.example .env)'],
        ]);

        $this->newLine();
        $this->output->writeln('  Drivers (Compat)', 'info');
        $drivers = [];
        foreach (['math', 'zip', 'http', 'unicode', 'mime', 'image'] as $capability) {
            $drivers[] = [$capability, Compat::driver($capability)];
        }
        $this->table(['capability', 'driver'], $drivers);
        $this->newLine();

        return self::SUCCESS;
    }
}
