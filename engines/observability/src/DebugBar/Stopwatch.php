<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\DebugBar;

/**
 * تَوان | Tavaan — Observability
 * Stopwatch — زمان‌سنج فازها (mark نقطه‌ای / measure بازه‌ای).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Stopwatch
{
    private int $originNs;

    /** @var array<string, float> name → میلی‌ثانیه */
    private array $marks = [];

    /** @var array<string, int> name → ns شروع بازه */
    private array $running = [];

    /** @var array<string, float> name → میلی‌ثانیهٔ بازه */
    private array $intervals = [];

    public function __construct()
    {
        $this->originNs = \hrtime(true);
    }

    /** ثبت یک نقطهٔ زمانی (نسبت به مبدأ). */
    public function mark(string $name): void
    {
        $this->marks[$name] = (\hrtime(true) - $this->originNs) / 1e6;
    }

    public function start(string $name): void
    {
        $this->running[$name] = \hrtime(true);
    }

    public function stop(string $name): float
    {
        if (!isset($this->running[$name])) {
            return 0.0;
        }
        $ms = (\hrtime(true) - $this->running[$name]) / 1e6;
        unset($this->running[$name]);
        $this->intervals[$name] = ($this->intervals[$name] ?? 0.0) + $ms;

        return $ms;
    }

    /** ثبت مستقیم یک بازهٔ اندازه‌گیری‌شده. */
    public function record(string $name, float $ms): void
    {
        $this->intervals[$name] = ($this->intervals[$name] ?? 0.0) + $ms;
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function measure(string $name, callable $fn): mixed
    {
        $this->start($name);
        try {
            return $fn();
        } finally {
            $this->stop($name);
        }
    }

    /**
     * همهٔ زمان‌ها (نقاط + بازه‌ها) در یک آرایه.
     *
     * @return array<string, float>
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->marks as $name => $ms) {
            $out[$name . ' @'] = \round($ms, 3);
        }
        foreach ($this->intervals as $name => $ms) {
            $out[$name] = \round($ms, 3);
        }

        return $out;
    }

    /** زمان سپری‌شده از مبدأ (ms). */
    public function elapsedMs(): float
    {
        return (\hrtime(true) - $this->originNs) / 1e6;
    }

    public function reset(): void
    {
        $this->originNs = \hrtime(true);
        $this->marks = [];
        $this->running = [];
        $this->intervals = [];
    }
}
