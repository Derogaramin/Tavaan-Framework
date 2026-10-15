<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: Enum injection با default. */
final class EnumService
{
    public function __construct(public readonly StatusEnum $status = StatusEnum::Active)
    {
    }
}
