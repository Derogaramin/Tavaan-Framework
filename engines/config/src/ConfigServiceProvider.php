<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config;

use Tavaan\Core\Contracts\Config\ConfigCacheInterface;
use Tavaan\Core\Contracts\Config\EnvRepositoryInterface;
use Tavaan\Core\Contracts\Config\RepositoryInterface;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Config
 * ConfigServiceProvider — ثبت Repository/Env/Cacher در کانتینر.
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnvRepository::class, static fn (): EnvRepository => EnvRepository::instance());
        $this->app->singleton(EnvRepositoryInterface::class, static fn (ContainerInterface $c): EnvRepositoryInterface => $c->make(EnvRepository::class));
        $this->app->alias(EnvRepositoryInterface::class, 'env');

        $this->app->singleton(SchemaValidator::class);
        $this->app->singleton(ConfigCacher::class, static fn (ContainerInterface $c): ConfigCacher => new ConfigCacher($c->make('config')));
        $this->app->singleton(ConfigCacheInterface::class, static fn (ContainerInterface $c): ConfigCacheInterface => $c->make(ConfigCacher::class));
    }

    public function boot(): void
    {
        // Config Repository توسط Bootstrapper LoadConfiguration ساخته و instance می‌شود.
        if (!$this->app->bound('config')) {
            $basePath = \defined('TVN_BASE_PATH') ? (string) \TVN_BASE_PATH : \dirname(__DIR__, 3);
            $loader = new ConfigLoader($basePath);
            $repository = new Repository($loader->load());
            $this->app->instance('config', $repository);
        }
        $this->app->alias('config', RepositoryInterface::class);
    }

    /** @return list<string> */
    public function provides(): array
    {
        return ['config', 'env', RepositoryInterface::class, EnvRepositoryInterface::class];
    }
}
