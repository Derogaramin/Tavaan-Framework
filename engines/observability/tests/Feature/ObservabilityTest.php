<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tests\Feature;

require_once __DIR__ . '/../Support/ThriftCompactReader.php';

use Tavaan\Core\Context\TraceContext;
use Tavaan\Core\Contracts\Observability\StatusCode;
use Tavaan\Engine\Http\HttpKernel;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;
use Tavaan\Engine\Observability\DebugBar\DebugBar;
use Tavaan\Engine\Observability\DebugBar\DebugBarMiddleware;
use Tavaan\Engine\Observability\DebugBar\Stopwatch;
use Tavaan\Engine\Observability\Exceptions\ObservabilityException;
use Tavaan\Engine\Observability\Health\CheckResult;
use Tavaan\Engine\Observability\Health\Down;
use Tavaan\Engine\Observability\Health\HealthManager;
use Tavaan\Engine\Observability\Health\Up;
use Tavaan\Engine\Observability\Metrics\Histogram;
use Tavaan\Engine\Observability\Metrics\MetricsRegistry;
use Tavaan\Engine\Observability\Tests\Support\ThriftCompactReader;
use Tavaan\Engine\Observability\Tracing\Exporters\ArrayExporter;
use Tavaan\Engine\Observability\Tracing\Exporters\JaegerUdpExporter;
use Tavaan\Engine\Observability\Tracing\Exporters\OtlpHttpExporter;
use Tavaan\Engine\Observability\Tracing\RateSampler;
use Tavaan\Engine\Observability\Tracing\Span;
use Tavaan\Engine\Observability\Tracing\TraceContextPropagator;
use Tavaan\Engine\Observability\Tracing\Tracer;
use Tavaan\Testing\TestCase;

/**
 * تست موتور Observability — DOC-067 §8 (T-01..T-07).
 */
