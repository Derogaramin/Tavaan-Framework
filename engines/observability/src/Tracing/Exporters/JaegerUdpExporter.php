<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing\Exporters;

use Tavaan\Core\Contracts\Observability\SpanExporterInterface;
use Tavaan\Core\Contracts\Observability\SpanInterface;

/**
 * تَوان | Tavaan — Observability
 * JaegerUdpExporter — صدور Batch به Jaeger Agent (jaeger.thrift over UDP).
 *
 * خودنوشته با ThriftCompactWriter. ساخت Batch:
 * ```
 * emitBatch(1: Batch{1: Process{1: serviceName}, 2: list<Span>})
 * Span{1: traceIdLow i64, 2: traceIdHigh i64, 3: operationName,
 *      4: references, 5: startTime µs, 6: duration µs, 7: tags, 8: logs}
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class JaegerUdpExporter implements SpanExporterInterface
{
    /** TagType در jaeger.thrift */
    private const TAG_STRING = 0;
    private const TAG_DOUBLE = 1;
    private const TAG_BOOL = 2;
    private const TAG_LONG = 3;

    /** SpanRefType */
    private const REF_CHILD_OF = 0;

    /** @var list<SpanInterface> */
    private array $buffer = [];

    private ?string $lastError = null;

    private int $sent = 0;

    /**
     * @param \Closure(string, string, int): bool|null $sender تزریق حمل‌کننده (تست)
     */
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6831,
        private readonly string $service = 'tavaan',
        private readonly ?\Closure $sender = null,
    ) {
    }

    public function export(SpanInterface $span): void
    {
        $this->buffer[] = $span;
    }

    public function flush(): int
    {
        if ($this->buffer === []) {
            return 0;
        }

        $spans = $this->buffer;
        $this->buffer = [];
        $payload = $this->buildBatch($spans);

        if (!$this->send($payload)) {
            return 0;   // best-effort — شکست شبکه خطای برنامه نیست
        }

        $this->sent += \count($spans);

        return \count($spans);
    }

    public function name(): string
    {
        return 'jaeger';
    }

    public function sentCount(): int
    {
        return $this->sent;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * ساخت باینری Batch (عمومی برای تست).
     *
     * @param list<SpanInterface> $spans
     */
    public function buildBatch(array $spans): string
    {
        $w = new ThriftCompactWriter();
        $w->messageBegin('emitBatch');

        // args struct → field 1: Batch
        $w->structBegin();
        $w->writeStructFieldBegin(1);

        // Batch → field 1: Process
        $w->structBegin();
        $w->writeStructFieldBegin(1);
        $w->structBegin();
        $w->writeStringField(1, $this->service);
        $w->structEnd();   // Process

        // Batch → field 2: list<Span>
        $w->writeListFieldBegin(2, ThriftCompactWriter::TYPE_STRUCT, \count($spans));
        foreach ($spans as $span) {
            $this->writeSpan($w, $span);
        }
        $w->structEnd();   // Batch
        $w->structEnd();   // args

        return $w->buffer();
    }

    private function writeSpan(ThriftCompactWriter $w, SpanInterface $span): void
    {
        $data = $span->toArray();
        [$low, $high] = $this->splitTraceId((string) $data['trace_id']);
        $spanIdInt = $this->hexToInt64((string) $data['span_id']);

        $w->structBegin();
        $w->writeI64Field(1, $low);
        $w->writeI64Field(2, $high);
        $w->writeStringField(3, (string) $data['name']);

        // field 4: references (CHILD_OF به والد)
        $parent = $data['parent_span_id'] ?? null;
        if (\is_string($parent) && $parent !== '') {
            $w->writeListFieldBegin(4, ThriftCompactWriter::TYPE_STRUCT, 1);
            $w->structBegin();
            $w->writeI32Field(1, self::REF_CHILD_OF);
            $w->writeI64Field(2, $low);
            $w->writeI64Field(3, $high);
            $w->writeI64Field(4, $this->hexToInt64($parent));
            $w->structEnd();
        }

        $startMicros = (int) $data['start_micros'];
        $durationMicros = \max(1, (int) \round((float) $data['duration_ms'] * 1000));
        $w->writeI64Field(5, $startMicros);
        $w->writeI64Field(6, $durationMicros);

        // field 7: tags
        $tags = $this->buildTags((array) $data['attributes']);
        $w->writeListFieldBegin(7, ThriftCompactWriter::TYPE_STRUCT, \count($tags));
        foreach ($tags as [$key, $type, $value]) {
            $this->writeTag($w, (string) $key, (int) $type, $value);
        }

        // field 8: logs (از events)
        $events = (array) $data['events'];
        $w->writeListFieldBegin(8, ThriftCompactWriter::TYPE_STRUCT, \count($events));
        foreach ($events as $event) {
            $w->structBegin();
            $w->writeI64Field(1, (int) ($event['timeMicros'] ?? $startMicros));
            $fields = $this->buildTags(['event' => $event['name'] ?? '', ...(array) ($event['attributes'] ?? [])]);
            $w->writeListFieldBegin(2, ThriftCompactWriter::TYPE_STRUCT, \count($fields));
            foreach ($fields as [$key, $type, $value]) {
                $this->writeTag($w, (string) $key, (int) $type, $value);
            }
            $w->structEnd();
        }

        $w->structEnd();   // Span
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return list<array{0: string, 1: int, 2: mixed}>
     */
    private function buildTags(array $attributes): array
    {
        $tags = [];
        foreach ($attributes as $key => $value) {
            $key = (string) $key;
            if (\is_bool($value)) {
                $tags[] = [$key, self::TAG_BOOL, $value];
            } elseif (\is_int($value)) {
                $tags[] = [$key, self::TAG_LONG, $value];
            } elseif (\is_float($value)) {
                $tags[] = [$key, self::TAG_DOUBLE, $value];
            } else {
                $tags[] = [$key, self::TAG_STRING, \is_scalar($value) ? (string) $value
                    : (string) (\json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '')];
            }
        }

        return $tags;
    }

    private function writeTag(ThriftCompactWriter $w, string $key, int $type, mixed $value): void
    {
        $w->structBegin();
        $w->writeStringField(1, $key);
        $w->writeI32Field(2, $type);
        match ($type) {
            self::TAG_BOOL => $w->writeBoolField(5, (bool) $value),
            self::TAG_LONG => $w->writeI64Field(6, (int) $value),
            self::TAG_DOUBLE => $w->writeDoubleField(4, (float) $value),
            default => $w->writeStringField(3, (string) $value),
        };
        $w->structEnd();
    }

    /**
     * trace-id هگز ۳۲ → (low64, high64) به‌عنوان int64.
     *
     * @return array{0: int, 1: int}
     */
    private function splitTraceId(string $hex): array
    {
        $hex = \preg_replace('/[^0-9a-fA-F]/', '', $hex) ?: '';
        $hex = \str_pad(\substr($hex, -32), 32, '0', STR_PAD_LEFT);

        return [
            $this->hexToInt64(\substr($hex, 16)),
            $this->hexToInt64(\substr($hex, 0, 16)),
        ];
    }

    private function hexToInt64(string $hex): int
    {
        $hex = \str_pad(\substr(\preg_replace('/[^0-9a-fA-F]/', '', $hex) ?: '', -16), 16, '0', STR_PAD_LEFT);

        /** @var array{1: int} $unpacked */
        $unpacked = \unpack('J', (string) \hex2bin($hex));

        return (int) $unpacked[1];
    }

    private function send(string $payload): bool
    {
        $this->lastError = null;

        if ($this->sender !== null) {
            try {
                return (bool) ($this->sender)($payload, $this->host, $this->port);
            } catch (\Throwable $e) {
                $this->lastError = $e->getMessage();

                return false;
            }
        }

        $socket = @\stream_socket_client(
            'udp://' . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            1.0,
        );
        if ($socket === false) {
            $this->lastError = 'udp connect failed: ' . $errstr;

            return false;
        }

        $written = @\fwrite($socket, $payload);
        \fclose($socket);

        if ($written === false || $written < \strlen($payload)) {
            $this->lastError = 'udp write failed';

            return false;
        }

        return true;
    }
}
