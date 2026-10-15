<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pipeline;

/** Fixture: لایهٔ A پیاز. */
final class PipeA
{
    public function handle(mixed $passable, \Closure $next): mixed
    {
        PipelineTrace::record('a:in');
        $result = $next($passable);
        PipelineTrace::record('a:out');

        return $result;
    }

    public function terminate(mixed $passable, mixed $result): void
    {
        PipelineTrace::record('a:terminate');
    }
}
