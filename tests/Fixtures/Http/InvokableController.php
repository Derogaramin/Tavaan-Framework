<?php

declare(strict_types=1);

namespace Tests\Fixtures\Http;

/** Fixture: کنترلر invokable. */
final class InvokableController
{
    public function __invoke(): string
    {
        return 'invoked-ok';
    }
}
