<?php

declare(strict_types=1);

namespace Tavaan\Testing;

/**
 * تَوان | Tavaan — Testing
 * TestResult — جمع‌بندی اجرای تست‌ها (شمارنده‌ها + شکست‌ها + زمان).
 *
 * قابل ادغام (`merge`) برای اجرای موازی و قابل سریال‌شدن (`toArray/fromArray`)
 * برای انتقال بین فرایند والد/فرزند (DOC-084E §7).
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class TestResult
{
    private int $passed = 0;

    private int $failed = 0;

    private int $errored = 0;

    private int $assertions = 0;

    private float $durationMs = 0.0;

    /** @var list<TestFailure> */
    private array $failures = [];

    public function recordPassed(int $assertions, float $durationMs = 0.0): void
    {
        $this->passed++;
        $this->assertions += $assertions;
        $this->durationMs += $durationMs;
    }

    public function recordFailure(string $test, AssertionFailed $error, float $durationMs = 0.0): void
    {
        $this->failed++;
        $this->durationMs += $durationMs;
        $this->failures[] = new TestFailure(
            test: $test,
            message: $error->getMessage(),
            location: $error->location(),
            kind: 'failure',
            exception: $error::class,
        );
    }

    public function recordError(string $test, \Throwable $error, float $durationMs = 0.0): void
    {
        $this->errored++;
        $this->durationMs += $durationMs;
        $this->failures[] = new TestFailure(
            test: $test,
            message: $error->getMessage(),
            location: $error->getFile() . ':' . $error->getLine(),
            kind: 'error',
            exception: $error::class,
        );
    }

    public function merge(self $other): void
    {
        $this->passed += $other->passed;
        $this->failed += $other->failed;
        $this->errored += $other->errored;
        $this->assertions += $other->assertions;
        $this->durationMs += $other->durationMs;
        foreach ($other->failures as $failure) {
            $this->failures[] = $failure;
        }
    }

    public function passed(): int
    {
        return $this->passed;
    }

    public function failed(): int
    {
        return $this->failed;
    }

    public function errored(): int
    {
        return $this->errored;
    }

    public function assertions(): int
    {
        return $this->assertions;
    }

    public function durationMs(): float
    {
        return $this->durationMs;
    }

    public function total(): int
    {
        return $this->passed + $this->failed + $this->errored;
    }

    /** @return list<TestFailure> */
    public function failures(): array
    {
        return $this->failures;
    }

    public function ok(): bool
    {
        return $this->failed === 0 && $this->errored === 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'failed' => $this->failed,
            'errored' => $this->errored,
            'assertions' => $this->assertions,
            'duration_ms' => $this->durationMs,
            'failures' => \array_map(static fn (TestFailure $f): array => $f->toArray(), $this->failures),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $result = new self();
        $result->passed = (int) ($data['passed'] ?? 0);
        $result->failed = (int) ($data['failed'] ?? 0);
        $result->errored = (int) ($data['errored'] ?? 0);
        $result->assertions = (int) ($data['assertions'] ?? 0);
        $result->durationMs = (float) ($data['duration_ms'] ?? 0.0);
        foreach ((array) ($data['failures'] ?? []) as $failure) {
            $result->failures[] = TestFailure::fromArray((array) $failure);
        }

        return $result;
    }
}
