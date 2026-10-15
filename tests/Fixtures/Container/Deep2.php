<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: زنجیرهٔ Auto-Wiring سطح 2. */
final class Deep2
{
    public function __construct(public readonly Deep3 $d)
    {
    }
}
