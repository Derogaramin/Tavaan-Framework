<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Observability;

/**
 * تَوان | Tavaan — Contracts
 * SpanInterface — قرارداد یک Span در ردیابی توزیع‌شده (DOC-067 §3).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
interface SpanInterface
{
    public function name(): string;

    public function traceId(): string;

    public function spanId(): string;

    public function parentSpanId(): ?string;

    /** آیا این Span واقعاً ضبط/صادر می‌شود (Sampling)؟ */
    public function isRecording(): bool;

    public function isEnded(): bool;

    /** @return array<string, mixed> */
    public function attributes(): array;

    public function setAttribute(string $key, mixed $value): static;

    /** @param array<string, mixed> $attributes */
    public function addEvent(string $name, array $attributes = []): static;

    public function recordException(\Throwable $e): static;

    public function setStatus(StatusCode $code, string $message = ''): static;

    /** زمان شروع (میکروثانیهٔ یونیکس). */
    public function startMicros(): int;

    /** مدت‌زمان تاکنون/تا پایان (میلی‌ثانیه). */
    public function durationMs(): float;

    public function end(): void;

    /** @return array<string, mixed> نمایش قابل‌صدر (OTLP/Log/Jaeger) */
    public function toArray(): array;
}
