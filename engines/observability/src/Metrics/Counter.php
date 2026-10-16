<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Metrics;

use Tavaan\Engine\Observability\Exceptions\ObservabilityException;

/**
 * تَوان | Tavaan — Observability
 * Counter — سنجهٔ فقطافزایشی (مانند http_requests_total).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Counter extends Metric
{
    public function type(): string
    {
        return 'counter';
    }

    /**
     * @param float|int             $by     مقدار افزایش (نامنفی)
     * @param array<string, string|int|float|bool> $labels
     */
    public function increment(float|int $by = 1, array $labels = []): void
    {
        if ($by < 0) {
            throw ObservabilityException::negativeIncrement($this->name);
        }

        $key = $this->labelKey($labels);
        $sample = $this->samples[$key] ?? ['labels' => $this->normalizeLabels($labels), 'value' => 0.0];
        $sample['value'] = (float) $sample['value'] + (float) $by;
        $this->samples[$key] = $sample;
        $this->mutate();
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function value(array $labels = []): float
    {
        $key = $this->labelKey($labels);

        return (float) ($this->samples[$key]['value'] ?? 0.0);
    }
}
