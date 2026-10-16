<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing;

use Tavaan\Core\Contracts\Observability\SpanInterface;
use Tavaan\Core\Contracts\Observability\StatusCode;

/**
 * تَوان | Tavaan — Observability
 * NonRecordingSpan — Span بی‌اثر برای حالت غیرفعال/نمونه‌نگرفته.
 *
 * همهٔ جهش‌ها no-op هستند اما end() پشتهٔ Tracer را مرتب نگه می‌دارد
 * (تا والد/فرزندی حتی در حالت نمونه‌نگرفته درست بماند).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class NonRecordingSpan implements SpanInterface
{
    /** @param \Closure(SpanInterface): void|null $onEnd */
    public function __construct(
        private readonly string $name = '',
        private readonly string $traceId = '',
        private readonly string $spanId = '',
        private readonly ?string $parentSpanId = null,
        private readonly ?\Closure $onEnd = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function traceId(): string
    {
        return $this->traceId;
    }

    public function spanId(): string
    {
        return $this->spanId;
    }

    public function parentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function isRecording(): bool
    {
        return false;
    }

    public function isEnded(): bool
    {
        return false;
    }

    public function attributes(): array
    {
        return [];
    }

    public function setAttribute(string $key, mixed $value): static
    {
        return $this;
    }

    public function addEvent(string $name, array $attributes = []): static
    {
        return $this;
    }

    public function recordException(\Throwable $e): static
    {
        return $this;
    }

    public function setStatus(StatusCode $code, string $message = ''): static
    {
        return $this;
    }

    public function startMicros(): int
    {
        return 0;
    }

    public function durationMs(): float
    {
        return 0.0;
    }

    public function end(): void
    {
        if ($this->onEnd !== null) {
            ($this->onEnd)($this);
        }
    }

    public function toArray(): array
    {
        return [
            'service' => '',
            'name' => $this->name,
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
            'start_micros' => 0,
            'duration_ms' => 0.0,
            'attributes' => [],
            'events' => [],
            'status' => ['code' => StatusCode::Unset->value, 'message' => ''],
        ];
    }
}
