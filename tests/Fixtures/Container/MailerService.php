<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: مصرف‌کنندهٔ Contextual. */
final class MailerService
{
    public function __construct(public readonly TransportInterface $transport)
    {
    }
}
