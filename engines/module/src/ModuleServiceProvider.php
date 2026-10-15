<?php

declare(strict_types=1);

namespace Tavaan\Engine\Module;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Module
 * ModuleServiceProvider — ثبت ModuleRegistry/Manager.
 *
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, static function (ContainerInterface $c): ModuleRegistry {
            $config = $c->bound('config') ? $c->make('config') : null;
            $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : \dirname(__DIR__, 3);

            $paths = $config !== null
                ? (array) $config->get('modules.paths', [$base . '/engines', $base . '/modules'])
                : [$base . '/engines', $base . '/modules'];

            $disabled = $config !== null ? (array) $config->get('modules.disabled', []) : [];

            $statusFile = $base . '/storage/framework/modules.status.php';
            $statusMap = \is_file($statusFile) ? (array) require $statusFile : [];

            return new ModuleRegistry($paths, $statusMap, $disabled);
        });
        $this->app->alias(ModuleRegistry::class, 'modules');

        $this->app->singleton(ModuleManager::class, static function (ContainerInterface $c): ModuleManager {
            $base = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : \dirname(__DIR__, 3);

            return new ModuleManager(
                $c->make(ModuleRegistry::class),
                $base . '/storage/framework/modules.status.php',
                $c->bound('events') ? $c->make('events') : null,
            );
        });
        $this->app->alias(ModuleManager::class, 'module.manager');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [ModuleRegistry::class, ModuleManager::class, 'modules', 'module.manager'];
    }
}
