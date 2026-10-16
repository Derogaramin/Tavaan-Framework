<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Routing
 * Router — API عمومی تعریف روت + group + resource + matching.
 *
 * ### مثال
 * ```php
 * Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show')->whereNumber('user');
 * Route::prefix('api/v1')->middleware('api')->group(function (): void {
 *     Route::apiResource('products', ProductController::class);
 * });
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class Router
{
    private RouteCollection $routes;

    private RouteMatcher $matcher;

    private bool $matcherStale = true;

    /** @var list<array<string, mixed>> پشتهٔ group */
    private array $groupStack = [];

    /** @var array<string, class-string> model → class (explicit binding) */
    private array $modelBindings = [];

    /** @var array<string, \Closure(string): mixed> */
    private array $binders = [];

    private ?RouteMatch $current = null;

    private ?Route $fallbackRoute = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $config = [],
        private readonly ?DispatcherInterface $events = null,
    ) {
        $this->routes = new RouteCollection();
        $this->matcher = new RouteMatcher(
            new RouteCompiler((bool) ($config['matching']['case_sensitive'] ?? false)),
            (bool) ($config['matching']['case_sensitive'] ?? false),
        );
    }

    // ─── ثبت روت ──────────────────────────────────────────────────────────

    /** @param \Closure|class-string|array{0: class-string|object, 1: string} $action */
    public function get(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET'], $uri, $action);
    }

    public function post(string $uri, mixed $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    public function put(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    public function patch(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    public function delete(string $uri, mixed $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    public function options(string $uri, mixed $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    public function head(string $uri, mixed $action): Route
    {
        return $this->addRoute(['HEAD'], $uri, $action);
    }

    /** @param list<string> $methods */
    public function match(array $methods, string $uri, mixed $action): Route
    {
        return $this->addRoute($methods, $uri, $action);
    }

    public function any(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $action);
    }

    /** روت نمایش View (بدون controller). @param array<string, mixed> $data */
    public function view(string $uri, string $view, array $data = [], int $status = 200): Route
    {
        return $this->get($uri, static function () use ($view, $data, $status): Response {
            /** @var \Tavaan\Core\Contracts\Container\ContainerInterface $c */
            $c = \Tavaan\Core\Facade\Facade::getFacadeApplication();
            $factory = $c->make('view');

            return new Response($factory->make($view, $data)->render(), $status);
        });
    }

    public function redirect(string $uri, string $destination, int $status = 302): Route
    {
        // 🔒 Open Redirect: فقط مسیر نسبی یا دامنهٔ مجاز
        $this->assertSafeRedirect($destination);

        return $this->any($uri, static fn (): Response => new Response('', $status, ['Location' => $destination]));
    }

    public function permanentRedirect(string $uri, string $destination): Route
    {
        return $this->redirect($uri, $destination, 301);
    }

    /** Fallback فقط برای GET/HEAD (مانند Laravel) — تا ۴۰۵ خالص بماند. */
    public function fallback(mixed $action): Route
    {
        $route = $this->addRoute(['GET'], '/{fallbackPlaceholder}', $action);
        $route->where('fallbackPlaceholder', '.*');
        $this->fallbackRoute = $route;
        $this->matcherStale = true;

        return $route;
    }

    /**
     * @param list<string> $methods
     * @param \Closure|class-string|array{0: class-string|object, 1: string} $action
     */
    public function addRoute(array $methods, string $uri, mixed $action): Route
    {
        $route = new Route($methods, $uri, $action);
        $this->applyGroupStack($route);
        $this->routes->add($route);
        $this->matcherStale = true;

        return $route;
    }

    // ─── Group ────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $attributes prefix|name|middleware|domain|where
     */
    public function group(array $attributes, \Closure $routes): void
    {
        $this->groupStack[] = $attributes;
        try {
            $routes($this);
        } finally {
            \array_pop($this->groupStack);
        }
    }

    /** Fluent group builder: Route::prefix('api')->middleware('api')->group(…). */
    public function prefix(string $prefix): RouteRegistrar
    {
        return (new RouteRegistrar($this))->prefix($prefix);
    }

    public function name(string $name): RouteRegistrar
    {
        return (new RouteRegistrar($this))->name($name);
    }

    /** @param list<string>|string $middleware */
    public function middleware(array|string $middleware): RouteRegistrar
    {
        return (new RouteRegistrar($this))->middleware($middleware);
    }

    public function domain(string $domain): RouteRegistrar
    {
        return (new RouteRegistrar($this))->domain($domain);
    }

    /** @param array<string, string> $wheres */
    public function where(array $wheres): RouteRegistrar
    {
        return (new RouteRegistrar($this))->where($wheres);
    }

    public function scopeBindings(): RouteRegistrar
    {
        return (new RouteRegistrar($this))->scopeBindings();
    }

    // ─── Resource ─────────────────────────────────────────────────────────

    /**
     * هفت روت RESTful (index/create/store/show/edit/update/destroy).
     *
     * @param class-string $controller
     */
    public function resource(string $name, string $controller, array $only = [], array $except = []): ResourceRegistrar
    {
        return (new ResourceRegistrar($this))->register($name, $controller, api: false, only: $only, except: $except);
    }

    /**
     * پنج روت API (index/store/show/update/destroy).
     *
     * @param class-string $controller
     */
    public function apiResource(string $name, string $controller, array $only = [], array $except = []): ResourceRegistrar
    {
        return (new ResourceRegistrar($this))->register($name, $controller, api: true, only: $only, except: $except);
    }

    // ─── Binding ──────────────────────────────────────────────────────────

    /** @param class-string $class */
    public function model(string $param, string $class): void
    {
        $this->modelBindings[$param] = $class;
    }

    public function bind(string $param, \Closure $resolver): void
    {
        $this->binders[$param] = $resolver;
    }

    /** @return array<string, \Closure(string): mixed> */
    public function binders(): array
    {
        return $this->binders;
    }

    /** @return array<string, class-string> */
    public function modelBindings(): array
    {
        return $this->modelBindings;
    }

    // ─── Matching ─────────────────────────────────────────────────────────

    public function matchRequest(Request $request): RouteMatch
    {
        $this->refreshMatcher();

        $events = $this->events;
        try {
            $match = $this->matcher->match($request->method(), $request->path(), $request->host());
        } catch (\Tavaan\Core\Exceptions\Http\RouteNotFoundException $e) {
            $events?->dispatch('router.not_found', ['path' => $request->path()]);
            throw $e;
        } catch (\Tavaan\Core\Exceptions\Http\MethodNotAllowedException $e) {
            $events?->dispatch('router.method_not_allowed', ['path' => $request->path()]);
            throw $e;
        }

        $this->current = $match;
        $events?->dispatch('router.matched', ['uri' => $match->route->uri(), 'name' => $match->route->getName()]);

        return $match;
    }

    public function matchUri(string $method, string $path, string $host = ''): RouteMatch
    {
        $this->refreshMatcher();

        return $this->matcher->match($method, $path, $host);
    }

    public function current(): ?RouteMatch
    {
        return $this->current;
    }

    public function currentRouteName(): ?string
    {
        $name = $this->current?->route->getName() ?? '';

        return $name === '' ? null : $name;
    }

    public function has(string $name): bool
    {
        return $this->routes->hasName($name);
    }

    public function routes(): RouteCollection
    {
        return $this->routes;
    }

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function fallbackRoute(): ?Route
    {
        return $this->fallbackRoute;
    }

    // ─── Cache ────────────────────────────────────────────────────────────

    /** بارگذاری روت‌ها از کش (route:cache). @param list<array<string, mixed>> $data */
    public function loadCached(array $data): void
    {
        $this->routes = RouteCollection::fromArray($data);
        $this->matcherStale = true;
    }

    /** @return list<array<string, mixed>> */
    public function toCacheArray(): array
    {
        return $this->routes->toArray();
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function refreshMatcher(): void
    {
        if ($this->matcherStale) {
            $this->matcher->setRoutes($this->routes);
            $this->matcherStale = false;
        }
    }

    private function applyGroupStack(Route $route): void
    {
        foreach (\array_reverse($this->groupStack) as $group) {
            if (isset($group['prefix']) && $group['prefix'] !== '') {
                $route->prependPrefix((string) $group['prefix']);
            }
            if (isset($group['name']) && $group['name'] !== '') {
                $route->prependName((string) $group['name']);
            }
            if (isset($group['middleware'])) {
                $route->prependMiddleware((array) $group['middleware']);
            }
            if (isset($group['domain'])) {
                $route->setDomainIfEmpty((string) $group['domain']);
            }
            if (isset($group['where'])) {
                $route->mergeWheres((array) $group['where']);
            }
        }
    }

    /** 🔒 Open Redirect prevention (DOC-040 §11). */
    private function assertSafeRedirect(string $destination): void
    {
        if (\str_starts_with($destination, '/') && !\str_starts_with($destination, '//')) {
            return;   // مسیر نسبی امن
        }

        $appUrl = $this->container->bound('config')
            ? (string) $this->container->make('config')->get('app.url', '')
            : '';
        if ($appUrl !== '' && \str_starts_with($destination, \rtrim($appUrl, '/'))) {
            return;   // دامنهٔ خود اپ
        }

        if (\preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $destination) !== 1 && !\str_starts_with($destination, '//')) {
            return;   // نسبی بدون /
        }

        throw new \InvalidArgumentException(\sprintf('Route::redirect only allows relative paths or app.url destinations (got "%s").', \substr($destination, 0, 80)));
    }
}
