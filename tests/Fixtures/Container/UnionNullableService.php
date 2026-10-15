<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: Union با null. */
final class UnionNullableService
{
    public function __construct(public readonly UnionA|null $dep = null)
    {
    }
}
