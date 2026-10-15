<?php

declare(strict_types=1);

namespace Tavaan\Engine\View;

/**
 * تَوان | Tavaan — View
 * DirectiveRegistry — ثبت directiveهای سفارشی (فقط از Provider — نه ورودی کاربر).
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class DirectiveRegistry
{
    /** @var array<string, \Closure(?string): string> */
    private array $directives = [];

    /** @param \Closure(?string): string $handler */
    public function register(string $name, \Closure $handler): void
    {
        $this->directives[$name] = $handler;
    }

    public function has(string $name): bool
    {
        return isset($this->directives[$name]);
    }

    public function call(string $name, ?string $expression): string
    {
        return (string) $this->directives[$name]($expression);
    }

    /** @return list<string> */
    public function names(): array
    {
        $names = \array_keys($this->directives);
        \sort($names);

        return $names;
    }
}
