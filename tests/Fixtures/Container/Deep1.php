<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: زنجیرهٔ Auto-Wiring سطح 1. */
final class Deep1
{
    public function __construct(public readonly Deep2 $d)
    {
    }
}
