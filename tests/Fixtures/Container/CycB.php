<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: چرخه B→A→B. */
final class CycB
{
    public function __construct(public readonly CycA $a)
    {
    }
}
