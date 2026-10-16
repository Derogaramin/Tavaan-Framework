<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Metrics;

/**
 * تَوان | Tavaan — Observability
 * Summary — چندک‌ها (quantile) با نمونه‌گیری مخزنی (Algorithm R).
 *
 * 🔒 ظرفیت نمونه‌ها محدود است؛ پس از پرشدن، نمونه‌های جدید با احتمال
 * n/seen جایگزین می‌شوند تا چندک‌ها تقریبی اما بدون رشد حافظه بمانند.
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Summary extends Metric
{
    public const DEFAULT_QUANTILES = [0.5, 0.9, 0.99];

    /** @var list<float> */
    private array $quantiles;

    private int $maxSamples;

    /**
     * @param list<float>|null $quantiles
     */
    public function __construct(string $name, ?array $quantiles = null, string $help = '', int $maxSamples = 1024)
    {
        parent::__construct($name, $help);

        $q = $quantiles ?? self::DEFAULT_QUANTILES;
        $clean = [];
        foreach ($q as $v) {
            $v = (float) $v;
            if ($v > 0.0 && $v < 1.0) {
                $clean[] = $v;
            }
        }
        \sort($clean);
        $this->quantiles = $clean === [] ? self::DEFAULT_QUANTILES : \array_values(\array_unique($clean));
        $this->maxSamples = \max(16, $maxSamples);
    }

    public function type(): string
    {
        return 'summary';
    }

    /** @return list<float> */
    public function quantiles(): array
    {
        return $this->quantiles;
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function observe(float $value, array $labels = []): void
    {
        $key = $this->labelKey($labels);
        if (!isset($this->samples[$key])) {
            $this->samples[$key] = [
                'labels' => $this->normalizeLabels($labels),
                'values' => [],
                'sum' => 0.0,
                'count' => 0,
            ];
        }

        $sample = &$this->samples[$key];
        $seen = (int) $sample['count'] + 1;
        if (\count($sample['values']) < $this->maxSamples) {
            $sample['values'][] = $value;
        } elseif (\random_int(1, $seen) <= $this->maxSamples) {
            // Algorithm R — جایگزینی مخزنی
            $sample['values'][\random_int(0, $this->maxSamples - 1)] = $value;
        }
        $sample['sum'] = (float) $sample['sum'] + $value;
        $sample['count'] = $seen;
        unset($sample);
        $this->mutate();
    }

    /**
     * چندک با روش nearest-rank روی نمونه‌های مرتب‌شده.
     *
     * @param array<string, string|int|float|bool> $labels
     */
    public function quantile(float $q, array $labels = []): ?float
    {
        $values = $this->samples[$this->labelKey($labels)]['values'] ?? [];
        if ($values === []) {
            return null;
        }
        $values = \array_map('floatval', $values);
        \sort($values);
        $rank = (int) \ceil($q * \count($values));
        $rank = \max(1, \min($rank, \count($values)));

        return (float) $values[$rank - 1];
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function sum(array $labels = []): float
    {
        return (float) ($this->samples[$this->labelKey($labels)]['sum'] ?? 0.0);
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function count(array $labels = []): int
    {
        return (int) ($this->samples[$this->labelKey($labels)]['count'] ?? 0);
    }

    public function state(): array
    {
        return [...parent::state(), 'quantiles' => $this->quantiles];
    }
}
