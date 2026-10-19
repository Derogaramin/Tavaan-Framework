<?php

declare(strict_types=1);

namespace Tavaan\Testing\Mock;

use Tavaan\Testing\AssertionFailed;

/**
 * تَوان | Tavaan — Testing
 * MockHandler — مغز یک Mock: تطبیق فراخوانی‌ها با انتظارات و verify پایانی.
 *
 * Proxy تولیدشده هر فراخوانی متد را به `call()` می‌سپارد؛ متدهای static از
 * طریق registry سراسری (`staticCall`) مسیریابی می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class MockHandler
{
    /** @var array<string, self> proxy class → handler (برای متدهای static) */
    private static array $staticRegistry = [];

    /** @var array<string, list<Expectation>> */
    private array $expectations = [];

    /** @var list<array{method: string, args: list<mixed>}> */
    private array $calls = [];

    // ─── registry ایستا ───────────────────────────────────────────────────

    public static function registerStatic(string $proxyClass, self $handler): void
    {
        self::$staticRegistry[$proxyClass] = $handler;
    }

    /** @param list<mixed> $args */
    public static function staticCall(string $proxyClass, string $method, array $args): mixed
    {
        $handler = self::$staticRegistry[$proxyClass] ?? null;
        if ($handler === null) {
            throw new \LogicException(\sprintf('No MockHandler registered for static proxy [%s].', $proxyClass));
        }

        return $handler->call($method, $args);
    }

    public static function resetStatics(): void
    {
        self::$staticRegistry = [];
    }

    // ─── انتظارات و فراخوانی‌ها ──────────────────────────────────────────

    public function expect(string $method, Expectation $expectation): void
    {
        $this->expectations[$method][] = $expectation;
    }

    /**
     * مسیریابی یک فراخوانی به اولین انتظارِ منطبقِ خسته‌نشده (ترتیب تعریف — قطعی).
     *
     * @param list<mixed> $args
     *
     * @throws AssertionFailed اگر انتظاری تعریف نشده / آرگومان‌ها نمنطبق / سقف پر شده
     */
    public function call(string $method, array $args): mixed
    {
        $this->calls[] = ['method' => $method, 'args' => $args];

        $candidates = $this->expectations[$method] ?? [];
        if ($candidates === []) {
            throw AssertionFailed::for(\sprintf(
                'Mock: unexpected call to [%s](%s) — no expectation was defined.',
                $method,
                self::exportArgs($args),
            ));
        }

        $argsMatched = false;
        foreach ($candidates as $expectation) {
            if (!$expectation->matchesArgs($args)) {
                continue;
            }
            $argsMatched = true;
            if (!$expectation->isExhausted()) {
                return $expectation->invoke($args);
            }
        }

        throw AssertionFailed::for($argsMatched
            ? \sprintf('Mock: [%s] was called more times than expected.', $method)
            : \sprintf(
                'Mock: [%s] called with unmatched arguments (%s).',
                $method,
                self::exportArgs($args),
            ));
    }

    /** بررسی برآورده‌شدن همهٔ انتظارات (پایان هر تست — توسط Mock::verifyAll). */
    public function verify(): void
    {
        $problems = [];
        foreach ($this->expectations as $expectations) {
            foreach ($expectations as $expectation) {
                try {
                    $expectation->verify();
                } catch (AssertionFailed $e) {
                    $problems[] = $e->getMessage();
                }
            }
        }

        if ($problems !== []) {
            throw AssertionFailed::for('Mock expectations not met:' . \PHP_EOL . '  - ' . \implode(\PHP_EOL . '  - ', $problems));
        }
    }

    /**
     * فراخوانی‌های ثبت‌شده (برای بازرسی دستی در تست).
     *
     * @return list<array{method: string, args: list<mixed>}>
     */
    public function calls(?string $method = null): array
    {
        if ($method === null) {
            return $this->calls;
        }

        return \array_values(\array_filter(
            $this->calls,
            static fn (array $call): bool => $call['method'] === $method,
        ));
    }

    /** @param list<mixed> $args */
    private static function exportArgs(array $args): string
    {
        $parts = [];
        foreach ($args as $arg) {
            $parts[] = match (true) {
                \is_string($arg) => "'" . (\strlen($arg) > 40 ? \substr($arg, 0, 40) . '…' : $arg) . "'",
                \is_bool($arg) => $arg ? 'true' : 'false',
                $arg === null => 'null',
                \is_array($arg) => 'array(' . \count($arg) . ')',
                \is_object($arg) => $arg::class,
                \is_scalar($arg) => (string) $arg,
                default => \get_debug_type($arg),
            };
        }

        return \implode(', ', $parts);
    }
}
