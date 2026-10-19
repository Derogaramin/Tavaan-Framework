<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Autoloading\AutoloaderRegistry;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Plugin
 * PluginSandbox — بارگذاری ایزولهٔ افزونه (DOC-079 §5).
 *
 * ۱) PSR-4 اختصاصی افزونه روی Autoloader هسته (بدون prepend → هسته اولویت دارد؛
 *    prefixهای قفل‌شدهٔ هسته هم هرگز override نمی‌شوند — Autoloader locked_prefixes)
 * ۲) vendor افزونه (معافیت صفر‌وابستگی) با ثبت append — کلاس‌های هسته/موتورها
 *    بر vendor افزونه اولویت دارند (ضدتداخل)
 * ۳) ثبت capabilities در registry زمان‌اجرا
 * ۴) register + boot پراوایدر افزونه
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginSandbox
{
    /** @var array<string, true> پراوایدرهای boot‌شده (ضد double-boot) */
    private array $booted = [];

    /** @var array<string, true> loaderهای PSR-4 ثبت‌شده برای مسیرهای خارج از base */
    private array $fallbackLoaders = [];

    /**
     * @param array<string, mixed> $config کلیدهای plugins.*
     */
    public function __construct(
        private readonly ContainerInterface $app,
        private readonly array $config = [],
    ) {
    }

    public function load(Plugin $plugin): void
    {
        // ۱) autoload فضای‌نام افزونه
        $base = \defined('TVN_BASE_PATH') ? \str_replace('\\', '/', (string) TVN_BASE_PATH) : '';
        $pluginPath = \str_replace('\\', '/', $plugin->path);
        $insideBase = $base !== '' && \str_starts_with($pluginPath, $base . '/');

        if ($insideBase) {
            // روی Autoloader هسته (append → هسته اولویت دارد؛ prefixهای
            // قفل‌شده هرگز override نمی‌شوند — Autoloader locked_prefixes)
            $autoloader = AutoloaderRegistry::get();
            foreach ($plugin->psr4 as $prefix => $relativeDir) {
                $autoloader->addNamespace($prefix, $plugin->path . '/' . $relativeDir, prepend: false);
            }
        } else {
            // مسیر خارج از base (plugins.path سفارشی/تست‌ها) — Autoloader هسته
            // sandbox دارد؛ loader سبک PSR-4 افزونه با ثبت append (هسته اولویت دارد)
            foreach ($plugin->psr4 as $prefix => $relativeDir) {
                $this->registerFallbackLoader($prefix, $plugin->path . '/' . $relativeDir);
            }
        }

        // ۲) vendor ایزوله (معافیت DOC-079 §1) — composer پیش‌فرض append ثبت می‌کند
        if ($plugin->hasVendor() && (bool) ($this->config['allow_vendor'] ?? true)) {
            $this->registerIsolatedVendor($plugin);
        }

        // ۳) capability enforcement (زمان اجرا — مشورتی برای اجزای فریمورک)
        PluginCapabilities::register($plugin->namespace(), $plugin->id, $plugin->capabilities);

        // ۴) ثبت و بوت پراوایدر
        if ($plugin->provider !== null && \class_exists($plugin->provider)) {
            $providerClass = $plugin->provider;
            if (isset($this->booted[$providerClass])) {
                return;
            }
            $this->booted[$providerClass] = true;

            if (\is_subclass_of($providerClass, PluginProvider::class)) {
                PluginProvider::bindPluginId($providerClass, $plugin->id);
            }

            /** @var ServiceProvider $provider */
            $provider = $this->app->make($providerClass);
            if ($this->app instanceof Application) {
                $this->app->registerProvider($provider);
            } else {
                $provider->register();
            }
            $provider->boot();   // پلاگین پس از فاز boot میزبان بارگذاری می‌شود
        }
    }

    /**
     * خروج منطقی: حذف listenerها و capability (کلاس‌های loadشده در PHP
     * قابل unload نیستند — مستند در DOC-079 §7: disable = عدم بارگذاری
     * در بوت بعدی + قطع hookهای جاری).
     *
     * با پاک‌کردن پرچم boot، `enable()` مجدد در همان فرایند provider را
     * دوباره boot می‌کند تا hookها برگردند (provider پلاگین باید idempotent
     * باشد — مستند در DOC-079 §6).
     */
    public function unload(Plugin $plugin): void
    {
        Hook::flushPlugin($plugin->id);
        if ($plugin->provider !== null) {
            unset($this->booted[$plugin->provider]);
        }
        PluginCapabilities::register($plugin->namespace(), $plugin->id, [
            'network' => [],
            'filesystem' => [],
            'database' => false,
            'shell' => false,
        ]);
    }

    public function isBooted(string $providerClass): bool
    {
        return isset($this->booted[$providerClass]);
    }

    private function registerIsolatedVendor(Plugin $plugin): void
    {
        $before = \spl_autoload_functions();
        require_once $plugin->path . '/vendor/autoload.php';
        $after = \spl_autoload_functions();

        // 🔒 ایزولاسیون ترتیبی: loaderهای جدیدِ vendor به انتهای زنجیره منتقل
        // می‌شوند تا کلاس‌های هسته/موتورها هرگز توسط vendor افزونه shadow نشوند.
        $added = \array_values(\array_filter(
            $after,
            static fn ($loader): bool => !\in_array($loader, $before === false ? [] : $before, true),
        ));
        if ($added !== []) {
            foreach ($added as $loader) {
                \spl_autoload_unregister($loader);
                \spl_autoload_register($loader, throw: true, prepend: false);
            }
        }
    }

    /**
     * loader سبک PSR-4 برای افزونه‌های خارج از basePath (ثبت append —
     * کلاس‌های هسته/موتورها همیشه اولویت دارند).
     */
    private function registerFallbackLoader(string $prefix, string $dir): void
    {
        $key = $prefix . '|' . $dir;
        if (isset($this->fallbackLoaders[$key])) {
            return;
        }
        $this->fallbackLoaders[$key] = true;

        $normalizedPrefix = \rtrim($prefix, '\\') . '\\';
        $normalizedDir = \rtrim(\str_replace('\\', '/', $dir), '/');

        \spl_autoload_register(static function (string $class) use ($normalizedPrefix, $normalizedDir): void {
            if (!\str_starts_with($class, $normalizedPrefix)) {
                return;
            }
            $relative = \str_replace('\\', '/', \substr($class, \strlen($normalizedPrefix)));
            if (\str_contains($relative, '..')) {
                return;   // 🔒 path traversal
            }
            $file = $normalizedDir . '/' . $relative . '.php';
            if (\is_file($file)) {
                require $file;
            }
        }, throw: true, prepend: false);
    }
}
