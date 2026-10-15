<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: پیاده‌سازی پایه. */
final class BaseExtendable implements ExtendableInterface
{
    public function name(): string
    {
        return 'base';
    }
}
