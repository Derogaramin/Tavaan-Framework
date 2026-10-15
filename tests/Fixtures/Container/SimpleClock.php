<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: ساعت ساده. */
final class SimpleClock
{
    public function now(): string
    {
        return '12:00';
    }
}
