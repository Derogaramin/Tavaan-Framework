<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pipeline;

/** Fixture: قطع زنجیره بدون $next. */
final class ShortCircuitPipe
{
    public function handle(mixed $passable, \Closure $next): mixed
    {
        PipelineTrace::record('short-circuit');

        return 'halted';
    }
}
