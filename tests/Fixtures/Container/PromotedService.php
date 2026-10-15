<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: Promoted readonly property (T-12). */
final class PromotedService
{
    public function __construct(
        public readonly GreeterInterface $greeter,
        private readonly int $count = 5,
    ) {
    }

    public function count(): int
    {
        return $this->count;
    }
}
