<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Module\ModuleRegistry;

/**
 * تَوان | Tavaan — Core
 * DiscoverModules — B7: کشف manifestها و مرتب‌سازی توپولوژیک.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class DiscoverModules implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        if (!$app->bound(ModuleRegistry::class)) {
            return;
        }

        /** @var ModuleRegistry $registry */
        $registry = $app->make(ModuleRegistry::class);
        $registry->discover();

        // مرتب‌سازی توپولوژیک — در صورت چرخه، بوت شکست می‌خورد (Fail Fast)
        $registry->sorted();
    }
}
