# DOC-036 — موتور Log | گزارش ساختاریافته

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-036 |
| Engine ID | `tavaan.engine.log` |
| Namespace | `Tavaan\Engine\Log` |
| اولویت | **P0** — فاز M1 |
| وابستگی | `config`؛ نرم: `event`, `queue` |
| Facade | `Log` → `log` |
| LOC | ۹۰۰ |
| استاندارد | RFC 5424 (Levels)، JSON Lines |

---

## ۱. هدف

یک سیستم لاگ **ساختاریافته، چندکاناله، چرخان و Mask‌شده** که جایگزین `monolog` شود —
کاملاً خودنوشته و بدون وابستگی.

- ✅ ۸ سطح RFC 5424 + `LogLevel` enum
- ✅ کانال‌های متعدد (Stack) با Driverهای مختلف
- ✅ Formatter: JSON, Line, Raw, Syslog, HTML(dev)
- ✅ Processor: Masking, TraceId, TenantId, UserId, Memory, RequestId
- ✅ چرخش روزانه/حجمی با Retention
- ✅ Buffering + Flush (کاهش I/O)
- ✅ Context تجمعی (`Log::withContext()`, `Log::shareContext()`)
- ✅ لاگ کوئری، رویداد، صف، ممیزی به کانال‌های جدا

---

## ۲. معماری

```
Log::error('x', [...])
   │
   ▼
┌──────────────┐    ┌──────────────────┐    ┌────────────────┐
│ LogManager   │───▶│ Logger (channel) │───▶│ Processor Chain │
└──────────────┘    └────────┬─────────┘    └───────┬────────┘
                             │                      ▼
                             │              ┌────────────────┐
                             │              │ LogRecord (RO) │
                             │              └───────┬────────┘
                             ▼                      ▼
                     ┌──────────────┐       ┌────────────────┐
                     │   Buffer     │──────▶│  Formatter     │
                     └──────────────┘       └───────┬────────┘
                                                    ▼
                                            ┌────────────────┐
                                            │ Handler (Write)│
                                            │ file/syslog/   │
                                            │ stdout/db/http │
                                            └────────────────┘
```

### ۲٫۱ کلاس‌ها
| کلاس | مسئولیت |
|---|---|
| `LogManager` | مدیریت کانال‌ها (Lazy) |
| `Logger` | یک کانال |
| `LogRecord` | readonly رکورد لاگ |
| `LogLevel` | enum |
| `Processors/*` | ۱۲ Processor |
| `Formatters/*` | ۵ Formatter |
| `Handlers/*` | ۷ Handler |
| `Rotator` | چرخش فایل |
| `Buffer` | بافر نوشتن |
| `LogServiceProvider`, `LogFacade` | ثبت |

---

## ۳. API

```php
Log::emergency('system.down', ['service' => 'db']);
Log::alert(...); Log::critical(...); Log::error(...); Log::warning(...);
Log::notice(...); Log::info(...); Log::debug(...);
Log::log(LogLevel::Error, 'message.id', ['ctx' => 1]);

// کانال
Log::channel('audit')->info('user.role.changed', [...]);
Log::stack(['daily', 'slack'])->error('critical');

// Context
Log::withContext(['orderId' => $id])->info('order.paid');       // یک‌بار
Log::shareContext(['tenant' => $t]);                            // کل درخواست
Log::withoutContext();

// Lazy context (فقط اگر لاگ شود)
Log::debug('expensive', fn () => ['dump' => $heavy->toArray()]);

// Flush
Log::flush();
```

### ۳٫۱ Interface
→ `core/Contracts/Log/LoggerInterface` (DOC-014 §2.8)

---

## ۴. ساختار `LogRecord`

```php
final readonly class LogRecord
{
    public function __construct(
        public string $channel,
        public LogLevel $level,
        public string $message,          // messageId (نه متن خام)
        public array $context,           // masked
        public int $severity,            // RFC 5424: 0..7
        public string $timestamp,        // ISO-8601 UTC با میکروثانیه
        public string $traceId,
        public string $spanId,
        public ?string $userId,
        public ?string $tenantId,
        public string $requestId,
        public string $environment,
        public ?string $file,
        public ?int $line,
        public int $memoryKb,
        public float $runtimeMs,
        public array $extra,             // Processor-added
    ) {}

    public function toArray(): array { /* … */ }
}
```

