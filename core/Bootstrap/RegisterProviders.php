<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Contracts\Support\DeferrableProvider;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — Core
 * RegisterProviders — B8: ثبت Providerها از config (با Deferrable lazy).
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class RegisterProviders implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        $providers = $app->bound('config')
            ? (array) $app->make('config')->get('providers', [])
            : [];

        foreach ($providers as $providerClass) {
            if (!\is_string($providerClass) || !\class_exists($providerClass)) {
                continue;
            }

            /** @var ServiceProvider $provider */
            $provider = $app->make($providerClass);

            if ($provider instanceof DeferrableProvider) {
                $this->registerDeferrable($app, $provider);
                continue;
            }

            $app->registerProvider($provider);
        }
    }

    /** binding تنبل: اولین make از provides() → register() اجرا شود. */
    private function registerDeferrable(Application $app, ServiceProvider&DeferrableProvider $provider): void
    {
        foreach ($provider->provides() as $abstract) {
            $app->bind((string) $abstract, static function (Application $c) use ($provider, $abstract): mixed {
                $c->registerProvider($provider);

                return $c->make((string) $abstract);
            });
        }
    }
}
