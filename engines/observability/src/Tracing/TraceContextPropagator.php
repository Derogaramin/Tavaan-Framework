<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing;

use Tavaan\Core\Context\TraceContext;

/**
 * تَوان | Tavaan — Observability
 * TraceContextPropagator — پارس/تزریق هدر W3C TraceContext (traceparent).
 *
 * ```
 * traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
 *              │  └─ trace-id (32 hex)              └─ span-id (16) └─ flags
 *              └─ version
 * ```
 *
 * 🔒 شناسه‌های تمام‌صفر نامعتبرند (طبق W3C).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class TraceContextPropagator
{
    private const PATTERN = '/^([0-9a-f]{2})-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/';

    private function __construct(
        public readonly string $version,
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly int $flags,
    ) {
    }

    /** پارس هدر traceparent — در صورت نامعتبر بودن null. */
    public static function fromHeader(?string $header): ?self
    {
        if ($header === null || \trim($header) === '') {
            return null;
        }
        if (\preg_match(self::PATTERN, \trim($header), $m) !== 1) {
            return null;
        }
        // 🔒 W3C: trace-id و parent-id تمام‌صفر نامعتبرند
        if ($m[2] === \str_repeat('0', 32) || $m[3] === \str_repeat('0', 16)) {
            return null;
        }

        return new self($m[1], $m[2], $m[3], \hexdec($m[4]));
    }

    public function sampled(): bool
    {
        return ($this->flags & 0x01) === 1;
    }

    public function traceId(): string
    {
        return $this->traceId;
    }

    public function spanId(): string
    {
        return $this->spanId;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function flags(): int
    {
        return $this->flags;
    }

    /** هدر traceparent برای درخواست/پاسخ جاری (از TraceContext هسته). */
    public static function header(): string
    {
        return \sprintf('00-%s-%s-01', TraceContext::traceId(), TraceContext::spanId());
    }

    /**
     * تزریق در هدرهای درخواست خروجی (http-client/queue).
     *
     * @param array<string, string> $headers
     */
    public static function inject(array &$headers): void
    {
        $headers['traceparent'] = self::header();
    }

    public function newSpanId(): string
    {
        return \bin2hex(\random_bytes(8));
    }
}
