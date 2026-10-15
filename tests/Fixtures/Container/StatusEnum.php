<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: Enum برای تزریق با default (T-13). */
enum StatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