---

## ۵. Handlers

| Handler | driver | توضیح | تنظیمات کلیدی |
|---|---|---|---|
| `StreamHandler` | `single` | یک فایل | `path`, `permission`, `locking` |
| `RotatingFileHandler` | `daily` | چرخش روزانه | `days`, `filename_pattern` |
| `SizeRotatingHandler` | `size` | چرخش حجمی | `max_size_mb`, `max_files` |
| `StderrHandler` | `stderr` | برای Docker/CLI | — |
| `SyslogHandler` | `syslog` | `syslog()` | `facility`, `ident` |
| `DatabaseHandler` | `database` | جدول `logs` | `table`, `connection`, `batch` |
| `HttpHandler` | `http` | ارسال به Endpoint | `url`, `timeout`, `sign`, `async` |
| `NullHandler` | `null` | تست | — |
| `ArrayHandler` | `array` | تست | — |

### ۵٫۱ RotatingFileHandler — الگوریتم
```
1. filename = "{name}-{Y-m-d}.log" (یا {name}-{Y-Ww}.log برای هفتگی)
2. اگر فایل فعلی != فایل امروز → rotate:
     a. فهرست فایل‌های مطابق الگو
     b. مرتب‌سازی بر اساس تاریخ
     c. حذف قدیمی‌تر از `days`
3. نوشتن با flock (LOCK_EX) برای ایمنی همزمانی
4. اگر `max_size_mb` و حجم > حد → rotate فوری با suffix شمارنده
```
🔒 نوشتن باید **atomic append** باشد (`FILE_APPEND` + `LOCK_EX`).
🔒 در چرخش، اگر چند فرایند همزمان بچرخانند → `rename` با بررسی `is_file` (idempotent).

### ۵٫۲ Buffer
```php
final class Buffer
{
    /** @var list<string> */
    private array $lines = [];

    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly int $maxLines = 100,
        private readonly int $flushIntervalMs = 1000,
        private readonly bool $flushOnShutdown = true,
    ) {
        if ($this->flushOnShutdown) {
            \register_shutdown_function($this->flush(...));
        }
    }

    public function write(LogRecord $r): void
    {
        $this->lines[] = $this->formatter->format($r);
        if (\count($this->lines) >= $this->maxLines) { $this->flush(); }
    }

    public function flush(): void
    {
        if ($this->lines === []) { return; }
        $batch = $this->lines;
        $this->lines = [];
        $this->handler->writeBatch($batch);
    }
}
```
🔒 در `terminate()` فراخوانی شود تا لاگ‌ها گم نشوند.

---

## ۶. Processors

| Processor | افزودن |
|---|---|
| `MaskProcessor` | اعمال `LogMasker` روی context |
| `TraceContextProcessor` | `trace_id`, `span_id` |
| `RequestContextProcessor` | `method`, `url`, `ip`, `user_agent` |
| `IdentityProcessor` | `user_id`, `tenant_id` |
| `MemoryProcessor` | `memory_kb` |
| `RuntimeProcessor` | `runtime_ms` از `TVN_START` |
| `BacktraceProcessor` | `file`, `line` (با حذف فریم‌های فریمورک) |
| `EnvironmentProcessor` | `env`, `app_version`, `hostname` |
| `TagsProcessor` | تگ‌های ثابت کانال |
| `TruncateProcessor` | محدودیت طول context (جلوگیری از لاگ غول) |
| `SamplingProcessor` | نمونه‌برداری برای لاگ‌های پرحجم (`rate: 0.1`) |
| `DeduplicateProcessor` | جلوگیری از تکرار یکسان در N ثانیه |

```php
final class BacktraceProcessor implements ProcessorInterface
{
    private const array SKIP_FRAMES = [
        self::class, Logger::class, LogManager::class, Facade::class,
    ];

    public function process(LogRecord $r): LogRecord
    {
        $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);
        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            if ($class !== '' && !\in_array($class, self::SKIP_FRAMES, true)
                && !\str_starts_with($class, 'Tavaan\\Engine\\Log\\')) {
                return $r->withSource($frame['file'] ?? null, $frame['line'] ?? null);
            }
        }
        return $r;
    }
}
```

---

## ۷. Formatters

