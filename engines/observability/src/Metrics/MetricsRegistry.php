<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Metrics;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Observability\MetricsInterface;
use Tavaan\Engine\Observability\Exceptions\ObservabilityException;

/**
 * تَوان | Tavaan — Observability
 * MetricsRegistry — ساخت/بازیابی سنجه‌ها + پایستگی write-through در cache.
 *
 * - درون یک درخواست: حافظهٔ فرایند
 * - بین درخواست‌ها (persist=true): ذخیره در cache (کلید tvn:metrics:snapshot)
 *   تا endpoint ‏/metrics تاریخچهٔ تجمعی داشته باشد.
 *
 * ⚠️ پایستگی read-modify-write است و در بار همزمانِ بالا تقریبی می‌ماند؛
 * برای دقت کامل، collector سرتاسری (pushgateway) توصیه می‌شود.
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class MetricsRegistry implements MetricsInterface
{
    private const CACHE_KEY = 'tvn:metrics:snapshot';

    /** @var array<string, Metric> */
    private array $metrics = [];

    /** @var array<string, array<string, mixed>> وضعیت بارگذاری‌شده از cache */
    private array $persisted = [];

    private bool $loaded = false;

    private string $prefix;

    private bool $persist;

    /** @param array<string, mixed> $config کلیدهای metrics در config/observability.php */
    public function __construct(
        private readonly ?CacheRepository $cache = null,
        array $config = [],
    ) {
        $this->prefix = (string) ($config['prefix'] ?? 'tavaan');
        $this->persist = (bool) ($config['persist'] ?? false) && $this->cache !== null;
    }

    public function counter(string $name, string $help = ''): Counter
    {
        /** @var Counter */
        return $this->metric($name, fn (): Counter => new Counter($name, $help), $help);
    }

    /** @param array<string, string|int|float|bool> $labels */
    public function gauge(string $name, float|int|null $value = null, array $labels = [], string $help = ''): Gauge
    {
        /** @var Gauge */
        $gauge = $this->metric($name, fn (): Gauge => new Gauge($name, $help), $help);
        if ($value !== null) {
            $gauge->set($value, $labels);
        }

        return $gauge;
    }

    /** @param list<float>|null $buckets */
    public function histogram(string $name, ?array $buckets = null, string $help = ''): Histogram
    {
        /** @var Histogram */
        return $this->metric($name, fn (): Histogram => new Histogram($name, $buckets, $help), $help);
    }

    /** @param list<float>|null $quantiles */
    public function summary(string $name, ?array $quantiles = null, string $help = ''): Summary
    {
        /** @var Summary */
        return $this->metric($name, fn (): Summary => new Summary($name, $quantiles, $help), $help);
    }

    public function timer(string $name, callable $fn, array $labels = []): mixed
    {
        $start = \hrtime(true);
        try {
            return $fn();
        } finally {
            $elapsed = (\hrtime(true) - $start) / 1e9;   // ثانیه
            $this->histogram($name, help: 'Timer "' . $name . '" duration in seconds')->observe($elapsed, $labels);
        }
    }

    public function collect(): string
    {
        $this->ensureLoaded();
        $this->hydratePersisted();

        return PrometheusFormatter::render($this->metrics, $this->prefix);
    }

    /**
     * بازیابی سنجه‌های پایدا‌شده‌ای که در این درخواست لمس نشده‌اند
     * (تا /metrics تاریخچهٔ تجمعی را نشان دهد).
     */
    private function hydratePersisted(): void
    {
        foreach ($this->persisted as $name => $state) {
            if (isset($this->metrics[$name]) || !\is_array($state)) {
                continue;
            }
            $help = (string) ($state['help'] ?? '');
            $metric = match ((string) ($state['type'] ?? '')) {
                'counter' => new Counter((string) $name, $help),
                'gauge' => new Gauge((string) $name, $help),
                'histogram' => new Histogram((string) $name, isset($state['buckets']) && \is_array($state['buckets']) ? $state['buckets'] : null, $help),
                'summary' => new Summary((string) $name, isset($state['quantiles']) && \is_array($state['quantiles']) ? $state['quantiles'] : null, $help),
                default => null,
            };
            if ($metric === null) {
                continue;
            }
            $metric->hydrate($state);
            if ($this->persist) {
                $metric->setOnMutate($this->save(...));
            }
            $this->metrics[(string) $name] = $metric;
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function snapshot(): array
    {
        $out = [];
        foreach ($this->metrics as $name => $metric) {
            $out[$name] = $metric->state();
        }

        return $out;
    }

    /** @return array<string, Metric> */
    public function all(): array
    {
        return $this->metrics;
    }

    public function reset(): void
    {
        $this->metrics = [];
        $this->persisted = [];
        $this->loaded = true;
        if ($this->persist) {
            $this->cache?->forget(self::CACHE_KEY);
        }
    }

    /**
     * @template M of Metric
     * @param \Closure(): M $factory
     * @return M
     */
    private function metric(string $name, \Closure $factory, string $help): Metric
    {
        if (\trim($name) === '') {
            throw ObservabilityException::invalidMetricName($name);
        }
        $this->ensureLoaded();

        if (!isset($this->metrics[$name])) {
            $metric = $factory();
            $metric->setHelp($help);
            if (isset($this->persisted[$name])) {
                $metric->hydrate($this->persisted[$name]);
            }
            if ($this->persist) {
                $metric->setOnMutate($this->save(...));
            }
            $this->metrics[$name] = $metric;
        } elseif ($help !== '') {
            $this->metrics[$name]->setHelp($help);
        }

        return $this->metrics[$name];
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        if (!$this->persist) {
            return;
        }

        /** @var array<string, array<string, mixed>>|null $state */
        $state = $this->cache?->get(self::CACHE_KEY);
        $this->persisted = \is_array($state) ? $state : [];
    }

    private function save(): void
    {
        if (!$this->persist) {
            return;
        }
        $this->cache?->put(self::CACHE_KEY, $this->snapshot(), 0);
    }
}
