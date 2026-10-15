<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: مصرف‌کنندهٔ Contextual دوم. */
final class NotifierService
{
    public function __construct(public readonly TransportInterface $transport)
    {
    }
}