### ۷٫۱ JSON (پیش‌فرض تولید)
```json
{"ts":"2026-10-15T12:34:56.789+00:00","level":"error","severity":3,"channel":"app",
 "message":"shop.order.payment_failed","context":{"order_id":"01J...","reason":"gateway_timeout"},
 "trace_id":"4bf92f3577b34da6a3ce929d0e0e4736","span_id":"a3ce929d0e0e4736",
 "user_id":"42","tenant_id":"acme","request_id":"req_9f3c","env":"production",
 "file":"modules/shop/src/Application/UseCases/PayOrderUseCase.php","line":78,
 "memory_kb":4096,"runtime_ms":42.3,"app_version":"1.0.0"}
```
🔒 `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR`.

### ۷٫۲ Line (توسعه)
```
[2026-10-15 12:34:56] production.ERROR: shop.order.payment_failed {"order_id":"01J..."} [trace:4bf92f35] [user:42]
```

### ۷٫۳ CLI (رنگی)
```
 ERROR  shop.order.payment_failed
        order_id: 01J...
        at PayOrderUseCase.php:78  •  42.3ms  •  trace 4bf92f35
```

---

## ۸. پیکربندی

```php
// engines/log/config/log.php
return [
    'default' => env_string('TVN_LOG_CHANNEL', 'daily'),

    'channels' => [
        'daily' => [
            'driver'     => 'daily',
            'path'       => storage_path('logs/tavaan.log'),
            'level'      => env_string('TVN_LOG_LEVEL', 'debug'),
            'days'       => 14,
            'permission' => 0640,
            'formatter'  => env_string('TVN_LOG_FORMAT', 'line'),
            'processors' => ['mask','trace','identity','memory','runtime','backtrace','environment'],
            'buffer'     => ['enabled' => true, 'max_lines' => 50],
        ],

        'json' => [
            'driver' => 'daily', 'path' => storage_path('logs/tavaan.json.log'),
            'formatter' => 'json', 'days' => 30,
            'processors' => ['mask','trace','identity','environment'],
        ],

        'audit' => [
            'driver' => 'daily', 'path' => storage_path('logs/audit.log'),
            'level' => 'info', 'days' => 1825,        // ۵ سال
            'formatter' => 'json', 'locking' => true,
            'processors' => ['mask','trace','identity','environment'],
            'immutable' => true,                       // هش زنجیره‌ای (با موتور audit)
        ],

        'query' => [
            'driver' => 'daily', 'path' => storage_path('logs/query.log'),
            'level' => 'debug', 'days' => 3,
            'enabled' => env_bool('TVN_LOG_QUERY', false),
            'slow_threshold_ms' => 100,
            'formatter' => 'json',
        ],

        'queue' => [
            'driver' => 'daily', 'path' => storage_path('logs/queue.log'),
            'level' => 'info', 'days' => 7, 'formatter' => 'json',
        ],

        'security' => [
            'driver' => 'daily', 'path' => storage_path('logs/security.log'),
            'level' => 'warning', 'days' => 180, 'formatter' => 'json',
        ],

        'deprecations' => [
            'driver' => 'daily', 'path' => storage_path('logs/deprecations.log'),
            'level' => 'warning', 'days' => 30,
            'processors' => ['mask','backtrace','deduplicate'],
        ],

        'stderr' => ['driver' => 'stderr', 'formatter' => 'json', 'level' => 'error'],
        'stack'  => ['driver' => 'stack', 'channels' => ['daily', 'stderr'], 'ignore_exceptions' => false],
        'null'   => ['driver' => 'null'],
    ],

    'masking' => [
        'keys'     => [],           // افزودنی بر پیش‌فرض‌های LogMasker
        'patterns' => [],
    ],

    'limits' => [
        'max_context_depth'  => 8,
        'max_context_bytes'  => 8192,
        'max_message_length' => 2048,
        'per_request_limit'  => 500,       // پس از آن فقط هشدار
    ],
];
```

---

## ۹. امنیت

| تهدید | کنترل |
|---|---|
| Log Injection (CRLF) | 🔒 حذف `\r\n` از message/context |
| نشت Secret | MaskProcessor همیشه در زنجیره (غیرقابل حذف در `audit`/`security`) |
| Log Flooding (DoS) | `per_request_limit` + Sampling + Deduplicate |
| افشای PII | `TruncateProcessor` + قوانین `masking` |
| دستکاری لاگ | `immutable: true` → زنجیرهٔ هش (موتور audit) |
| نوشتن به مسیر نامعتبر | Path sandbox داخل `storage/logs` |

