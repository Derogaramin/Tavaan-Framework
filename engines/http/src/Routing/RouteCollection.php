<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

use Tavaan\Engine\Http\Routing\Exceptions\UrlGenerationException;

/**
 * تَوان | Tavaan — Routing
 * RouteCollection — مجموعهٔ روت‌ها + نگاشت نام.
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class RouteCollection
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $byName = [];

    private bool $nameIndexStale = true;

    public function add(Route $route): Route
    {
        $route->setOrder(\count($this->routes));
        $this->routes[] = $route;
        $this->nameIndexStale = true;   // name() ممکن است بعداً (chained) صدا زده شود

        return $route;
    }

    /** بازسازی تنبل نگاشت نام (name() پس از add فراخوانی می‌شود). */
    private function ensureNameIndex(): void
    {
        if (!$this->nameIndexStale) {
            return;
        }

        $this->byName = [];
        foreach ($this->routes as $route) {
            if ($route->getName() !== '') {
                $this->byName[$route->getName()] = $route;
            }
        }
        $this->nameIndexStale = false;
    }

    /** @return list<Route> */
    public function all(): array
    {
        return $this->routes;
    }

    public function count(): int
    {
        return \count($this->routes);
    }

    public function getByName(string $name): Route
    {
        $this->ensureNameIndex();

        return $this->byName[$name] ?? throw UrlGenerationException::routeNotFound($name);
    }

    public function hasName(string $name): bool
    {
        $this->ensureNameIndex();

        return isset($this->byName[$name]);
    }

    /** @return array<string, Route> */
    public function names(): array
    {
        $this->ensureNameIndex();

        return $this->byName;
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return \array_map(static fn (Route $r): array => $r->toArray(), $this->routes);
    }

    public static function fromArray(array $data): self
    {
        $collection = new self();
        foreach ($data as $item) {
            $collection->add(Route::fromArray((array) $item));
        }

        return $collection;
    }
}
