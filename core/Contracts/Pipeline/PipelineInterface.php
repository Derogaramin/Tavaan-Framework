<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Pipeline;

/**
 * قرارداد Pipeline عمومی (Passable دلخواه).
 *
 * @api 1.0
 * @see docs/03-engines/pipeline.md
 */
interface PipelineInterface
{
    public function send(mixed $passable): static;

    /** @param iterable<string|callable|object> $pipes */
    public function through(iterable $pipes): static;

    public function pipe(string|callable|object $pipe): static;

    public function via(string $method): static;

    public function then(callable $destination): mixed;

    public function thenReturn(): mixed;
}