final class ObservabilityTest extends TestCase
{
    private \Tavaan\Core\Application\Application $app;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('log.default', 'array');
        TraceContext::reset();
    }

    protected function tearDown(): void
    {
        TraceContext::reset();
    }

    // ─── T-01: Counter/Gauge/Histogram ────────────────────────────────────

    public function testCounterIncrementsPerLabelSet(): void
    {
        $m = new MetricsRegistry();
        $c = $m->counter('requests', 'Total requests');
        $c->increment(1, ['route' => 'a']);
        $c->increment(2, ['route' => 'a']);
        $c->increment(5, ['route' => 'b']);

        $this->assertSame(3.0, $c->value(['route' => 'a']));
        $this->assertSame(5.0, $c->value(['route' => 'b']));
        $this->assertSame(0.0, $c->value(['route' => 'missing']));
    }

    public function testCounterRejectsNegativeIncrement(): void
    {
        $m = new MetricsRegistry();
        $this->expectException(ObservabilityException::class);
        $this->expectExceptionMessageId('observability.negative_increment');
        $m->counter('bad')->increment(-1);
    }

    public function testGaugeSetIncrementDecrement(): void
    {
        $m = new MetricsRegistry();
        $g = $m->gauge('queue_size', 42, ['queue' => 'default']);
        $this->assertSame(42.0, $g->value(['queue' => 'default']));

        $g->increment(8, ['queue' => 'default']);
        $this->assertSame(50.0, $g->value(['queue' => 'default']));

        $g->decrement(50, ['queue' => 'default']);
        $this->assertSame(0.0, $g->value(['queue' => 'default']));
    }

    public function testHistogramTracksBucketsSumCount(): void
    {
        $m = new MetricsRegistry();
        $h = $m->histogram('duration', [0.1, 0.5, 1.0]);
        $h->observe(0.05);
        $h->observe(0.3);
        $h->observe(2.0);

        $this->assertSame(3, $h->count());
        $this->assertEqualsWithDelta(2.35, $h->sum(), 0.0001);

        $sample = \array_values($h->samples())[0];
        // سطل‌ها غیرتجمعی: 0.05→bucket0، 0.3→bucket1، 2.0→هیچ‌کدام
        $this->assertSame([1, 1, 0], $sample['counts']);
    }

    public function testSummaryComputesQuantiles(): void
    {
        $m = new MetricsRegistry();
        $s = $m->summary('latency', [0.5, 0.9]);
        foreach ([0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0] as $v) {
            $s->observe($v);
        }

        $this->assertSame(10, $s->count());
        $this->assertEqualsWithDelta(0.5, (float) $s->quantile(0.5), 0.0001);
        $this->assertEqualsWithDelta(0.9, (float) $s->quantile(0.9), 0.0001);
        $this->assertNull($s->quantile(0.5, ['unknown' => 'x']));
    }

    public function testTimerRecordsDurationAndReturnsValue(): void
    {
        $m = new MetricsRegistry();
        $result = $m->timer('db.query', static fn (): string => 'done', ['op' => 'select']);

        $this->assertSame('done', $result);
        $h = $m->histogram('db.query');
        $this->assertSame(1, $h->count(['op' => 'select']));
        $this->assertTrue($h->sum(['op' => 'select']) >= 0.0);
    }

    // ─── T-02: Prometheus format ─────────────────────────────────────────

    public function testPrometheusTextFormat(): void
    {
        $m = new MetricsRegistry(null, ['prefix' => 'tavaan']);
        $m->counter('http_requests_total', 'Total HTTP requests')->increment(1523, ['route' => 'users.index', 'status' => '200']);
        $m->gauge('queue_size', 42, ['queue' => 'default']);

        $out = $m->collect();

        $this->assertStringContainsString('# HELP tavaan_http_requests_total Total HTTP requests', $out);
        $this->assertStringContainsString('# TYPE tavaan_http_requests_total counter', $out);
        $this->assertStringContainsString('tavaan_http_requests_total{route="users.index",status="200"} 1523', $out);
        $this->assertStringContainsString('# TYPE tavaan_queue_size gauge', $out);
        $this->assertStringContainsString('tavaan_queue_size{queue="default"} 42', $out);
    }

    public function testPrometheusHistogramCumulativeBuckets(): void
    {
        $m = new MetricsRegistry(null, ['prefix' => 'app']);
        $h = $m->histogram('http_request_duration_seconds', [0.01, 0.1]);
        $h->observe(0.005);
        $h->observe(0.034);
        $h->observe(0.5);

        $out = $m->collect();
        // تجمعی: le=0.01 → 1؛ le=0.1 → 2؛ +Inf → 3
        $this->assertStringContainsString('app_http_request_duration_seconds_bucket{le="0.01"} 1', $out);
        $this->assertStringContainsString('app_http_request_duration_seconds_bucket{le="0.1"} 2', $out);
        $this->assertStringContainsString('app_http_request_duration_seconds_bucket{le="+Inf"} 3', $out);
        $this->assertStringContainsString('app_http_request_duration_seconds_count 3', $out);
        $this->assertStringContainsString('app_http_request_duration_seconds_sum 0.539', $out);
    }

    public function testPrometheusEscapesLabelValues(): void
    {
        $m = new MetricsRegistry(null, ['prefix' => 'x']);
        $m->counter('c')->increment(1, ['msg' => 'quote" back\\slash newline' . "\n" . 'end']);

        $out = $m->collect();
        $this->assertStringContainsString('msg="quote\\" back\\\\slash newline\\nend"', $out);
    }

    public function testMetricNameSanitizedAndPrefixNotDuplicated(): void
    {
        $m = new MetricsRegistry(null, ['prefix' => 'tavaan']);
        $m->counter('tavaan_http.requests-total')->increment(1);

        $out = $m->collect();
        $this->assertStringContainsString('tavaan_http_requests_total 1', $out);
        $this->assertFalse(\str_contains($out, 'tavaan_tavaan'));
    }

    public function testMetricsPersistThroughCache(): void
    {
        /** @var \Tavaan\Engine\Cache\CacheRepository $cache */
        $cache = $this->app->make('cache')->store('array');

        $m1 = new MetricsRegistry($cache, ['persist' => true, 'prefix' => 'p']);
        $m1->counter('hits')->increment(7);

        // نمونهٔ دوم (مثل درخواست بعدی) — باید از cache بازیابد
        $m2 = new MetricsRegistry($cache, ['persist' => true, 'prefix' => 'p']);
        $this->assertSame(7.0, $m2->counter('hits')->value());

        $m2->counter('hits')->increment(3);
        $m3 = new MetricsRegistry($cache, ['persist' => true, 'prefix' => 'p']);
        $this->assertSame(10.0, $m3->counter('hits')->value());

        $m3->reset();
        $m4 = new MetricsRegistry($cache, ['persist' => true, 'prefix' => 'p']);
        $this->assertSame(0.0, $m4->counter('hits')->value());
    }

    // ─── T-03: traceparent parse + inject ────────────────────────────────

    public function testTraceContextParsesValidHeader(): void
    {
        $ctx = TraceContextPropagator::fromHeader('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01');
        $this->assertNotNull($ctx);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $ctx->traceId());
        $this->assertSame('00f067aa0ba902b7', $ctx->spanId());
        $this->assertTrue($ctx->sampled());
    }

    public function testTraceContextRejectsInvalidHeaders(): void
    {
        $this->assertNull(TraceContextPropagator::fromHeader(null));
        $this->assertNull(TraceContextPropagator::fromHeader(''));
        $this->assertNull(TraceContextPropagator::fromHeader('garbage'));
        // 🔒 W3C: trace-id تمام‌صفر نامعتبر
        $this->assertNull(TraceContextPropagator::fromHeader('00-00000000000000000000000000000000-00f067aa0ba902b7-01'));
        $this->assertNull(TraceContextPropagator::fromHeader('00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01'));
        // flags=0 → sampled نیست اما معتبر است
        $ctx = TraceContextPropagator::fromHeader('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-00');
        $this->assertNotNull($ctx);
        $this->assertFalse($ctx->sampled());
    }

    public function testTraceContextInjectUsesCurrentTrace(): void
    {
        TraceContext::setTrace('4bf92f3577b34da6a3ce929d0e0e4736', '00f067aa0ba902b7');
        $headers = [];
        TraceContextPropagator::inject($headers);

        $this->assertArrayHasKey('traceparent', $headers);
        $parsed = TraceContextPropagator::fromHeader($headers['traceparent']);
        $this->assertNotNull($parsed);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $parsed->traceId());
        $this->assertSame('00f067aa0ba902b7', $parsed->spanId());
    }

    // ─── T-04: Span با exception + والد/فرزندی ───────────────────────────

    private function tracer(float $rate = 1.0, ?\Tavaan\Core\Contracts\Observability\SpanExporterInterface $exporter = null): Tracer
    {
        $t = new Tracer(['enabled' => true, 'exporter' => 'none', 'sample_rate' => $rate, 'service' => 'test-svc']);
        if ($exporter !== null) {
            $t->addExporter($exporter);
        }

        return $t;
    }

    public function testSpansHaveParentChildRelation(): void
    {
        TraceContext::setTrace(\str_repeat('a', 32), \str_repeat('b', 16));
        $exporter = new ArrayExporter();
        $t = $this->tracer(1.0, $exporter);

        $root = $t->startSpan('http.request');
        $this->assertSame($root->spanId(), $t->currentSpan()?->spanId());
        $child = $t->startSpan('db.query', ['db.system' => 'sqlite']);
        $this->assertSame($root->spanId(), $child->parentSpanId());
        $this->assertSame($root->traceId(), $child->traceId());
        $this->assertSame(2, $t->depth());

        $child->end();
        $root->end();
        $this->assertSame(0, $t->depth());

        $this->assertSame(2, $exporter->count());
        $names = \array_column($exporter->spans(), 'name');
        $this->assertSame(['db.query', 'http.request'], $names);   // پایان فرزند زودتر
    }

    public function testWithSpanRecordsExceptionAndRethrows(): void
    {
        $exporter = new ArrayExporter();
        $t = $this->tracer(1.0, $exporter);

        $thrown = false;
        try {
            $t->withSpan('risky.op', [], static function (): never {
                throw new \RuntimeException('boom-obs');
            });
        } catch (\RuntimeException $e) {
            $thrown = $e->getMessage() === 'boom-obs';
        }

        $this->assertTrue($thrown);
        $span = $exporter->last();
        $this->assertNotNull($span);
        $this->assertSame(StatusCode::Error->value, $span['status']['code']);
        $this->assertSame('boom-obs', $span['status']['message']);
        $this->assertCount(1, $span['events']);
        $this->assertSame('exception', $span['events'][0]['name']);
        $this->assertSame(\RuntimeException::class, $span['events'][0]['attributes']['exception.type']);
    }

    public function testWithSpanSetsOkStatusOnSuccess(): void
    {
        $exporter = new ArrayExporter();
        $t = $this->tracer(1.0, $exporter);

        $result = $t->withSpan('good.op', ['k' => 'v'], static fn (Span $s): int => 42);

        $this->assertSame(42, $result);
        $span = $exporter->last();
        $this->assertSame(StatusCode::Ok->value, $span['status']['code']);
        $this->assertSame('v', $span['attributes']['k']);
    }

    public function testDoubleEndExportsOnce(): void
    {
        $exporter = new ArrayExporter();
        $t = $this->tracer(1.0, $exporter);
        $span = $t->startSpan('once');
        $span->end();
        $span->end();

        $this->assertSame(1, $exporter->count());
        $this->assertSame(0, $t->depth());
    }

    // ─── T-07: Sampling ──────────────────────────────────────────────────

    public function testSamplerIsDeterministicPerTraceId(): void
    {
        $traceId = \str_repeat('c', 32);
        $first = RateSampler::shouldSample($traceId, 0.5);
        for ($i = 0; $i < 50; $i++) {
            $this->assertSame($first, RateSampler::shouldSample($traceId, 0.5));
        }
        $this->assertTrue(RateSampler::shouldSample($traceId, 1.0));
        $this->assertFalse(RateSampler::shouldSample($traceId, 0.0));
    }

    public function testSamplingRateZeroProducesNonRecordingSpans(): void
    {
        $exporter = new ArrayExporter();
        $t = $this->tracer(0.0, $exporter);

        $span = $t->startSpan('not.sampled');
        $this->assertFalse($span->isRecording());
        $span->setAttribute('x', 1);
        $this->assertSame([], $span->attributes());
        $span->end();

        $this->assertSame(0, $exporter->count());
        $this->assertSame(0, $t->depth());
    }

    public function testChildInheritsParentSampling(): void
    {
        $exporter = new ArrayExporter();
        $t = $this->tracer(0.0, $exporter);

        $root = $t->startSpan('root');
        $child = $t->startSpan('child');
        $this->assertFalse($child->isRecording());
        $child->end();
        $root->end();
        $this->assertSame(0, $exporter->count());
    }

    public function testDisabledTracerProducesNonRecordingSpans(): void
    {
        $t = new Tracer(['enabled' => false]);
        $span = $t->startSpan('anything');
        $this->assertFalse($span->isRecording());
        $span->end();
        $this->assertSame(0, $t->depth());
    }

    public function testSamplingDistributionIsRoughlyCorrect(): void
    {
        $sampled = 0;
        $total = 2000;
        for ($i = 0; $i < $total; $i++) {
            $traceId = \str_pad(\dechex($i * 2654435761 % 0xFFFFFFFF), 8, '0', STR_PAD_LEFT) . \str_repeat('0', 24);
            if (RateSampler::shouldSample($traceId, 0.25)) {
                $sampled++;
            }
        }
        // دامنهٔ مجاز: بین ۱۵٪ و ۳۵٪
        $this->assertTrue($sampled > $total * 0.15, 'sampled=' . $sampled);
        $this->assertTrue($sampled < $total * 0.35, 'sampled=' . $sampled);
    }

    // ─── Exporters ───────────────────────────────────────────────────────

    public function testOtlpPayloadStructure(): void
    {
        $exporter = new OtlpHttpExporter('http://collector:4318', 'otlp-svc');
        $t = $this->tracer(1.0, $exporter);

        $span = $t->startSpan('db.query', ['db.system' => 'sqlite', 'rows' => 3, 'ok' => true]);
        $span->addEvent('checkpoint', ['n' => 1]);
        $span->setStatus(StatusCode::Ok);
        $span->end();

        $payload = $exporter->payload([$span]);
        $this->assertArrayHasKey('resourceSpans', $payload);
        $rs = $payload['resourceSpans'][0];
        // service از خود Span می‌آید (tracer روی test-svc تنظیم شده)
        $this->assertSame('test-svc', $rs['resource']['attributes'][0]['value']['stringValue']);

        $otlpSpan = $rs['scopeSpans'][0]['spans'][0];
        $this->assertSame('db.query', $otlpSpan['name']);
        $this->assertSame(1, $otlpSpan['kind']);
        $this->assertSame(StatusCode::Ok->value, $otlpSpan['status']['code']);
        $this->assertMatchesRegex('/^\d+$/', $otlpSpan['startTimeUnixNano']);

        $attrs = [];
        foreach ($otlpSpan['attributes'] as $a) {
            $attrs[$a['key']] = $a['value'];
        }
        $this->assertSame(['stringValue' => 'sqlite'], $attrs['db.system']);
        $this->assertSame(['intValue' => '3'], $attrs['rows']);
        $this->assertSame(['boolValue' => true], $attrs['ok']);
        $this->assertCount(1, $otlpSpan['events']);
    }

    public function testOtlpFlushUsesInjectedTransport(): void
    {
        $sent = [];
        $exporter = new OtlpHttpExporter(
            'http://collector:4318',
            'svc',
            256,
            1.0,
            static function (string $url, string $json) use (&$sent): bool {
                $sent[] = [$url, $json];

                return true;
            },
        );

        $t = $this->tracer(1.0, $exporter);
        $t->startSpan('op')->end();

        $this->assertSame(1, $exporter->flush());
        $this->assertCount(1, $sent);
        $this->assertSame('http://collector:4318/v1/traces', $sent[0][0]);
        $decoded = \json_decode($sent[0][1], true);
        $this->assertArrayHasKey('resourceSpans', $decoded);
        $this->assertSame(1, $exporter->sentCount());
        // flush دوباره بدون span جدید → ۰
        $this->assertSame(0, $exporter->flush());
    }

    public function testJaegerThriftBatchRoundTrip(): void
    {
        $exporter = new JaegerUdpExporter('127.0.0.1', 6831, 'jaeger-svc');
        $t = $this->tracer(1.0, $exporter);

        $root = $t->startSpan('http.req', ['http.method' => 'GET', 'n' => 42, 'f' => 1.5, 'b' => true]);
        $child = $t->startSpan('db.query');
        $child->end();
        $root->end();

        $spans = [$child, $root];
        $binary = $exporter->buildBatch($spans);

        $r = new ThriftCompactReader($binary);
        $msg = $r->readMessageBegin();
        $this->assertSame('emitBatch', $msg['name']);
        $this->assertSame(1, $msg['type']);   // CALL

        // args struct → field 1: Batch
        $r->enterStruct();
        $this->assertTrue($r->seekStructField(1));       // Batch

        // Batch → field 1: Process
        $processStart = $r->readFieldBegin();
        $this->assertNotNull($processStart);
        $this->assertSame(1, $processStart['id']);
        $this->assertSame(12, $processStart['type']);
        $r->enterStruct();                                 // Process
        $serviceName = $r->seekScalarField(1);
        $this->assertSame('jaeger-svc', $serviceName);
        $r->leaveStruct();                                 // Process را تا STOP مصرف می‌کند

        // Batch → field 2: list<Span>
        $list = $r->seekListField(2);
        $this->assertNotNull($list);
        $this->assertSame(12, $list['elemType']);
        $this->assertSame(2, $list['size']);

        // اولین span (child): field 3 = operationName
        $r->enterStruct();
        $opName = $r->seekScalarField(3);
        $this->assertSame('db.query', $opName);
        $r->leaveStruct();

        // دومین span (root): operationName + tags
        $r->enterStruct();
        $fields = [];
        while (($f = $r->readFieldBegin()) !== null) {
            if ($f['id'] === 3) {
                $fields['op'] = $r->readValue($f['type']);
            } elseif ($f['id'] === 7) {
                $tags = $r->readListBegin();
                $this->assertSame(4, $tags['size']);
                $fields['tags'] = $tags['size'];
                $r->skipList($tags['elemType'], $tags['size']);
            } else {
                $r->skipValue($f['type']);
            }
        }
        $r->structEnd();
        $this->assertSame('http.req', $fields['op']);
        $this->assertSame(4, $fields['tags']);
    }

    public function testJaegerFlushUsesInjectedSender(): void
    {
        $packets = [];
        $exporter = new JaegerUdpExporter(
            '127.0.0.1',
            6831,
            'svc',
            static function (string $payload, string $host, int $port) use (&$packets): bool {
                $packets[] = \strlen($payload);

                return true;
            },
        );
        $t = $this->tracer(1.0, $exporter);
        $t->startSpan('x')->end();

        $this->assertSame(1, $exporter->flush());
        $this->assertCount(1, $packets);
        $this->assertTrue($packets[0] > 10);
    }

    // ─── T-05: Health checks ─────────────────────────────────────────────

    public function testHealthChecksUpAndDown(): void
    {
        $h = new HealthManager();
        $h->add('database', static fn (): bool => true);
        $h->add('cache', static fn (): string => Up::class);
        $h->add('queue', static fn () => new Down('queue size 15000 > 10000'));
        $h->add('boom', static function (): never {
            throw new \RuntimeException('db gone');
        });

        $result = $h->check();
        $this->assertSame('unhealthy', $result['status']);
        $this->assertCount(4, $result['checks']);

        $byName = [];
        foreach ($result['checks'] as $c) {
            $byName[$c['name']] = $c;
        }
        $this->assertSame('up', $byName['database']['status']);
        $this->assertSame('up', $byName['cache']['status']);
        $this->assertSame('down', $byName['queue']['status']);
        $this->assertSame('queue size 15000 > 10000', $byName['queue']['message']);
        $this->assertSame('down', $byName['boom']['status']);
        $this->assertStringContainsString('db gone', $byName['boom']['message']);
        $this->assertTrue($byName['database']['responseTime'] >= 0);
    }

    public function testHealthAllUpIsHealthy(): void
    {
        $h = new HealthManager();
        $h->add('a', static fn (): array => ['status' => 'up']);
        $h->add('b', static fn (): CheckResult => CheckResult::up('b', 1.5));

        $result = $h->check();
        $this->assertSame('healthy', $result['status']);
        $this->assertSame(200, HealthManager::statusCode($result));
    }

    public function testHealthLiveReadySeparation(): void
    {
        $h = new HealthManager();
        $h->add('process', static fn (): bool => true, 'live');
        $h->add('database', static fn (): bool => false, 'ready');

        $live = $h->live();
        $this->assertSame('healthy', $live['status']);
        $this->assertCount(1, $live['checks']);

        $ready = $h->ready();
        $this->assertSame('unhealthy', $ready['status']);
        $this->assertSame(503, HealthManager::statusCode($ready));
    }

    // ─── T-06: Debug Bar فقط در dev ──────────────────────────────────────

    public function testDebugBarGuards(): void
    {
        // غیرفعال به‌طور پیش‌فرض
        $bar = new DebugBar(['debug' => true, 'env' => 'local'], ['enabled' => false]);
        $this->assertFalse($bar->isEnabled());

        // production حتی با debug=true
        $bar = new DebugBar(['debug' => true, 'env' => 'production'], ['enabled' => true]);
        $this->assertFalse($bar->isEnabled());

        // debug=false
        $bar = new DebugBar(['debug' => false, 'env' => 'local'], ['enabled' => true]);
        $this->assertFalse($bar->isEnabled());

        // dev واقعی
        $bar = new DebugBar(['debug' => true, 'env' => 'local'], ['enabled' => true]);
        $this->assertTrue($bar->isEnabled());
    }

    public function testDebugBarRenderEscapesXss(): void
    {
        $sw = new Stopwatch();
        $sw->record('<img src=x onerror=alert(1)>', 2.5);
        $bar = new DebugBar(['debug' => true, 'env' => 'local'], ['enabled' => true], $sw);
        $bar->addSpan('<script>alert(2)</script>', 1.0);

        $html = $bar->render();
        $this->assertFalse(\str_contains($html, '<script>alert(2)</script>'));
        $this->assertFalse(\str_contains($html, '<img src=x'));
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('tvn-debugbar', $html);
    }

    public function testDebugBarMiddlewareInjectsOnlyIntoHtml(): void
    {
        $bar = new DebugBar(['debug' => true, 'env' => 'local'], ['enabled' => true], new Stopwatch());
        $mw = new DebugBarMiddleware($bar);

        $html = new Response('<html><body><h1>hi</h1></body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $result = $mw->handle(Request::create('http://localhost/'), static fn (): Response => $html);
        $body = $result->body();
        $this->assertStringContainsString('tvn-debugbar', $body);
        // پنل قبل از </body> تزریق شده
        $this->assertTrue(\strrpos($body, 'tvn-debugbar') < (int) \strrpos($body, '</body>'));

        // JSON دست‌نخورده
        $json = Response::json(['a' => 1]);
        $result2 = $mw->handle(Request::create('http://localhost/'), static fn (): Response => $json);
        $this->assertFalse(\str_contains($result2->body(), 'tvn-debugbar'));

        // غیر HTML بدون </body> دست‌نخورده
        $text = new Response('plain text', 200, ['Content-Type' => 'text/plain']);
        $result3 = $mw->handle(Request::create('http://localhost/'), static fn (): Response => $text);
        $this->assertSame('plain text', $result3->body());
    }

    // ─── یکپارچگی: middleware + routes ───────────────────────────────────

    public function testMetricsMiddlewareRecordsHttpRequest(): void
    {
        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);
        /** @var \Tavaan\Engine\Http\Routing\Router $router */
        $router = $this->app->make('router');
        $router->get('/obs/test', static fn (): array => ['ok' => true])->name('obs.test');

        $request = Request::create('http://localhost/obs/test');
        $response = $kernel->handle($request);
        $this->assertSame(200, $response->statusCode());

        /** @var MetricsRegistry $metrics */
        $metrics = $this->app->make('metrics');
        $total = $metrics->counter('http_requests_total');
        $this->assertSame(1.0, $total->value(['route' => 'obs.test', 'method' => 'GET', 'status' => '200']));

        /** @var Histogram $hist */
        $hist = $metrics->histogram('http_request_duration_seconds');
        $this->assertSame(1, $hist->count(['route' => 'obs.test', 'method' => 'GET']));
    }

    public function testMetricsEndpointReturnsPrometheusText(): void
    {
        /** @var MetricsRegistry $metrics */
        $metrics = $this->app->make('metrics');
        $metrics->counter('probe_total', 'probe')->increment(2);

        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);
        $response = $kernel->handle(Request::create('http://localhost/metrics'));

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('text/plain', (string) $response->header('Content-Type'));
        $this->assertStringContainsString('tavaan_probe_total 2', $response->body());
    }

    public function testHealthEndpoints(): void
    {
        /** @var HealthManager $health */
        $health = $this->app->make('health');
        $health->add('probe', static fn (): bool => true, 'live');

        /** @var HttpKernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);

        $live = $kernel->handle(Request::create('http://localhost/health/live'));
        $this->assertSame(200, $live->statusCode());
        $decoded = \json_decode($live->body(), true);
        $this->assertSame('healthy', $decoded['status']);

        // readiness با بررسی خراب → 503
        $health->add('broken', static fn (): bool => false, 'ready');
        $ready = $kernel->handle(Request::create('http://localhost/health/ready'));
        $this->assertSame(503, $ready->statusCode());
        $decoded = \json_decode($ready->body(), true);
        $this->assertSame('unhealthy', $decoded['status']);
    }

    public function testFacadesResolve(): void
    {
        $this->assertTrue($this->app->bound('metrics'));
        $this->assertTrue($this->app->bound('tracer'));
        $this->assertTrue($this->app->bound('health'));
        $this->assertTrue($this->app->bound('debugbar'));
        $this->assertInstanceOf(MetricsRegistry::class, $this->app->make('metrics'));
        $this->assertInstanceOf(Tracer::class, $this->app->make('tracer'));
    }

    public function testStopwatchMeasureAndAll(): void
    {
        $sw = new Stopwatch();
        $sw->mark('boot');
        $result = $sw->measure('work', static function (): string {
            $t = \hrtime(true);
            while (\hrtime(true) - $t < 2_000_000) {
                // ~2ms
            }

            return 'done';
        });

        $this->assertSame('done', $result);
        $all = $sw->all();
        $this->assertArrayHasKey('work', $all);
        $this->assertArrayHasKey('boot @', $all);
        $this->assertTrue($all['work'] >= 1.0);
    }
}
