<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Container\PsrContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Container
 * ContainerServiceProvider — ثبت خودِ کانتینر به‌عنوان سرویس + aliasها.
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final class ContainerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $container = $this->app;

        $container->instance(ContainerInterface::class, $container instanceof ContainerInterface ? $container : new Container());
        $container->instance(PsrContainerInterface::class, $container);
        $container->instance(Container::class, $container);
        $container->alias(ContainerInterface::class, 'app');
        $container->alias(ContainerInterface::class, 'container');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [ContainerInterface::class, PsrContainerInterface::class, 'app', 'container'];
    }
}
