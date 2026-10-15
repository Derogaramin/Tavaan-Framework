<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;

/**
 * تَوان | Tavaan — Core
 * BootProviders — B9: اجرای boot() همهٔ Providerهای ثبت‌شده.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class BootProviders implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        foreach ($app->providers() as $provider) {
            $provider->boot();
        }
    }
}
