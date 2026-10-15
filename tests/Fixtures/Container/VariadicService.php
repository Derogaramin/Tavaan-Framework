<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: پارامتر Variadic. */
final class VariadicService
{
    public readonly string $name;

    /** @var list<string> */
    public readonly array $items;

    public function __construct(string $name = 'base', string ...$items)
    {
        $this->name = $name;
        $this->items = $items;
    }

    public function count(): int
    {
        return \count($this->items);
    }
}