```php
private function sanitize(string $s): string
{
    return \preg_replace('/[\r\n\t\0]+/', ' ', $s) ?? $s;
}
```

---

## ۱۰. کارایی

| عملیات | هدف |
|---|---|
| `Log::info()` با Buffer | ≤ ۵µs |
| `Log::info()` با نوشتن فوری (flush) | ≤ ۰٫۳ms |
| JSON format یک رکورد | ≤ ۲۰µs |
| Mask روی context با ۲۰ کلید | ≤ ۳۰µs |
| چرخش فایل (۱۰۰۰ فایل قدیمی) | ≤ ۱۰۰ms |
| حافظهٔ Buffer با ۵۰ رکورد | ≤ ۱۰۰KB |

---

## ۱۱. دستورات CLI
| دستور | توضیح |
|---|---|
| `tvn log:tail [channel] [-n 50] [-f]` | نمایش زنده |
| `tvn log:find <refCode>` | یافتن همهٔ لاگ‌های یک refCode |
| `tvn log:trace <traceId>` | بازسازی زنجیرهٔ یک درخواست |
| `tvn log:clear --older-than=30d` | پاکسازی |
| `tvn log:stats` | حجم/تعداد بر اساس سطح |
| `tvn log:rotate` | چرخش دستی |

---

## ۱۲. تست
| ID | سناریو |
|---|---|
| T-01 | هر ۸ سطح → severity RFC 5424 درست |
| T-02 | سطح فیلتر: `level=warning` → `info` نوشته نمی‌شود |
| T-03 | Stack channel → هر دو کانال |
| T-04 | Mask: `password` → `********` |
| T-05 | Pattern mask: شمارهٔ کارت ۱۶ رقمی → `4321********5678` |
| T-06 | چرخش روزانه با Clock Fake |
| T-07 | `days=3` → حذف فایل‌های قدیمی‌تر |
| T-08 | Buffer: ۵۰ رکورد → یک `writeBatch` |
| T-09 | `shutdown` → flush |
| T-10 | Log Injection با `\r\n` → پاک شده |
| T-11 | `per_request_limit` → پس از حد، فقط یک هشدار |
| T-12 | JSON formatter → UTF-8 فارسی بدون escape |
| T-13 | Context تجمعی (`shareContext`) در همهٔ رکوردها |

---

## ۱۳. معیار پذیرش 🧪
- AC-LOG-01: همهٔ تست‌های §12 سبز.
- AC-LOG-02: اهداف کارایی §10.
- AC-LOG-03: هیچ Secret در خروجی لاگ (تست Fuzzing با ۲۰۰ کلید).
- AC-LOG-04: `tvn log:trace <id>` زنجیرهٔ کامل را بازسازی می‌کند.
- AC-LOG-05: در صورت پر بودن دیسک → `DiskFullException` + ادامهٔ برنامه (نه Crash).

---

## ۱۴. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| Monolog | Handler/Formatter/Processor | ساختار یکسان اما صفر وابستگی؛ `LogRecord` readonly؛ Mask داخلی |
| PSR-3 | `LoggerInterface` | Interface داخلی (بدون `psr/log`) |
| Laravel Log | channel/stack | افزودن Sampling، Deduplicate، Immutable |
| RFC 5424 | Severity | — |
| structlog (Python) | Context تجمعی | — |

---

## ۱۵. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.log.v1
engine:
  id: tavaan.engine.log
  namespace: Tavaan\Engine\Log
  path: engines/log
  priority: P0
  phase: M1
  depends_on: [config]
  soft_depends_on: [event, queue, database]
  extensions: {required: [], optional: [{name: syslog, fallback: file}]}
  facade: {name: Log, accessor: log}
  tables: [logs]
  events_emitted: [log.written, log.rotation, log.disk_full]
  config_file: engines/log/config/log.php
  public_api:
    - class: LogManager
      methods: [channel, stack, driver, extend, flush, getChannels]
    - class: Logger
      methods: [emergency, alert, critical, error, warning, notice, info, debug, log,
                withContext, shareContext, withoutContext]
  loc_estimate: 900
  acceptance:
    - {id: AC-LOG-03, test: 'engines/log/tests/Security/MaskingTest.php'}
```
