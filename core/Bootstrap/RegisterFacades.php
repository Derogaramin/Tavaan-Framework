<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Facade\Facade;

/**
 * تَوان | Tavaan — Core
 * RegisterFacades — B6: اتصال Facadeها به کانتینر.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class RegisterFacades implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }
}
