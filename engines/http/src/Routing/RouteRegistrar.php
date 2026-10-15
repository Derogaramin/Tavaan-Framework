<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

/**
 * تَوان | Tavaan — Routing
 * RouteRegistrar — Fluent group builder.
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class RouteRegistrar
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(private readonly Router $router)
    {
    }

    public function prefix(string $prefix): self
    {
        $this->attributes['prefix'] = \trim($this->attributes['prefix'] ?? '', '/') === ''
            ? $prefix
            : \trim((string) $this->attributes['prefix'], '/') . '/' . \trim($prefix, '/');

        return $this;
    }

    public function name(string $name): self
    {
        $this->attributes['name'] = ($this->attributes['name'] ?? '') . $name;

        return $this;
    }

    /** @param list<string>|string $middleware */
    public function middleware(array|string $middleware): self
    {
        $existing = (array) ($this->attributes['middleware'] ?? []);
        $this->attributes['middleware'] = [...$existing, ...(array) $middleware];

        return $this;
    }

    public function domain(string $domain): self
    {
        $this->attributes['domain'] = $domain;

        return $this;
    }

    /** @param array<string, string> $wheres */
    public function where(array $wheres): self
    {
        $this->attributes['where'] = [...(array) ($this->attributes['where'] ?? []), ...$wheres];

        return $this;
    }

    public function scopeBindings(): self
    {
        $this->attributes['scope_bindings'] = true;

        return $this;
    }

    /** اجرای group با attributeهای انباشته. */
    public function group(\Closure $routes): void
    {
        $this->router->group($this->attributes, $routes);
    }

    /**
     * forwards: Route::prefix('x')->get(...) — ثبت روت با attributeهای جاری.
     *
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $forwardable = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'match', 'any', 'view', 'redirect', 'permanentRedirect', 'resource', 'apiResource', 'fallback'];

        if (!\in_array($method, $forwardable, true)) {
            throw new \BadMethodCallException(\sprintf('Method [%s] does not exist on RouteRegistrar.', $method));
        }

        $result = null;
        $this->router->group($this->attributes, function (Router $router) use ($method, $arguments, &$result): void {
            $result = $router->{$method}(...$arguments);
        });

        return $result;
    }
}
