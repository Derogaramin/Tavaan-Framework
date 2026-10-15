<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: Union مبهم (هر دو bound). */
final class UnionService
{
    public function __construct(public readonly UnionA|UnionB $dep)
    {
    }
}
