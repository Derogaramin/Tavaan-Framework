<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Metrics;

/**
 * تَوان | Tavaan — Observability
 * Gauge — سنجهٔ افزایشی/کاهشی (مانند queue_size یا مصرف حافظه).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Gauge extends Metric
{
    public function type(): string
    {
        return 'gauge';
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function set(float|int $value, array $labels = []): void
    {
        $key = $this->labelKey($labels);
        $this->samples[$key] = ['labels' => $this->normalizeLabels($labels), 'value' => (float) $value];
        $this->mutate();
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function increment(float|int $by = 1, array $labels = []): void
    {
        $this->set($this->value($labels) + (float) $by, $labels);
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function decrement(float|int $by = 1, array $labels = []): void
    {
        $this->set($this->value($labels) - (float) $by, $labels);
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function value(array $labels = []): float
    {
        $key = $this->labelKey($labels);

        return (float) ($this->samples[$key]['value'] ?? 0.0);
    }
}
