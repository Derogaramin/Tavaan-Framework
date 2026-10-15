<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: Transport نوع SMTP. */
final class SmtpTransport implements TransportInterface
{
    public function name(): string
    {
        return 'smtp';
    }
}
