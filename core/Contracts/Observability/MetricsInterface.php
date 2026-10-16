<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Observability;

/**
 * تَوان | Tavaan — Contracts
 * MetricsInterface — قرارداد ثبت و جمع‌آوری سنجه‌ها (DOC-067 §2).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
interface MetricsInterface
{
    /** ساخت/بازیابی یک Counter. */
    public function counter(string $name, string $help = ''): mixed;

    /** ساخت/بازیابی یک Gauge (با مقدار اولیهٔ اختیاری). */
    public function gauge(string $name, float|int|null $value = null, array $labels = [], string $help = ''): mixed;

    /** ساخت/بازیابی یک Histogram. */
    public function histogram(string $name, ?array $buckets = null, string $help = ''): mixed;

    /** ساخت/بازیابی یک Summary. */
    public function summary(string $name, ?array $quantiles = null, string $help = ''): mixed;

    /**
     * زمان‌سنجی یک callable و ثبت در Histogram.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function timer(string $name, callable $fn, array $labels = []): mixed;

    /** خروجی Prometheus text format. */
    public function collect(): string;

    /** پاک‌کردن همهٔ سنجه‌ها (حافظه + پایستگی). */
    public function reset(): void;
}
