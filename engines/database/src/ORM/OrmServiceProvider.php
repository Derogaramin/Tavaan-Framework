<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Database\ConnectionManager;

/**
 * تَوان | Tavaan — ORM
 * OrmServiceProvider — اتصال resolverهای Model به کانتینر (DOC-050).
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class OrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Model::setContainer($this->app);

        $this->app->singleton('orm', static fn (): string => 'tavaan.orm');
    }

    public function boot(): void
    {
        Model::setConnectionResolver($this->app->make(ConnectionManager::class));

        if ($this->app->bound(DispatcherInterface::class)) {
            Model::setEventDispatcher($this->app->make(DispatcherInterface::class));
        }

        if ($this->app->bound('config')) {
            $config = $this->app->make('config');
            $morphMap = (array) ($config->get('orm.morph_map', []) ?? []);
            if ($morphMap !== []) {
                /** @var array<string, class-string> $morphMap */
                MorphMap::register($morphMap);
            }
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return ['orm'];
    }
}
