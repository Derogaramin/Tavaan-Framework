<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: سرویس با ۲ وابستگی (Auto-Wiring). */
final class WelcomeService
{
    public function __construct(
        public readonly GreeterInterface $greeter,
        public readonly SimpleClock $clock,
    ) {
    }

    public function run(string $name): string
    {
        return $this->greeter->greet($name) . ' (' . $this->clock->now() . ')';
    }
}
