# DOC-067 — موتور Observability | Metrics، Trace و Health

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-067 |
| Engine ID | `tavaan.engine.observability` |
| Namespace | `Tavaan\Engine\Observability` |
| اولویت | **P1** — فاز M6 |
| وابستگی | `event`, `log`, `http` |
| Facade | `Metrics`, `Trace` |
| LOC | ۱۴۰۰ |

## ۱. هدف
رصدپذیری سه‌گانه: **Metrics** (Prometheus-compatible)، **Distributed Tracing**
(W3C TraceContext + OTLP)، **Health Checks** (liveness/readiness) و **Debug Bar**.

## ۲. Metrics
```php
Metrics::counter('http_requests_total')->increment(labels: ['route'=>'users.index','status'=>'200']);
Metrics::gauge('queue_size', 42, labels: ['queue'=>'default']);
Metrics::histogram('http_request_duration_seconds')->observe(0.034, labels: ['route'=>'users.index']);
Metrics::timer('db.query', fn () => $this->runQuery());
Metrics::collect();     // → Prometheus text format
```

### ۲٫۱ انواع
| نوع | توضیح |
|---|---|
| Counter | افزایشی (requests_total) |
| Gauge | افزایشی/کاهشی (queue_size, memory) |
| Histogram | توزیع (duration) با buckets |
| Summary | quantile |

### ۲٫۲ Prometheus Format (`/metrics`)
```
# HELP tavaan_http_requests_total Total HTTP requests
# TYPE tavaan_http_requests_total counter
tavaan_http_requests_total{route="users.index",status="200"} 1523
tavaan_http_request_duration_seconds_bucket{le="0.01"} 234
tavaan_http_request_duration_seconds_bucket{le="0.1"} 1450
tavaan_http_request_duration_seconds_count 1523
tavaan_http_request_duration_seconds_sum 12.5
```

## ۳. Distributed Tracing
```php
$span = Trace::startSpan('db.query', attributes: ['db.system'=>'mysql','db.statement'=>$sql]);
try { /* … */ $span->setStatus(StatusCode::Ok); }
catch (\Throwable $e) { $span->recordException($e); $span->setStatus(StatusCode::Error); throw $e; }
finally { $span->end(); }
```

### ۳٫۱ W3C TraceContext
```
traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
             │  └─ trace-id (32 hex)              └─ span-id (16) └─ flags
             └─ version
```
```php
final class TraceContext
{
    public static function fromHeader(?string $header): TraceContext { /* parse */ }
    public static function inject(array &$headers): void { /* برای درخواست خروجی */ }
    public function traceId(): string;
    public function spanId(): string;
    public function newSpanId(): string;
}
```
🔒 `TraceContextMiddleware` در ورود traceparent را پارس و در `Context` ذخیره می‌کند.
🔒 در `http-client` و `queue`، traceparent به خروجی تزریق می‌شود.

### ۳٫۲ Exporters
| Exporter | مقصد |
|---|---|
| `ArrayExporter` | تست |
| `LogExporter` | لاگ ساختاریافته |
| `OtlpHttpExporter` | OTLP/HTTP (Jaeger, Tempo, Zipkin) |
| `JaegerUdpExporter` | Jaeger |

## ۴. Health Checks
```php
// GET /health/live  → liveness
// GET /health/ready → readiness
Health::add('database', fn () => DB::select('SELECT 1') ? Up::class : Down::class);
Health::add('cache', fn () => Cache::get('health:ping') !== null || Cache::put('health:ping', 1, 60));
Health::add('queue', fn () => Queue::size() < 10000 ? Up::class : Down::class);
Health::add('disk', fn () => disk_free_space('/') > 1073741824 ? Up::class : Down::class);
```
پاسخ:
```json
{
  "status": "healthy",
  "checks": [
    {"name":"database","status":"up","responseTime":2},
    {"name":"cache","status":"up","responseTime":1},
    {"name":"queue","status":"down","message":"queue size 15000 > 10000"}
  ]
}
```

## ۵. Debug Bar (فقط dev)
پنل HTML تزریق‌شده در پایان `<body>` با: زمان هر فاز (DOC-025)، کوئری‌ها، حافظه،
رویدادها، Middlewareها، Route، Session، Config (masked)، Exceptionها.

```php
final class DebugBar
{
    public function collect(): array
    {
        return [
            'timings' => $this->stopwatch->all(),
            'queries' => DB::queryLog(),
            'memory' => \memory_get_peak_usage(true),
            'events' => Event::getDispatched(),
            'route' => Route::current()?->name(),
            'middleware' => $this->kernel->resolvedMiddleware(),
        ];
    }
    public function render(): string { /* HTML با CSS inline، escape شده */ }
}
```
🔒 فقط وقتی `app.debug=true` و `app.env !== 'production'`.
🔒 خروجی Debug Bar **escape** می‌شود (XSS).

## ۶. پیکربندی
```php
// config/observability.php
return [
    'metrics' => ['enabled'=>true, 'endpoint'=>'/metrics', 'prefix'=>'tavaan'],
    'tracing' => [
        'enabled'=>env_bool('TVN_TRACING', false),
        'exporter'=>env_string('TVN_TRACE_EXPORTER','log'),
        'sample_rate'=>env_float('TVN_TRACE_SAMPLE', 1.0),
        'otlp_endpoint'=>env_string('OTEL_EXPORTER_OTLP_ENDPOINT'),
    ],
    'health' => ['endpoint'=>'/health', 'checks'=>['database','cache','queue','disk']],
    'debug_bar' => ['enabled'=>env_bool('TVN_DEBUG_BAR', false)],
];
```

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| نشت اطلاعات در `/metrics` | auth یا شبکهٔ داخلی |
| Debug Bar در prod | فقط `app.debug && !production` |
| Trace با دادهٔ حساس | attribute filtering + mask |
| DoS با tracing کامل | sample_rate |

## ۸. تست
| T-01 | Counter/Gauge/Histogram + collect |
| T-02 | Prometheus format |
| T-03 | traceparent parse + inject |
| T-04 | Span با exception |
| T-05 | Health check up/down |
| T-06 | Debug Bar فقط در dev |
| T-07 | Sampling |

## ۹. معیار پذیرش 🧪
- AC-OBS-01..07. AC-OBS-08: Trace از Gateway تا DB متصل می‌ماند.

## ۱۰. مراجع
| منبع | تفاوت |
|---|---|
| OpenTelemetry PHP | Span/Trace | خودنوشته OTLP، بدون SDK |
| Prometheus client_php | Metrics | — |
| Laravel Debugbar / Telescope | Debug Bar | داخلی، escape شده |

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.observability.v1
engine:
  id: tavaan.engine.observability
  namespace: Tavaan\Engine\Observability
  path: engines/observability
  priority: P1
  phase: M6
  depends_on: [event, log, http]
  facade: [{name: Metrics, accessor: metrics}, {name: Trace, accessor: tracer}]
  config_file: config/observability.php
  public_api:
    - class: Metrics
      methods: [counter, gauge, histogram, timer, collect]
    - class: Tracer
      methods: [startSpan, currentSpan, withSpan]
    - class: Health
      methods: [add, check, live, ready]
  loc_estimate: 1400
```
