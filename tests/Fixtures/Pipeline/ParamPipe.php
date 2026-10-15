<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pipeline;

/** Fixture: Middleware با پارامتر (`auth:web,admin`). */
final class ParamPipe
{
    public function handle(mixed $passable, \Closure $next, string ...$params): mixed
    {
        PipelineTrace::record('params:' . \implode('|', $params));

        return $next($passable);
    }
}
