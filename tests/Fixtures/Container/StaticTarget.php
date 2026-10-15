<?php

declare(strict_types=1);

namespace Tests\Fixtures\Container;

/** Fixture: هدف call() — static/instance/invokable. */
final class StaticTarget
{
    public static function greetStatic(string $name = 'world'): string
    {
        return 'static:' . $name;
    }

    public function show(GreeterInterface $greeter, int $id = 0): string
    {
        return $greeter->greet((string) $id);
    }

    public function __invoke(GreeterInterface $greeter): string
    {
        return $greeter->greet('invoked');
    }
}
