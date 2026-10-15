<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: پیاده‌سازی هر دو Union. */
final class UnionImpl implements UnionA, UnionB
{
    public function tag(): string
    {
        return 'union-impl';
    }
}
