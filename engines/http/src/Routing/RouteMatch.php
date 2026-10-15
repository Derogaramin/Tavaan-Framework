<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

/**
 * تَوان | Tavaan — Routing
 * RouteMatch — نتیجهٔ تطبیق: روت + پارامترهای استخراج‌شده.
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final readonly class RouteMatch
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public Route $route,
        public array $parameters = [],
    ) {
    }

    public function parameter(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $this->route->getDefaults()[$name] ?? $default;
    }

    /** همهٔ پارامترها + defaultها. @return array<string, mixed> */
    public function allParameters(): array
    {
        return [...$this->route->getDefaults(), ...$this->parameters];
    }
}
