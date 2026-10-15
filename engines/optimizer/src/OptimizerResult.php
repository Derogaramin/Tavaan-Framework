<?php

declare(strict_types=1);

namespace Tavaan\Engine\Optimizer;

/**
 * تَوان | Tavaan — Optimizer
 * OptimizerResult — زمان اجرای هر cacher (میلی‌ثانیه).
 *
 * @api 1.0
 * @see docs/03-engines/optimizer.md
 */
final readonly class OptimizerResult
{
    /** @param array<string, float> $timings نام cacher → ms */
    public function __construct(public array $timings)
    {
    }

    public function total(): float
    {
        return \array_sum($this->timings);
    }
}
