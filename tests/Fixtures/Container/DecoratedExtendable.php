<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: Decorator برای extend(). */
final class DecoratedExtendable implements ExtendableInterface
{
    public function __construct(private readonly ExtendableInterface $inner, private readonly string $tag = 'decorated')
    {
    }

    public function name(): string
    {
        return $this->tag . '(' . $this->inner->name() . ')';
    }
}
