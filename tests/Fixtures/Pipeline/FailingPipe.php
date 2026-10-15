<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pipeline;

/** Fixture: Pipe با exception. */
final class FailingPipe
{
    public function handle(mixed $passable, \Closure $next): mixed
    {
        throw new \RuntimeException('pipe boom');
    }

    public function terminate(mixed $passable, mixed $result): void
    {
        PipelineTrace::record('fail:terminate');
    }
}
