<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: سرویس Factory. */
final class FactoryService
{
    public readonly string $id;

    public function __construct()
    {
        $this->id = \bin2hex(\random_bytes(4));
    }
}
