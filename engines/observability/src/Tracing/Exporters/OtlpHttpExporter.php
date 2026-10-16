<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing\Exporters;

use Tavaan\Core\Contracts\Observability\SpanExporterInterface;
use Tavaan\Core\Contracts\Observability\SpanInterface;

/**
 * تَوان | Tavaan — Observability
 * OtlpHttpExporter — صدور OTLP/HTTP JSON ( Jaeger/Tempo/Zipkin مدرن).
 *
 * خودنوشته و صفر‌وابستگی: POST با stream context (بدون curl).
 * ساختار payload مطابق proto OTLP (نمایش JSON):
 * resourceSpans → scopeSpans → spans.
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class OtlpHttpExporter implements SpanExporterInterface
{
    /** @var list<SpanInterface> */
    private array $buffer = [];

    private int $sent = 0;

    private ?string $lastError = null;

    /**
     * @param string        $endpoint  پایه (مثلاً http://localhost:4318) یا مسیر کامل
     * @param \Closure(string, string): bool|null $transport تزریقِ حمل‌کننده (تست)
     */
    public function __construct(
        private readonly string $endpoint = '',
        private readonly string $service = 'tavaan',
        private readonly int $batchSize = 256,
        private readonly float $timeout = 2.0,
        private readonly ?\Closure $transport = null,
    ) {
    }

    public function export(SpanInterface $span): void
    {
        $this->buffer[] = $span;
        if (\count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function flush(): int
    {
        if ($this->buffer === []) {
            return 0;
        }

        $payload = $this->payload($this->buffer);
        $json = \json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
        $count = \count($this->buffer);
        $this->buffer = [];

        if ($this->endpoint === '' && $this->transport === null) {
            $this->lastError = 'no endpoint configured';

            return 0;
        }

        $ok = $this->send($json);
        if ($ok) {
            $this->sent += $count;
        } else {
            // 🔒 شکست export نباید برنامه را بیندازد — spanها دور ریخته می‌شوند (best-effort)
            return 0;
        }

        return $count;
    }

    public function name(): string
    {
        return 'otlp';
    }

    public function sentCount(): int
    {
        return $this->sent;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /** @return list<SpanInterface> */
    public function pending(): array
    {
        return $this->buffer;
    }

    /**
     * ساخت payload کامل OTLP (برای تست/اشکال‌زدایی عمومی است).
     *
     * @param list<SpanInterface> $spans
     *
     * @return array<string, mixed>
     */
    public function payload(array $spans): array
    {
        $byService = [];
        foreach ($spans as $span) {
            $data = $span->toArray();
            $service = (string) ($data['service'] ?? $this->service);
            if ($service === '') {
                $service = $this->service;
            }
            $byService[$service][] = $this->spanToOtlp($span, $data);
        }

        $resourceSpans = [];
        foreach ($byService as $service => $serviceSpans) {
            $resourceSpans[] = [
                'resource' => [
                    'attributes' => [['key' => 'service.name', 'value' => ['stringValue' => (string) $service]]],
                ],
                'scopeSpans' => [[
                    'scope' => ['name' => 'tavaan.observability', 'version' => '1.0.0'],
                    'spans' => $serviceSpans,
                ]],
            ];
        }

        return ['resourceSpans' => $resourceSpans];
    }

    /**
     * @param array<string, mixed> $data خروجی Span::toArray
     *
     * @return array<string, mixed>
     */
    private function spanToOtlp(SpanInterface $span, array $data): array
    {
        $startNs = (int) $data['start_micros'] * 1000;
        $durationNs = (int) \round((float) $data['duration_ms'] * 1e6);

        $attributes = [];
        foreach ((array) $data['attributes'] as $k => $v) {
            $attributes[] = ['key' => (string) $k, 'value' => $this->anyValue($v)];
        }

        $events = [];
        foreach ((array) $data['events'] as $event) {
            $eventAttrs = [];
            foreach ((array) ($event['attributes'] ?? []) as $k => $v) {
                $eventAttrs[] = ['key' => (string) $k, 'value' => $this->anyValue($v)];
            }
            $events[] = [
                'timeUnixNano' => (string) ((int) ($event['timeMicros'] ?? 0) * 1000),
                'name' => (string) ($event['name'] ?? ''),
                'attributes' => $eventAttrs,
            ];
        }

        $out = [
            'traceId' => (string) $data['trace_id'],
            'spanId' => (string) $data['span_id'],
            'name' => (string) $data['name'],
            'kind' => 1,   // SPAN_KIND_INTERNAL
            'startTimeUnixNano' => (string) $startNs,
            'endTimeUnixNano' => (string) ($startNs + \max(0, $durationNs)),
            'attributes' => $attributes,
            'events' => $events,
            'status' => ['code' => (int) ($data['status']['code'] ?? 0)],
        ];
        if (($data['status']['message'] ?? '') !== '') {
            $out['status']['message'] = (string) $data['status']['message'];
        }
        if (($data['parent_span_id'] ?? null) !== null) {
            $out['parentSpanId'] = (string) $data['parent_span_id'];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function anyValue(mixed $v): array
    {
        return match (true) {
            \is_bool($v) => ['boolValue' => $v],
            \is_int($v) => ['intValue' => (string) $v],
            \is_float($v) => ['doubleValue' => $v],
            \is_array($v) => ['stringValue' => \json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]'],
            $v === null => ['stringValue' => ''],
            default => ['stringValue' => (string) $v],
        };
    }

    private function url(): string
    {
        $endpoint = \rtrim($this->endpoint, '/');
        if (\str_ends_with($endpoint, '/v1/traces')) {
            return $endpoint;
        }

        return $endpoint . '/v1/traces';
    }

    private function send(string $json): bool
    {
        $url = $this->url();
        $this->lastError = null;

        if ($this->transport !== null) {
            try {
                return (bool) ($this->transport)($url, $json);
            } catch (\Throwable $e) {
                $this->lastError = $e->getMessage();

                return false;
            }
        }

        $context = \stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $json,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $result = @\file_get_contents($url, false, $context);
        if ($result === false) {
            $this->lastError = 'transport error to ' . $url;

            return false;
        }

        return true;
    }
}
