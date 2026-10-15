<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: زنجیرهٔ Auto-Wiring سطح 4. */
final class Deep4
{
    public function __construct(public readonly Deep5 $d)
    {
    }
}
