<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pipeline;

/** Fixture: لایهٔ C پیاز. */
final class PipeC
{
    public function handle(mixed $passable, \Closure $next): mixed
    {
        PipelineTrace::record('c:in');
        $result = $next($passable);
        PipelineTrace::record('c:out');

        return $result;
    }

    public function terminate(mixed $passable, mixed $result): void
    {
        PipelineTrace::record('c:terminate');
    }
}
