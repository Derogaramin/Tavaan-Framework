<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Api\Middleware\ApiVersionMiddleware;
use Tavaan\Engine\Api\Middleware\IdempotencyMiddleware;
use Tavaan\Engine\Api\Middleware\ThrottleMiddleware;
use Tavaan\Engine\Api\OpenApi\OpenApiGenerator;

/**
 * تَوان | Tavaan — Api
 * ApiServiceProvider — ثبت OpenApiGenerator + aliasهای middleware (DOC-044).
 *
 * aliasها: `throttle`، `idempotent`، `api.version`.
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // resolver ایستا برای JsonResource (درخواست جاری)
        ApiResolver::set($this->app);

        $this->app->singleton(OpenApiGenerator::class, static function (ContainerInterface $c): OpenApiGenerator {
            $config = $c->bound('config') ? (array) $c->make('config')->get('api', []) : [];

            return new OpenApiGenerator($c->make('router'), $config);
        });
        $this->app->alias(OpenApiGenerator::class, 'openapi');

        // middlewareها
        $this->app->singleton(ApiVersionMiddleware::class, static function (ContainerInterface $c): ApiVersionMiddleware {
            $config = $c->bound(ConfigRepository::class) ? $c->make(ConfigRepository::class) : ($c->bound('config') ? $c->make('config') : null);

            return new ApiVersionMiddleware($config instanceof ConfigRepository ? $config : null);
        });

        $this->app->singleton(IdempotencyMiddleware::class, static function (ContainerInterface $c): IdempotencyMiddleware {
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
            $ttl = $c->bound('config') ? (int) $c->make('config')->get('api.idempotency.ttl', 86400) : 86400;

            return new IdempotencyMiddleware($cache instanceof CacheRepository ? $cache : null, $ttl);
        });

        $this->app->singleton(ThrottleMiddleware::class, static fn (ContainerInterface $c): ThrottleMiddleware => new ThrottleMiddleware($c));

        $this->registerMiddlewareAliases();
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [OpenApiGenerator::class, 'openapi'];
    }

    /**
     * ثبت aliasهای middleware در config http — HttpKernel هنگام ساخت
     * (تنبل) آن‌ها را می‌خواند.
     */
    private function registerMiddlewareAliases(): void
    {
        if (!$this->app->bound('config')) {
            return;
        }

        /** @var ConfigRepository $config */
        $config = $this->app->make('config');
        $aliases = (array) $config->get('http.middleware.aliases', []);

        $aliases += [
            'throttle' => ThrottleMiddleware::class,
            'idempotent' => IdempotencyMiddleware::class,
            'api.version' => ApiVersionMiddleware::class,
        ];

        $config->set('http.middleware.aliases', $aliases);
    }
}
