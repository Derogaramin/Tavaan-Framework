<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: چرخه A→B→A. */
final class CycA
{
    public function __construct(public readonly CycB $b)
    {
    }
}
