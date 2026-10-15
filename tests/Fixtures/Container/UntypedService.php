<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: پارامتر بدون type (strict → خطا). */
final class UntypedService
{
    /** @param mixed $anything پارامتر عمداً بدون type */
    public function __construct($anything = null)
    {
    }
}
