<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Plugin
 * PluginProvider — پایهٔ ServiceProvider افزونه‌ها (DOC-079 §6).
 *
 * Sandbox پیش از ثبت، `pluginId` را تزریق می‌کند تا `listenHook()` بتواند
 * محدودیت «فقط hookهای اعلام‌شده» را اعمال کند.
 *
 * ```php
 * final class ServiceProvider extends PluginProvider
 * {
 *     public function boot(): void
 *     {
 *         $this->listenHook('shop.order.placed', [$this, 'onOrderPlaced']);
 *     }
 * }
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
abstract class PluginProvider extends ServiceProvider
{
    /** @var array<class-string, string> provider → pluginId (توسط Sandbox پر می‌شود) */
    private static array $pluginIds = [];

    /** تزریق زمینهٔ افزونه (توسط PluginSandbox — پیش از register). */
    public static function bindPluginId(string $providerClass, string $pluginId): void
    {
        self::$pluginIds[$providerClass] = $pluginId;
    }

    public static function clearPluginIds(): void
    {
        self::$pluginIds = [];
    }

    protected function pluginId(): ?string
    {
        return self::$pluginIds[static::class] ?? null;
    }

    /**
     * اتصال به hook میزبان — فقط hookهای اعلام‌شده مجازند.
     *
     * @throws Exceptions\PluginException اگر hook اعلام نشده باشد
     */
    protected function listenHook(string $hook, callable $listener): void
    {
        Hook::listen($hook, $listener, $this->pluginId());
    }
}
