<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: زنجیرهٔ Auto-Wiring سطح 3. */
final class Deep3
{
    public function __construct(public readonly Deep4 $d)
    {
    }
}
