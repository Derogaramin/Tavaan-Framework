<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;

/**
 * تَوان | Tavaan — HttpClient
 * HttpClientServiceProvider — ثبت Factory به‌عنوان `http.client` (Facade: Http).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
final class HttpClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Factory::class, static function (ContainerInterface $c): Factory {
            return new Factory(
                $c->bound('config') ? $c->make('config') : null,
                $c,
                null,   // transport: lazy (curl → stream)
                null,   // ssrf: lazy (security → internal)
                $c->bound(CacheRepository::class) ? $c->make(CacheRepository::class) : ($c->bound('cache') ? $c->make('cache') : null),
            );
        });
        $this->app->alias(Factory::class, 'http.client');
        $this->app->alias(Factory::class, 'http-client');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Factory::class, 'http.client', 'http-client'];
    }
}
