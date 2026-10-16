<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Metrics;

/**
 * تَوان | Tavaan — Observability
 * Histogram — توزیع مقادیر در سطل‌ها (مانند مدت‌زمان درخواست).
 *
 * شمارش سطل‌ها درونی «غیرتجمعی» است؛ فرمت Prometheus تجمعی صادر می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Histogram extends Metric
{
    /** سطل‌های پیش‌فرض (ثانیه) — هم‌تراز کلاینت‌های Prometheus. */
    public const DEFAULT_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    /** @var list<float> سطل‌های مرتب یکتا (بدون +Inf) */
    private array $buckets;

    /**
     * @param list<float>|null $buckets
     */
    public function __construct(string $name, ?array $buckets = null, string $help = '')
    {
        parent::__construct($name, $help);

        $raw = $buckets ?? self::DEFAULT_BUCKETS;
        $finite = [];
        foreach ($raw as $b) {
            $b = (float) $b;
            if ($b > 0 && $b !== INF) {
                $finite[] = $b;
            }
        }
        $finite = \array_values(\array_unique($finite));
        \sort($finite);
        $this->buckets = $finite === [] ? self::DEFAULT_BUCKETS : $finite;
    }

    public function type(): string
    {
        return 'histogram';
    }

    /** @return list<float> */
    public function buckets(): array
    {
        return $this->buckets;
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function observe(float $value, array $labels = []): void
    {
        $key = $this->labelKey($labels);
        if (!isset($this->samples[$key])) {
            $this->samples[$key] = [
                'labels' => $this->normalizeLabels($labels),
                'counts' => \array_fill(0, \count($this->buckets), 0),
                'sum' => 0.0,
                'count' => 0,
            ];
        }

        $sample = &$this->samples[$key];
        // 🔒 هر مشاهده فقط در کوچک‌ترین سطلِ شاملش ثبت می‌شود (غیرتجمعی)؛
        // تجمعی‌سازی در PrometheusFormatter انجام می‌شود.
        foreach ($this->buckets as $i => $bound) {
            if ($value <= $bound) {
                $sample['counts'][$i] = (int) $sample['counts'][$i] + 1;

                break;
            }
        }
        $sample['sum'] = (float) $sample['sum'] + $value;
        $sample['count'] = (int) $sample['count'] + 1;
        unset($sample);
        $this->mutate();
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

    /** @param array<string, mixed> $state */
    public function hydrate(array $state): void
    {
        parent::hydrate($state);
        if (isset($state['buckets']) && \is_array($state['buckets']) && $state['buckets'] !== []) {
            $saved = \array_values(\array_map('floatval', $state['buckets']));
            if ($saved !== $this->buckets) {
                // سطل‌های ذخیره‌شده متفاوتند — دادهٔ کهنهٔ سطل‌ها قابل اتکا نیست
                foreach ($this->samples as $k => $sample) {
                    $this->samples[$k]['counts'] = \array_fill(0, \count($this->buckets), 0);
                }
            }
        }
    }

    public function state(): array
    {
        return [...parent::state(), 'buckets' => $this->buckets];
    }
}
