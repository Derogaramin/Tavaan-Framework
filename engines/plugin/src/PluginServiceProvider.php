<?php

declare(strict_types=1);

namespace Tavaan\Engine\Plugin;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Module\ModuleRegistry;

/**
 * تَوان | Tavaan — Plugin
 * PluginServiceProvider — ثبت PluginManager + اعلام hookهای میزبان +
 * بارگذاری افزونه‌های فعال در boot (DOC-079 §6/§8).
 *
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginManager::class, static function (ContainerInterface $c): PluginManager {
            $config = $c->bound('config') ? (array) ($c->make('config')->get('plugins', []) ?? []) : [];
            $events = $c->bound(DispatcherInterface::class) ? $c->make(DispatcherInterface::class) : null;

            return new PluginManager(
                $c,
                $config,
                $events instanceof DispatcherInterface ? $events : null,
            );
        });

        $this->app->alias(PluginManager::class, 'plugins');
    }

    public function boot(): void
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);
        $config = $this->app->bound('config') ? (array) ($this->app->make('config')->get('plugins', []) ?? []) : [];

        // ۱) اعلام hookهای میزبان: module.json ماژول‌ها + config allowed_hooks
        $this->declareHostHooks($config);

        // ۲) محدودیت‌های DoS برای hookها (§9)
        Hook::setLimits(
            (int) ($config['hook_timeout_ms'] ?? 5000),
            isset($config['hook_memory_limit']) ? (int) $config['hook_memory_limit'] : null,
        );

        // ۳) بارگذاری افزونه‌های فعال
        $manager->loadEnabled();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [PluginManager::class, 'plugins'];
    }

    /** @param array<string, mixed> $config */
    private function declareHostHooks(array $config): void
    {
        foreach ((array) ($config['allowed_hooks'] ?? []) as $hook) {
            if (\is_string($hook) && $hook !== '') {
                Hook::declare($hook);
            }
        }

        if (!$this->app->bound(ModuleRegistry::class)) {
            return;
        }

        /** @var ModuleRegistry $registry */
        $registry = $this->app->make(ModuleRegistry::class);
        foreach ($registry->all() as $module) {
            $hooks = $module->manifest()->raw['hooks'] ?? [];
            if (\is_array($hooks)) {
                foreach ($hooks as $hook) {
                    if (\is_string($hook) && $hook !== '') {
                        Hook::declare($hook);
                    }
                }
            }
        }
    }
}
