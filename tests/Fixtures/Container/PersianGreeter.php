<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: پیاده‌سازی فارسی Greeter. */
final class PersianGreeter implements GreeterInterface
{
    public function greet(string $name): string
    {
        return "سلام {$name}!";
    }
}
