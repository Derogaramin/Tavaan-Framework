<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Module\Module;
use Tavaan\Engine\Module\ModuleRegistry;

/**
 * تَوان | Tavaan — Core
 * DiscoverModules — B7: کشف manifestها، مرتب‌سازی توپولوژیک و ثبت Provider ماژول‌ها.
 *
 * ثبت Providerها پیش از BootProviders (B9) انجام می‌شود تا boot() ماژول‌ها
 * به‌ترتیب وابستگی و پس از موتورها اجرا شود (DOC-022 §4 گام ۴).
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
        $modules = $registry->sorted();

        $this->registerModuleProviders($app, $modules);
    }

    /**
     * ثبت Provider ماژول‌ها/کیت‌ها به ترتیب توپولوژیک.
     *
     * - موتور (engine): providerها از config/providers.php ثبت می‌شوند (idempotent).
     * - پلاگین (plugin): چرخهٔ عمر sandbox جداگانه دارد (DOC-079).
     *
     * @param list<Module> $modules
     */
    private function registerModuleProviders(Application $app, array $modules): void
    {
        foreach ($modules as $module) {
            $type = $module->type();
            if ($type === 'engine' || $type === 'plugin') {
                continue;
            }

            foreach ($module->manifest()->providers as $providerClass) {
                if (!\is_string($providerClass) || $providerClass === '' || !\class_exists($providerClass)) {
                    continue;
                }

                $provider = $app->make($providerClass);
                if ($provider instanceof ServiceProvider) {
                    $app->registerProvider($provider);
                }
            }
        }
    }
}
