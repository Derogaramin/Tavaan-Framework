<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing;

use Tavaan\Core\Context\TraceContext;
use Tavaan\Core\Contracts\Log\LoggerInterface;
use Tavaan\Core\Contracts\Observability\SpanExporterInterface;
use Tavaan\Core\Contracts\Observability\SpanInterface;
use Tavaan\Core\Contracts\Observability\StatusCode;
use Tavaan\Engine\Observability\Exceptions\ObservabilityException;
use Tavaan\Engine\Observability\Tracing\Exporters\ArrayExporter;
use Tavaan\Engine\Observability\Tracing\Exporters\JaegerUdpExporter;
use Tavaan\Engine\Observability\Tracing\Exporters\LogExporter;
use Tavaan\Engine\Observability\Tracing\Exporters\OtlpHttpExporter;

/**
 * تَوان | Tavaan — Observability
 * Tracer — ساخت Span با والد خودکار، Sampling و صدور به Exporterها.
 *
 * - trace-id از TraceContext هسته (traceparent ورودی یا تصادفی)
 * - نمونه‌گیری قطعی در ریشه (RateSampler بر پایه trace-id)؛ فرزندان به ارث می‌برند
 * - Span پایان‌یافته → exporter->export (معمولاً buffer + flush)
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class Tracer
{
    /** @var list<SpanInterface> پشتهٔ Spanهای باز */
    private array $stack = [];

    /** @var list<SpanExporterInterface> */
    private array $exporters = [];

    private bool $enabled;

    private float $sampleRate;

    private string $service;

    /**
     * @param array<string, mixed> $config کلیدهای tracing در config/observability.php
     */
    public function __construct(array $config = [], ?LoggerInterface $logger = null)
    {
        $this->enabled = (bool) ($config['enabled'] ?? false);
        $this->sampleRate = \max(0.0, \min(1.0, (float) ($config['sample_rate'] ?? 1.0)));
        $this->service = (string) ($config['service'] ?? 'tavaan');

        if ($this->enabled) {
            $exporter = (string) ($config['exporter'] ?? 'log');
            if ($exporter !== 'none') {
                $built = $this->buildExporter($exporter, $config, $logger);
                if ($built !== null) {
                    $this->exporters[] = $built;
                }
            }
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function sampleRate(): float
    {
        return $this->sampleRate;
    }

    public function service(): string
    {
        return $this->service;
    }

    public function addExporter(SpanExporterInterface $exporter): void
    {
        $this->exporters[] = $exporter;
    }

    /** @return list<SpanExporterInterface> */
    public function exporters(): array
    {
        return $this->exporters;
    }

    /**
     * شروع Span جدید (فرزند Span جاری در همان trace).
     *
     * @param array<string, mixed> $attributes
     */
    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $traceId = TraceContext::traceId();
        $parent = $this->currentSpan();

        if (!$this->enabled) {
            $span = new NonRecordingSpan($name, $traceId, $this->newSpanId(), $parent?->spanId() ?: null, $this->onEnd(...));
            $this->stack[] = $span;

            return $span;
        }

        $recording = $parent !== null
            ? $parent->isRecording()                                       // parent-based
            : RateSampler::shouldSample($traceId, $this->sampleRate);      // ریشه: قطعی

        $spanId = $this->newSpanId();

        if (!$recording) {
            $span = new NonRecordingSpan($name, $traceId, $spanId, $parent?->spanId() ?: null, $this->onEnd(...));
            $this->stack[] = $span;

            return $span;
        }

        $span = new Span(
            name: $name,
            traceId: $traceId,
            spanId: $spanId,
            parentSpanId: $parent?->spanId() ?: null,
            onEnd: $this->onEnd(...),
            attributes: $attributes,
            service: $this->service,
        );
        $this->stack[] = $span;

        return $span;
    }

    public function currentSpan(): ?SpanInterface
    {
        return $this->stack === [] ? null : $this->stack[\count($this->stack) - 1];
    }

    public function depth(): int
    {
        return \count($this->stack);
    }

    /**
     * اجرای callable داخل یک Span — با ثبت خودکار exception و پایان قطعی.
     *
     * @template T
     * @param array<string, mixed> $attributes
     * @param callable(SpanInterface): T $fn
     * @return T
     */
    public function withSpan(string $name, array $attributes, callable $fn): mixed
    {
        $span = $this->startSpan($name, $attributes);
        try {
            $result = $fn($span);
            $span->setStatus(StatusCode::Ok);

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::Error, $e->getMessage());

            throw $e;
        } finally {
            $span->end();
        }
    }

    /** صدور همهٔ Spanهای bufferشده در exporterها. */
    public function flush(): int
    {
        $total = 0;
        foreach ($this->exporters as $exporter) {
            $total += $exporter->flush();
        }

        return $total;
    }

    private function onEnd(SpanInterface $span): void
    {
        // حذف از پشته (حتی اگر Span میانی بی‌ترتیب end شده باشد)
        for ($i = \count($this->stack) - 1; $i >= 0; $i--) {
            if ($this->stack[$i] === $span) {
                \array_splice($this->stack, $i, 1);

                break;
            }
        }

        if ($span->isRecording()) {
            foreach ($this->exporters as $exporter) {
                $exporter->export($span);
            }
        }
    }

    private function newSpanId(): string
    {
        return \bin2hex(\random_bytes(8));
    }

    /** @param array<string, mixed> $config */
    private function buildExporter(string $name, array $config, ?LoggerInterface $logger): ?SpanExporterInterface
    {
        return match ($name) {
            'array' => new ArrayExporter(),
            'log' => $logger !== null ? new LogExporter($logger) : null,
            'otlp', 'otlp_http' => new OtlpHttpExporter((string) ($config['otlp_endpoint'] ?? '')),
            'jaeger', 'jaeger_udp' => new JaegerUdpExporter(
                (string) ($config['jaeger_host'] ?? '127.0.0.1'),
                (int) ($config['jaeger_port'] ?? 6831),
                $this->service,
            ),
            default => throw ObservabilityException::unknownExporter($name),
        };
    }
}
