<?php

declare(strict_types=1);

namespace Tavaan\Testing\Mock;

use Tavaan\Testing\AssertionFailed;

/**
 * تَوان | Tavaan — Testing
 * Expectation — یک انتظار Mock: تطبیق آرگومان، مقدار بازگشتی و شمار فراخوانی.
 *
 * زنجیرهٔ روان طبق DOC-084E §4:
 * ```php
 * Mock::of(StoreInterface::class)
 *     ->shouldReceive('get')->with('key')->andReturn('value')
 *     ->shouldReceive('put')->once()
 *     ->getMock();
 * ```
 * متدهای `shouldReceive()` و `getMock()` به Builder forwards می‌شوند تا
 * زنجیرهٔ بالا ممکن شود.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class Expectation
{
    /** @var list<mixed>|null null = هر آرگومانی قبول است */
    private ?array $expectedArgs = null;

    /** @var list<mixed> صف مقادیر بازگشتی (آخرین مقدار تکرار می‌شود) */
    private array $returnValues = [];

    private ?\Closure $returnCallback = null;

    private ?\Throwable $throwable = null;

    private ?int $minCalls = null;

    private ?int $maxCalls = null;

    private int $callCount = 0;

    public function __construct(
        private readonly string $method,
        private readonly ?MockBuilder $builder = null,
    ) {
    }

    // ─── تعریف انتظار ─────────────────────────────────────────────────────

    public function with(mixed ...$args): self
    {
        $this->expectedArgs = $args;

        return $this;
    }

    public function andReturn(mixed ...$values): self
    {
        foreach ($values as $value) {
            $this->returnValues[] = $value;
        }

        return $this;
    }

    public function andReturnUsing(callable $callback): self
    {
        $this->returnCallback = $callback instanceof \Closure ? $callback : \Closure::fromCallable($callback);

        return $this;
    }

    public function andThrow(\Throwable $throwable): self
    {
        $this->throwable = $throwable;

        return $this;
    }

    /** دقیقاً یک بار. */
    public function once(): self
    {
        return $this->times(1);
    }

    /** دقیقاً دو بار. */
    public function twice(): self
    {
        return $this->times(2);
    }

    /** دقیقاً n بار. */
    public function times(int $count): self
    {
        $this->minCalls = \max(0, $count);
        $this->maxCalls = \max(0, $count);

        return $this;
    }

    /** هرگز (فراخوانی = شکست). */
    public function never(): self
    {
        $this->minCalls = 0;
        $this->maxCalls = 0;

        return $this;
    }

    /** بدون محدودیت شمار (پیش‌فرض). */
    public function anyTimes(): self
    {
        $this->minCalls = null;
        $this->maxCalls = null;

        return $this;
    }

    // ─── forwards به Builder (زنجیرهٔ DOC-084E §4) ────────────────────────

    public function shouldReceive(string $method): self
    {
        return $this->builderOrFail()->shouldReceive($method);
    }

    public function getMock(): object
    {
        return $this->builderOrFail()->getMock();
    }

    // ─── internals (توسط MockHandler استفاده می‌شود) ──────────────────────

    /** @param list<mixed> $args */
    public function matchesArgs(array $args): bool
    {
        if ($this->expectedArgs === null) {
            return true;
        }

        if (\count($args) !== \count($this->expectedArgs)) {
            return false;
        }

        foreach ($this->expectedArgs as $index => $expected) {
            if (($args[$index] ?? null) !== $expected) {
                return false;
            }
        }

        return true;
    }

    public function isExhausted(): bool
    {
        return $this->maxCalls !== null && $this->callCount >= $this->maxCalls;
    }

    /** @param list<mixed> $args */
    public function invoke(array $args): mixed
    {
        $this->callCount++;

        if ($this->throwable !== null) {
            throw $this->throwable;
        }

        if ($this->returnCallback !== null) {
            return ($this->returnCallback)(...$args);
        }

        if ($this->returnValues === []) {
            return null;
        }

        return $this->returnValues[\min($this->callCount - 1, \count($this->returnValues) - 1)];
    }

    /** بررسی برآورده‌شدن انتظار در پایان تست (توسط Mock::verifyAll). */
    public function verify(): void
    {
        if ($this->minCalls !== null && $this->callCount < $this->minCalls) {
            throw AssertionFailed::for(\sprintf(
                'Mock: [%s] expected %d call(s) but received %d.',
                $this->method,
                $this->minCalls,
                $this->callCount,
            ));
        }
    }

    public function method(): string
    {
        return $this->method;
    }

    public function callCount(): int
    {
        return $this->callCount;
    }

    private function builderOrFail(): MockBuilder
    {
        if ($this->builder === null) {
            throw new \LogicException('Expectation is not attached to a MockBuilder.');
        }

        return $this->builder;
    }
}
