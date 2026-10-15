<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: پارامتر builtin بدون default (غیرقابل resolve). */
final class StringDepService
{
    public function __construct(public readonly string $name)
    {
    }
}
