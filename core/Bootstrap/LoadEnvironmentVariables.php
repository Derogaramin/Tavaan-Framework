<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Exceptions\Core\ConfigException;
use Tavaan\Engine\Config\EnvRepository;

/**
 * تَوان | Tavaan — Core
 * LoadEnvironmentVariables — B2: خواندن `.env` (بدون putenv) + بررسی E6.
 *
 * ⚠️ Composition root: تنها لایهٔ core که پیاده‌سازی موتور را می‌شناسد.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class LoadEnvironmentVariables implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        $env = EnvRepository::instance();
        $env->ensureLoaded();   // idempotent

        $app->instance(EnvRepository::class, $env);

        // 🔒 E6: production + debug=true → خطای بوت
        $environment = (string) $env->string('TVN_APP_ENV', 'production');
        $app->setEnvironment($environment);
        if ($environment === 'production' && $env->bool('TVN_APP_DEBUG', false)) {
            throw new ConfigException(
                messageId: 'core.env.debug_in_production',
                hint: 'TVN_APP_DEBUG=true در production ممنوع است (نشت اطلاعات).',
                docsUrl: 'docs/02-architecture/bootstrapping.md#41-قواعد-env',
            );
        }
    }
}
