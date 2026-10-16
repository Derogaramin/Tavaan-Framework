<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Health;

/**
 * تَوان | Tavaan — Observability
 * HealthManager — ثبت و اجرای بررسی‌های liveness/readiness.
 *
 * خروجی هر بررسی (callable) نرمال می‌شود:
 * - bool → up/down
 * - `Up::class`/`Down::class` (رشته یا نمونه) → up/down (+ پیام)
 * - `CheckResult` → همان
 * - `array{status, message}` → همان
 * - Throwable → down با پیام خطا
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class HealthManager
{
    /** @var array<string, array{fn: callable, kind: string}> */
    private array $checks = [];

    /**
     * @param callable(): mixed $check
     * @param string            $kind  live|ready|both
     */
    public function add(string $name, callable $check, string $kind = 'both'): static
    {
        $kind = \in_array($kind, ['live', 'ready', 'both'], true) ? $kind : 'both';
        $this->checks[$name] = ['fn' => $check, 'kind' => $kind];

        return $this;
    }

    public function remove(string $name): void
    {
        unset($this->checks[$name]);
    }

    public function has(string $name): bool
    {
        return isset($this->checks[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return \array_keys($this->checks);
    }

    /**
     * اجرای بررسی‌ها.
     *
     * @param string $kind all|live|ready
     *
     * @return array{status: string, checks: list<array<string, mixed>>}
     */
    public function check(string $kind = 'all'): array
    {
        $results = [];
        $healthy = true;

        foreach ($this->checks as $name => $entry) {
            if ($kind !== 'all' && $entry['kind'] !== 'both' && $entry['kind'] !== $kind) {
                continue;
            }

            $result = $this->runCheck((string) $name, $entry['fn']);
            $results[] = $result->toArray();
            $healthy = $healthy && $result->isUp();
        }

        return [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $results,
        ];
    }

    /** Liveness — «فرایند زنده است» (حداقل بررسی‌ها). */
    public function live(): array
    {
        return $this->check('live');
    }

    /** Readiness — «آمادهٔ پذیرش ترافیک» (همهٔ وابستگی‌ها). */
    public function ready(): array
    {
        return $this->check('ready');
    }

    /** کد HTTP مناسب برای پاسخ health. */
    public static function statusCode(array $result): int
    {
        return ($result['status'] ?? 'unhealthy') === 'healthy' ? 200 : 503;
    }

    private function runCheck(string $name, callable $fn): CheckResult
    {
        $start = \hrtime(true);
        try {
            $outcome = $fn();
        } catch (\Throwable $e) {
            $ms = (\hrtime(true) - $start) / 1e6;

            return CheckResult::down($name, $ms, $e::class . ': ' . $e->getMessage());
        }
        $ms = (\hrtime(true) - $start) / 1e6;

        return $this->normalize($name, $outcome, $ms);
    }

    private function normalize(string $name, mixed $outcome, float $ms): CheckResult
    {
        if ($outcome instanceof CheckResult) {
            return $outcome;
        }
        if (\is_bool($outcome)) {
            return $outcome ? CheckResult::up($name, $ms) : CheckResult::down($name, $ms);
        }
        if ($outcome instanceof Up || $outcome === Up::class) {
            return CheckResult::up($name, $ms, $outcome instanceof Up ? $outcome->message : '');
        }
        if ($outcome instanceof Down || $outcome === Down::class) {
            return CheckResult::down($name, $ms, $outcome instanceof Down ? $outcome->message : '');
        }
        if (\is_array($outcome)) {
            $status = (string) ($outcome['status'] ?? 'down');
            $message = (string) ($outcome['message'] ?? '');

            return $status === 'up'
                ? CheckResult::up($name, $ms, $message)
                : CheckResult::down($name, $ms, $message);
        }
        if (\is_string($outcome)) {
            // رشته‌های کلاس‌مانند یا up/down
            $lower = \mb_strtolower($outcome);
            if ($lower === 'up' || $lower === 'ok' || $lower === 'healthy') {
                return CheckResult::up($name, $ms);
            }

            return CheckResult::down($name, $ms, $outcome);
        }

        // مقادیر truthy/falsy
        return $outcome ? CheckResult::up($name, $ms) : CheckResult::down($name, $ms);
    }
}
