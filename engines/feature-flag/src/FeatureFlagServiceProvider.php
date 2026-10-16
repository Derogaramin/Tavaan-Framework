<?php

declare(strict_types=1);

namespace Tavaan\Engine\FeatureFlag;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — FeatureFlag
 * FeatureFlagServiceProvider — ثبت FeatureManager به‌عنوان `feature` (Facade: Feature).
 *
 * @api 1.0
 * @see docs/03-engines/feature-flag.md
 */
final class FeatureFlagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        FeatureFlagResolver::set($this->app);

        $this->app->singleton(FeatureManager::class, static function (ContainerInterface $c): FeatureManager {
            $config = $c->bound('config') ? (array) $c->make('config')->get('features', []) : [];

            $cache = null;
            if ($c->bound(CacheRepository::class)) {
                $candidate = $c->make(CacheRepository::class);
                $cache = $candidate instanceof CacheRepository ? $candidate : null;
            } elseif ($c->bound('cache')) {
                $manager = $c->make('cache');
                if ($manager instanceof CacheRepository) {
                    $cache = $manager;
                } elseif (\method_exists($manager, 'repository')) {
                    $cache = $manager->repository();
                }
            }

            $env = $c->bound('config') ? (string) $c->make('config')->get('app.env', 'production') : 'production';

            $ip = null;
            try {
                if ($c->bound('request')) {
                    $request = $c->make('request');
                    if (\is_object($request) && \method_exists($request, 'ip')) {
                        $ip = $request->ip();
                    }
                }
            } catch (\Throwable) {
                $ip = null;
            }

            return new FeatureManager(
                $cache instanceof CacheRepository ? $cache : null,
                $config,
                $env,
                \is_string($ip) ? $ip : null,
            );
        });
        $this->app->alias(FeatureManager::class, 'feature');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [FeatureManager::class, 'feature'];
    }
}
