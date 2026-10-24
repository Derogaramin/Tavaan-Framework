<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Core\Context\RequestStack;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Http\KernelInterface as HttpKernelInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Error\ExceptionHandler;
use Tavaan\Engine\Http\Middleware\MaintenanceModeMiddleware;
use Tavaan\Engine\Http\Middleware\RequestSizeLimitMiddleware;
use Tavaan\Engine\Http\Middleware\SecurityHeadersMiddleware;
use Tavaan\Engine\Http\Middleware\SetLocaleMiddleware;
use Tavaan\Engine\Http\Middleware\TrustProxiesMiddleware;
use Tavaan\Engine\Http\Routing\ControllerResolver;
use Tavaan\Engine\Http\Routing\Route;
use Tavaan\Engine\Http\Routing\Router;
use Tavaan\Engine\Http\Routing\UrlGenerator;

/**
 * تَوان | Tavaan — Http
 * HttpServiceProvider — ثبت Request/Response/Router/Kernel/Url و middlewareها.
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;

        $app->singleton(RequestStack::class);

        $app->singleton('router', static function (ContainerInterface $c): Router {
            $config = $c->bound('config') ? (array) $c->make('config')->get('router', []) : [];
            $events = $c->bound('events') ? $c->make('events') : null;

            return new Router($c, $config, $events);
        });
        $app->alias('router', Router::class);

        $app->singleton(UrlGenerator::class, static function (ContainerInterface $c): UrlGenerator {
            $appUrl = $c->bound('config') ? (string) $c->make('config')->get('app.url', '') : '';
            $crypt = $c->bound('encrypter') ? $c->make(\Tavaan\Engine\Crypt\Crypt::class) : null;

            return new UrlGenerator($c->make('router'), $crypt, $appUrl);
        });
        $app->alias(UrlGenerator::class, 'url');

        $app->singleton(ResponseFactory::class, static fn (ContainerInterface $c): ResponseFactory => new ResponseFactory($c));
        $app->alias(ResponseFactory::class, 'response.factory');

        $app->singleton(Redirector::class, static function (ContainerInterface $c): Redirector {
            $allowed = $c->bound('config') ? (array) $c->make('config')->get('http.allowed_redirect_hosts', []) : [];

            return new Redirector($c, $c->make(UrlGenerator::class), $allowed);
        });
        $app->alias(Redirector::class, 'redirect');

        $app->singleton(ResponsePreparer::class, static function (ContainerInterface $c): ResponsePreparer {
            $config = $c->bound('config') ? (array) $c->make('config')->get('http', []) : [];

            return new ResponsePreparer($config);
        });

        $app->singleton(ControllerResolver::class, static fn (ContainerInterface $c): ControllerResolver => new ControllerResolver($c));

        $app->singleton(HttpKernel::class, static function (ContainerInterface $c): HttpKernel {
            $config = $c->bound('config') ? (array) $c->make('config')->get('http', []) : [];

            return new HttpKernel(
                container: $c,
                router: $c->make('router'),
                requestStack: $c->make(RequestStack::class),
                exceptions: $c->make(ExceptionHandler::class),
                preparer: $c->make(ResponsePreparer::class),
                controller: $c->make(ControllerResolver::class),
                config: $config,
                events: $c->bound('events') ? $c->make('events') : null,
            );
        });
        $app->alias(HttpKernel::class, HttpKernelInterface::class);
        $app->alias(HttpKernel::class, 'http.kernel');

        // middlewareهای سازنده‌دار (از config)
        $app->singleton(TrustProxiesMiddleware::class, static function (ContainerInterface $c): TrustProxiesMiddleware {
            $config = $c->bound('config') ? $c->make('config') : null;

            return new TrustProxiesMiddleware(
                (array) ($config?->get('http.trusted_proxies', []) ?? []),
                (array) ($config?->get('http.trusted_headers', []) ?? []),
            );
        });
        $app->singleton(MaintenanceModeMiddleware::class, static function (ContainerInterface $c): MaintenanceModeMiddleware {
            $config = $c->bound('config') ? $c->make('config') : null;

            return new MaintenanceModeMiddleware(
                \defined('TVN_BASE_PATH') ? TVN_BASE_PATH . '/storage/framework/down' : '',
                (array) ($config?->get('http.maintenance.except', []) ?? []),
            );
        });
        $app->singleton(SecurityHeadersMiddleware::class, static function (ContainerInterface $c): SecurityHeadersMiddleware {
            $config = $c->bound('config') ? $c->make('config') : null;

            return new SecurityHeadersMiddleware(
                (array) ($config?->get('http.security_headers', []) ?? []),
                (bool) ($config?->get('http.security_headers_override', false) ?? false),
            );
        });
        $app->singleton(RequestSizeLimitMiddleware::class, static function (ContainerInterface $c): RequestSizeLimitMiddleware {
            $max = $c->bound('config') ? (int) $c->make('config')->get('http.max_body_size', 8_388_608) : 8_388_608;

            return new RequestSizeLimitMiddleware($max);
        });
        $app->singleton(SetLocaleMiddleware::class, static function (ContainerInterface $c): SetLocaleMiddleware {
            $config = $c->bound('config') ? (array) $c->make('config')->get('i18n', []) : [];

            return new SetLocaleMiddleware(
                $c->bound('translator') ? $c->make('translator') : null,
                $config,
            );
        });

        $app->singleton(\Tavaan\Engine\Http\Cors\CorsMiddleware::class, static function (ContainerInterface $c): \Tavaan\Engine\Http\Cors\CorsMiddleware {
            $config = $c->bound('config') ? (array) $c->make('config')->get('cors', []) : [];

            return new \Tavaan\Engine\Http\Cors\CorsMiddleware($config);
        });

        $app->singleton(\Tavaan\Engine\Http\Cookie\CookieJar::class, static function (ContainerInterface $c): \Tavaan\Engine\Http\Cookie\CookieJar {
            $config = $c->bound('config') ? (array) $c->make('config')->get('cookie', []) : [];

            return new \Tavaan\Engine\Http\Cookie\CookieJar(
                $c->bound(\Tavaan\Engine\Crypt\Crypt::class) ? $c->make(\Tavaan\Engine\Crypt\Crypt::class) : null,
                (bool) ($config['secure'] ?? false),
                isset($config['domain']) ? (string) $config['domain'] : null,
                (string) ($config['same_site'] ?? 'Lax'),
            );
        });
        $app->alias(\Tavaan\Engine\Http\Cookie\CookieJar::class, 'cookie');

        $app->singleton(\Tavaan\Engine\Http\Cookie\EncryptCookiesMiddleware::class, static function (ContainerInterface $c): \Tavaan\Engine\Http\Cookie\EncryptCookiesMiddleware {
            $except = $c->bound('config') ? (array) $c->make('config')->get('cookie.except', ['XSRF-TOKEN']) : ['XSRF-TOKEN'];

            return new \Tavaan\Engine\Http\Cookie\EncryptCookiesMiddleware(
                $c->make(\Tavaan\Engine\Http\Cookie\CookieJar::class),
                $c->bound(\Tavaan\Engine\Crypt\Crypt::class) ? $c->make(\Tavaan\Engine\Crypt\Crypt::class) : null,
                $except,
            );
        });

        // method override از config
        Request::enableMethodOverride(
            $app->bound('config') ? (bool) $app->make('config')->get('http.method_override', true) : true,
        );
    }

    public function boot(): void
    {
        // UrlGenerator context
        $this->app->singleton('request', static fn (): ?Request => null);

        // بارگذاری فایل‌های روت (config/router.files)
        if (!$this->app->bound('config')) {
            return;
        }

        $files = (array) $this->app->make('config')->get('router.files', []);
        $router = $this->app->make('router');

        foreach ($files as $file => $attributes) {
            if (!\is_string($file) || !\is_file($file)) {
                continue;
            }
            $attributes = \is_array($attributes) ? $attributes : [];
            if (($attributes['type'] ?? 'web') !== 'web' && ($attributes['type'] ?? '') !== 'api') {
                continue;   // console/channels در موتور خودشان
            }

            $router->group($attributes, static function (Router $r) use ($file): void {
                $routerRef = $r;
                (static function () use ($file, $routerRef): void {
                    // فایل روت با Facade\Route کار می‌کند؛ Router در scope تزریق می‌شود
                    require $file;
                })();
            });
        }

        // Route cache
        $cacheFile = (string) $this->app->make('config')->get('router.cache.file', '');
        $cacheEnabled = (bool) $this->app->make('config')->get('router.cache.enabled', false);
        if ($cacheEnabled && $cacheFile !== '' && \is_file($cacheFile)) {
            /** @var array{routes: list<array<string, mixed>>} $cached */
            $cached = require $cacheFile;
            $router->loadCached((array) ($cached['routes'] ?? []));
        }
    }

    /** @return list<string> */
    public function provides(): array
    {
        return ['router', 'url', 'redirect', 'http.kernel', 'cookie', HttpKernel::class, Router::class, UrlGenerator::class, RequestStack::class, \Tavaan\Engine\Http\Cookie\CookieJar::class];
    }
}
