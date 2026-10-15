<?php

declare(strict_types=1);

namespace Tavaan\Engine\Session;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Crypt\Crypt;
use Tavaan\Engine\Http\Cookie\CookieJar;
use Tavaan\Engine\Session\Middleware\CsrfVerifyMiddleware;
use Tavaan\Engine\Session\Middleware\ShareErrorsFromSessionMiddleware;
use Tavaan\Engine\Session\Middleware\StartSessionMiddleware;

/**
 * تَوان | Tavaan — Session
 * SessionServiceProvider — ثبت SessionManager/middlewareها/CookieJar.
 *
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SessionManager::class, static function (ContainerInterface $c): SessionManager {
            return new SessionManager(
                $c->bound('config') ? $c->make('config') : null,
                $c->bound(Crypt::class) ? $c->make(Crypt::class) : null,
            );
        });

        // CookieJar (موتور cookie)
        $this->app->singleton(CookieJar::class, static function (ContainerInterface $c): CookieJar {
            $config = $c->bound('config') ? $c->make('config') : null;

            return new CookieJar(
                $c->bound(Crypt::class) ? $c->make(Crypt::class) : null,
                (bool) ($config?->get('session.secure', false) ?? false),
                ($config?->get('session.domain')) ?: null,
                (string) ($config?->get('session.same_site', 'Lax') ?? 'Lax'),
            );
        });
        $this->app->alias(CookieJar::class, 'cookie');

        $this->app->singleton(StartSessionMiddleware::class, static function (ContainerInterface $c): StartSessionMiddleware {
            return new StartSessionMiddleware(
                $c->make(SessionManager::class),
                $c,
                $c->bound('config') ? $c->make('config') : null,
            );
        });

        $this->app->singleton(CsrfVerifyMiddleware::class, static function (ContainerInterface $c): CsrfVerifyMiddleware {
            $except = $c->bound('config') ? (array) $c->make('config')->get('session.csrf.except', []) : [];

            return new CsrfVerifyMiddleware($except);
        });

        $this->app->singleton(ShareErrorsFromSessionMiddleware::class, static fn (ContainerInterface $c): ShareErrorsFromSessionMiddleware => new ShareErrorsFromSessionMiddleware($c));
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [SessionManager::class, CookieJar::class, 'cookie', StartSessionMiddleware::class];
    }
}
