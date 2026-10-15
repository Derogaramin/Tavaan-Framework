<?php

declare(strict_types=1);

namespace Tavaan\Core\Bootstrap;

use Tavaan\Core\Application\Application;
use Tavaan\Engine\Config\ConfigLoader;
use Tavaan\Engine\Config\Repository;
use Tavaan\Engine\Config\SchemaValidator;

/**
 * تَوان | Tavaan — Core
 * LoadConfiguration — B3: بارگذاری config/*.php + Cache + Schema + Freeze.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class LoadConfiguration implements BootstrapperInterface
{
    public function bootstrap(Application $app): void
    {
        if ($app->bound('config') && $app->resolved('config')) {
            return;   // idempotent
        }

        $basePath = $app->basePath();
        $environment = $app->environment();

        $cacheFile = $basePath . '/storage/cache/config.php';
        $cacheEnabled = \is_file($cacheFile)
            && ($environment === 'production' || ($_SERVER['TVN_CONFIG_CACHE'] ?? '') === 'true');

        if ($cacheEnabled) {
            /** @var array<string, mixed> $items */
            $items = require $cacheFile;
        } else {
            $items = (new ConfigLoader($basePath))->load($environment);
        }

        $secretKeys = (array) ($items['config']['secret_keys'] ?? ['*.password', '*.secret', '*.key', '*.token']);
        $repository = new Repository($items, $secretKeys);

        $app->instance('config', $repository);
        $app->alias('config', \Tavaan\Core\Contracts\Config\RepositoryInterface::class);

        // Schema Validation (اختیاری — DEV: کامل؛ PROD: required/in)
        if ((bool) ($items['config']['schema']['enabled'] ?? false) && !$cacheEnabled) {
            $this->validateSchemas($repository, (string) ($items['config']['schema']['path'] ?? $basePath . '/config/schema'), $environment !== 'production');
        }

        // 🔒 Freeze در production
        $environmentIsProduction = $environment === 'production';
        if ($environmentIsProduction && (bool) ($items['config']['freeze_in_production'] ?? true)) {
            $repository->freeze();
        }
    }

    private function validateSchemas(Repository $repository, string $schemaPath, bool $strict): void
    {
        if (!\is_dir($schemaPath)) {
            return;
        }

        $validator = new SchemaValidator();
        foreach (\glob($schemaPath . '/*.schema.php') ?: [] as $file) {
            $namespace = \basename($file, '.schema.php');
            /** @var array<string, mixed> $schema */
            $schema = require $file;
            $validator->validate($namespace, $repository->namespace($namespace), $schema, $strict);
        }
    }
}
