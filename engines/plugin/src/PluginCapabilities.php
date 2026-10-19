<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

use Tavaan\Engine\Plugin\Exceptions\PluginException;

/**
 * تَوان | Tavaan — Plugin
 * PluginCapabilities — registry زمان‌اجرای capabilityها به تفکیک فضای‌نام.
 *
 * Sandbox در load هر افزونه capabilities را اینجا ثبت می‌کند؛ اجزای فریمورک
 * (HttpClient/Filesystem/…) و کد میزبان می‌توانند پیش از عملیات حساس، کلاس
 * فراخوان را با `forClass()` به افزونه نگاشت و مجوز را بررسی کنند.
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginCapabilities
{
    /** @var array<string, array{network: list<string>, filesystem: list<string>, database: bool, shell: bool, plugin: string}> */
    private static array $registry = [];

    private function __construct()
    {
    }

    /**
     * @param array{network: list<string>, filesystem: list<string>, database: bool, shell: bool} $capabilities
     */
    public static function register(string $namespacePrefix, string $pluginId, array $capabilities): void
    {
        self::$registry[\trim($namespacePrefix, '\\') . '\\'] = [...$capabilities, 'plugin' => $pluginId];
    }

    /**
     * یافتن capability یک کلاس بر اساس طولانی‌ترین prefix فضای‌نام.
     *
     * @param class-string|string $class
     *
     * @return array{network: list<string>, filesystem: list<string>, database: bool, shell: bool, plugin: string}|null
     */
    public static function forClass(string $class): ?array
    {
        $best = null;
        $bestLength = -1;
        foreach (self::$registry as $prefix => $caps) {
            if (\str_starts_with($class, $prefix) && \strlen($prefix) > $bestLength) {
                $best = $caps;
                $bestLength = \strlen($prefix);
            }
        }

        return $best;
    }

    /**
     * بررسی دسترسی شبکه برای یک کلاس افزونه (allowlist دامنه).
     *
     * @throws PluginException اگر دامنه در allowlist افزونه نباشد
     */
    public static function assertNetwork(string $class, string $url): void
    {
        $caps = self::forClass($class);
        if ($caps === null) {
            return;   // کد میزبان — بدون محدودیت افزونه
        }

        $host = (string) (\parse_url($url, PHP_URL_HOST) ?: '');
        if ($host === '' || !\in_array($host, $caps['network'], true)) {
            throw PluginException::capabilityDenied(
                $caps['plugin'],
                'network',
                \sprintf('دامنهٔ [%s] در allowlist افزونه نیست', $host),
            );
        }
    }

    /**
     * بررسی دسترسی DB برای یک کلاس افزونه.
     *
     * @throws PluginException اگر capability دیتابیس نداشته باشد
     */
    public static function assertDatabase(string $class): void
    {
        $caps = self::forClass($class);
        if ($caps !== null && !$caps['database']) {
            throw PluginException::capabilityDenied($caps['plugin'], 'database', 'دسترسی مستقیم DB بدون capability');
        }
    }

    public static function flush(): void
    {
        self::$registry = [];
    }
}
