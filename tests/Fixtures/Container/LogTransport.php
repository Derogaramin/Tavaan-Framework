<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: Transport نوع Log. */
final class LogTransport implements TransportInterface
{
    public function name(): string
    {
        return 'log';
    }
}
