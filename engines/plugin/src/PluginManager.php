<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Plugin\Exceptions\PluginException;

/**
 * تَوان | Tavaan — Plugin
 * PluginManager — چرخهٔ عمر افزونه‌ها (DOC-079 §7).
 *
 * ```
 * install → verify (امضا+capabilities) → scan → copy → lock (hash) → enable
 * update  → backup → verify → scan → جایگزینی → relock → enable
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginManager
{
    private readonly PluginsLock $lock;

    private readonly PluginVerifier $verifier;

    private readonly PluginScanner $scanner;

    private readonly PluginSandbox $sandbox;

    /** @var array<string, Plugin> افزونه‌های loadشده در این فرایند */
    private array $loaded = [];

    /**
     * @param array<string, mixed> $config کلیدهای plugins.*
     */
    public function __construct(
        private readonly ContainerInterface $app,
        private readonly array $config = [],
        private readonly ?DispatcherInterface $events = null,
    ) {
        $this->lock = new PluginsLock((string) ($config['lock_file'] ?? $this->path() . '/plugins.lock'));
        $this->verifier = new PluginVerifier($config, $this->lock);
        $this->scanner = new PluginScanner();
        $this->sandbox = new PluginSandbox($app, $config);

        // اعلام hookهای مجاز سطح اپ (میزبان؛ علاوه بر module.json که
        // PluginServiceProvider اعلام می‌کند) — مستقل از container قابل استفاده باشد
        foreach ((array) ($config['allowed_hooks'] ?? []) as $hook) {
            if (\is_string($hook) && $hook !== '') {
                Hook::declare($hook);
            }
        }
    }

    // ─── چرخهٔ عمر ────────────────────────────────────────────────────────

    /**
     * نصب از یک دایرکتوری منبع: verify → scan → copy → lock → load.
     *
     * @throws PluginException
     */
    public function install(string $sourceDir, bool $force = false): Plugin
    {
        $this->assertEnabled();

        $sourceDir = \rtrim($sourceDir, '/\\');
        if (!\is_dir($sourceDir)) {
            throw PluginException::manifestInvalid($sourceDir, 'دایرکتوری منبع وجود ندارد');
        }

        $plugin = Plugin::fromDirectory($sourceDir);
        if ($this->lock->has($plugin->id)) {
            throw PluginException::alreadyInstalled($plugin->id);
        }
        $this->assertFrameworkCompatible($plugin);
        $this->verifier->verifyForInstall($plugin);

        $report = $this->scanner->scan($sourceDir);
        if (!$report->clean() && !$force) {
            throw PluginException::dangerousCode($plugin->id, $report->summary());
        }

        $target = $this->pluginDir($plugin->id);
        if ($target !== $sourceDir) {
            if (\is_dir($target)) {
                throw PluginException::alreadyInstalled($plugin->id);
            }
            self::copyDirectory($sourceDir, $target);
        }

        $plugin = Plugin::fromDirectory($target, enabled: true, installed: true);
        $this->lock->put($plugin->id, [
            'version' => $plugin->version,
            'enabled' => true,
            'installed_at' => \date('c'),
            'updated_at' => \date('c'),
            'files' => PluginsLock::hashDirectory($target),
        ]);

        $this->sandbox->load($plugin);
        $this->loaded[$plugin->id] = $plugin;
        $this->dispatch('plugin.installed', ['id' => $plugin->id, 'version' => $plugin->version]);

        return $plugin;
    }

    /**
     * به‌روزرسانی: backup → verify → scan → جایگزینی → relock → load.
     *
     * @throws PluginException
     */
    public function update(string $id, string $sourceDir, bool $force = false): Plugin
    {
        $this->assertEnabled();

        $current = $this->installedPlugin($id);

        // backup (DOC-079 §7)
        $backupDir = $this->backupDir();
        if (!\is_dir($backupDir) && !\mkdir($backupDir, 0775, true) && !\is_dir($backupDir)) {
            throw new \RuntimeException(\sprintf('Cannot create plugin backup dir [%s].', $backupDir));
        }
        $backup = $backupDir . '/' . $current->slug() . '-' . \date('Ymd-His');
        self::copyDirectory($current->path, $backup);

        $sourceDir = \rtrim($sourceDir, '/\\');
        $updated = Plugin::fromDirectory($sourceDir);
        if ($updated->id !== $id) {
            throw PluginException::manifestInvalid($sourceDir, 'id با افزونهٔ نصب‌شده نمی‌خواند');
        }
        $this->assertFrameworkCompatible($updated);
        $this->verifier->verifyForInstall($updated);

        $report = $this->scanner->scan($sourceDir);
        if (!$report->clean() && !$force) {
            throw PluginException::dangerousCode($id, $report->summary());
        }

        // جایگزینی اتمیک‌گونه: حذف قدیم → کپی جدید (backup گرفته شده)
        self::removeDirectory($current->path);
        if ($current->path !== $sourceDir) {
            self::copyDirectory($sourceDir, $current->path);
        }

        $plugin = Plugin::fromDirectory($current->path, enabled: true, installed: true);
        $entry = $this->lock->entry($id) ?? [];
        $this->lock->put($id, [
            ...$entry,
            'version' => $plugin->version,
            'enabled' => true,
            'updated_at' => \date('c'),
            'files' => PluginsLock::hashDirectory($plugin->path),
        ]);

        $this->sandbox->load($plugin);   // no-op اگر provider قبلاً boot شده
        $this->loaded[$id] = $plugin;
        $this->dispatch('plugin.updated', ['id' => $id, 'version' => $plugin->version, 'backup' => $backup]);

        return $plugin;
    }

    public function enable(string $id): void
    {
        $this->assertEnabled();
        $plugin = $this->installedPlugin($id);

        $this->lock->setEnabled($id, true);
        $this->verifier->verify($plugin);   // hashها باید با lock جدید هم‌خوان بماند

        if (!isset($this->loaded[$id])) {
            $this->sandbox->load($plugin);
            $this->loaded[$id] = $plugin;
        }
        $this->dispatch('plugin.enabled', ['id' => $id]);
    }

    public function disable(string $id): void
    {
        $plugin = $this->installedPlugin($id);

        $this->lock->setEnabled($id, false);
        $this->sandbox->unload($plugin);
        unset($this->loaded[$id]);
        $this->dispatch('plugin.disabled', ['id' => $id]);
    }

    /**
     * حذف نصب: disable → پاک‌کردن lock → (اختیاری) حذف فایل‌ها.
     */
    public function uninstall(string $id, bool $deleteFiles = false): void
    {
        $plugin = $this->installedPlugin($id);

        if ($this->lock->has($id)) {
            $this->lock->setEnabled($id, false);
        }
        $this->sandbox->unload($plugin);
        unset($this->loaded[$id]);
        $this->lock->remove($id);

        if ($deleteFiles && \is_dir($plugin->path)) {
            self::removeDirectory($plugin->path);
        }
        $this->dispatch('plugin.uninstalled', ['id' => $id, 'files_deleted' => $deleteFiles]);
    }

    // ─── پرس‌وجو ──────────────────────────────────────────────────────────

    /**
     * فهرست همهٔ افزونه‌ها (نصب‌شده از lock + کشف‌شده روی دیسک).
     *
     * @return list<Plugin>
     */
    public function list(): array
    {
        $plugins = [];
        $entries = $this->lock->read();

        foreach ($entries as $id => $entry) {
            $dir = $this->pluginDir((string) $id);
            if (!\is_dir($dir)) {
                continue;
            }
            try {
                $plugins[] = Plugin::fromDirectory(
                    $dir,
                    enabled: (bool) ($entry['enabled'] ?? false),
                    installed: true,
                );
            } catch (PluginException) {
                continue;   // manifest خراب → در list نمی‌آید؛ `plugin:verify` خطا می‌دهد
            }
        }

        // نصب‌نشده‌های روی دیسک (کشف از plugins/)
        foreach (\glob($this->path() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (!\is_file($dir . '/plugin.json')) {
                continue;
            }
            try {
                $candidate = Plugin::fromDirectory($dir);
            } catch (PluginException) {
                continue;
            }
            if (!isset($entries[$candidate->id])) {
                $plugins[] = $candidate;
            }
        }

        return $plugins;
    }

    public function isLoaded(string $id): bool
    {
        return isset($this->loaded[$id]);
    }

    /**
     * اعتبارسنجی کامل یک افزونهٔ نصب‌شده (امضا + capabilities + hash).
     *
     * @throws PluginException
     */
    public function verify(string $id): void
    {
        $this->verifier->verify($this->installedPlugin($id));
    }

    public function scan(string $dir): ScanReport
    {
        return $this->scanner->scan($dir);
    }

    public function lock(): PluginsLock
    {
        return $this->lock;
    }

    public function path(): string
    {
        $path = (string) ($this->config['path'] ?? '');
        if ($path === '') {
            $path = (\defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : '') . '/plugins';
        }

        return \rtrim($path, '/\\');
    }

    /**
     * بارگذاری افزونه‌های فعال در بوت (توسط PluginServiceProvider::boot).
     */
    public function loadEnabled(): void
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            return;
        }

        foreach ($this->lock->read() as $id => $entry) {
            if (!(bool) ($entry['enabled'] ?? false)) {
                continue;
            }
            $dir = $this->pluginDir((string) $id);
            if (!\is_dir($dir) || isset($this->loaded[$id])) {
                continue;
            }
            try {
                $plugin = Plugin::fromDirectory($dir, enabled: true, installed: true);
                $this->verifier->verify($plugin);
                $this->sandbox->load($plugin);
                $this->loaded[(string) $id] = $plugin;
            } catch (PluginException $e) {
                // افزونهٔ خراب/دستکاری‌شده نباید بوت میزبان را بشکند —
                // غیرفعال‌سازی خودکار + رویداد برای audit
                $this->lock->setEnabled((string) $id, false);
                $this->dispatch('plugin.load_failed', ['id' => (string) $id, 'reason' => $e->messageId]);
            }
        }
    }

    // ─── internals ────────────────────────────────────────────────────────

    public function pluginDir(string $id): string
    {
        return $this->path() . '/' . \str_replace('.', '-', $id);
    }

    private function installedPlugin(string $id): Plugin
    {
        if (!$this->lock->has($id)) {
            throw PluginException::notFound($id);
        }
        $dir = $this->pluginDir($id);
        if (!\is_dir($dir)) {
            throw PluginException::notFound($id);
        }
        $entry = $this->lock->entry($id) ?? [];

        return Plugin::fromDirectory($dir, enabled: (bool) ($entry['enabled'] ?? false), installed: true);
    }

    private function assertEnabled(): void
    {
        if (!(bool) ($this->config['enabled'] ?? true)) {
            throw PluginException::disabled();
        }
    }

    private function assertFrameworkCompatible(Plugin $plugin): void
    {
        $version = $this->app->bound('config')
            ? (string) ($this->app->make('config')->get('app.version', '1.0.0') ?? '1.0.0')
            : '1.0.0';
        if (!$plugin->supportsFramework($version)) {
            throw PluginException::manifestInvalid(
                $plugin->path,
                \sprintf('constraint نسخهٔ tavaan (%s) با %s سازگار نیست', (string) ($plugin->manifest['tavaan'] ?? ''), $version),
            );
        }
    }

    private function backupDir(): string
    {
        $configured = (string) ($this->config['backup_path'] ?? '');
        if ($configured !== '') {
            return \rtrim($configured, '/\\');
        }
        $base = \defined('TVN_BASE_PATH') ? (string) TVN_BASE_PATH : \dirname($this->path());

        return $base . '/storage/backups/plugins';
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(string $event, array $payload): void
    {
        $this->events?->dispatch($event, $payload);
    }

    private static function copyDirectory(string $from, string $to): void
    {
        if (!\is_dir($to) && !\mkdir($to, 0775, true) && !\is_dir($to)) {
            throw new \RuntimeException(\sprintf('Cannot create directory [%s].', $to));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            $target = $to . '/' . \substr(\str_replace('\\', '/', $item->getPathname()), \strlen(\str_replace('\\', '/', $from)) + 1);
            if ($item->isDir()) {
                if (!\is_dir($target)) {
                    \mkdir($target, 0775, true);
                }
                continue;
            }
            $dir = \dirname($target);
            if (!\is_dir($dir)) {
                \mkdir($dir, 0775, true);
            }
            \copy($item->getPathname(), $target);
        }
    }

    private static function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
        }
        \rmdir($dir);
    }
}
