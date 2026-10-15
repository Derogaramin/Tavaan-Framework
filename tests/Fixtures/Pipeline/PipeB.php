<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pipeline;

/** Fixture: لایهٔ B پیاز. */
final class PipeB
{
    public function handle(mixed $passable, \Closure $next): mixed
    {
        PipelineTrace::record('b:in');
        $result = $next($passable);
        PipelineTrace::record('b:out');

        return $result;
    }

    public function terminate(mixed $passable, mixed $result): void
    {
        PipelineTrace::record('b:terminate');
    }
}
