<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing;

use Tavaan\Core\Contracts\Observability\SpanInterface;
use Tavaan\Core\Contracts\Observability\StatusCode;

/**
 * تَوان | Tavaan — Observability
 * Span — یک بازهٔ کاری ردیابی‌شده (نام، زمان، attributes، events، status).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Span implements SpanInterface
{
    private int $startMicros;

    private int $startNs;

    private ?int $endNs = null;

    private bool $ended = false;

    /** @var array<string, mixed> */
    private array $attributes;

    /** @var list<array{name: string, timeMicros: int, attributes: array<string, mixed>}> */
    private array $events = [];

    private StatusCode $status = StatusCode::Unset;

    private string $statusMessage = '';

    /**
     * @param array<string, mixed> $attributes
     * @param \Closure(Span): void|null $onEnd
     */
    public function __construct(
        private readonly string $name,
        private readonly string $traceId,
        private readonly string $spanId,
        private readonly ?string $parentSpanId = null,
        private readonly ?\Closure $onEnd = null,
        array $attributes = [],
        private readonly string $service = 'tavaan',
    ) {
        $this->attributes = $attributes;
        $this->startNs = \hrtime(true);
        $this->startMicros = (int) \floor(\microtime(true) * 1_000_000);
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
        return true;
    }

    public function isEnded(): bool
    {
        return $this->ended;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        if (!$this->ended) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    public function addEvent(string $name, array $attributes = []): static
    {
        if (!$this->ended) {
            $this->events[] = [
                'name' => $name,
                'timeMicros' => (int) \floor(\microtime(true) * 1_000_000),
                'attributes' => $attributes,
            ];
        }

        return $this;
    }

    public function recordException(\Throwable $e): static
    {
        return $this->addEvent('exception', [
            'exception.type' => $e::class,
            'exception.message' => $e->getMessage(),
            'exception.file' => $e->getFile() . ':' . $e->getLine(),
        ]);
    }

    public function setStatus(StatusCode $code, string $message = ''): static
    {
        if (!$this->ended) {
            // Ok پس از Error نباید پایین‌بیاید
            if (!($this->status === StatusCode::Error && $code === StatusCode::Ok)) {
                $this->status = $code;
                $this->statusMessage = $message;
            }
        }

        return $this;
    }

    public function status(): StatusCode
    {
        return $this->status;
    }

    public function statusMessage(): string
    {
        return $this->statusMessage;
    }

    public function startMicros(): int
    {
        return $this->startMicros;
    }

    public function durationMs(): float
    {
        $end = $this->endNs ?? \hrtime(true);

        return ($end - $this->startNs) / 1e6;
    }

    public function durationMicros(): int
    {
        $end = $this->endNs ?? \hrtime(true);

        return (int) \floor(($end - $this->startNs) / 1000);
    }

    /** @return list<array{name: string, timeMicros: int, attributes: array<string, mixed>}> */
    public function events(): array
    {
        return $this->events;
    }

    public function end(): void
    {
        if ($this->ended) {
            return;   // 🔒 end چندباره بی‌اثر (از double-export جلوگیری می‌کند)
        }
        $this->ended = true;
        $this->endNs = \hrtime(true);

        if ($this->onEnd !== null) {
            ($this->onEnd)($this);
        }
    }

    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'name' => $this->name,
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
            'start_micros' => $this->startMicros,
            'duration_ms' => \round($this->durationMs(), 3),
            'attributes' => $this->attributes,
            'events' => $this->events,
            'status' => ['code' => $this->status->value, 'message' => $this->statusMessage],
        ];
    }
}
