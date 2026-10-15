# DOC-023 — معماری رویداد‌محور (Event-Driven Architecture) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-023 |
| وضعیت | Approved v1.0 |
| موتور مسئول | `engines/event`، `engines/queue`، `engines/database` (Outbox) |
| مرتبط | DOC-022 (مرز ماژول)، DOC-024 (مایکروسرویس) |

---

## ۱. نقش رویداد در تَوان

| کاربرد | توضیح |
|---|---|
| کاهش Coupling بین ماژول | جایگزین فراخوانی مستقیم |
| ناهمزمان‌سازی | انتقال کار سنگین به Queue |
| CQRS | جداسازی Write/Read Model |
| Event Sourcing (اختیاری) | تاریخچهٔ کامل تغییرات |
| یکپارچگی توزیعی | Outbox → Broker → سرویس‌ها |
| Audit | زنجیرهٔ رویدادها به‌عنوان رد پای تغییر |
| Hook/Plugin | نقاط توسعه برای پلاگین‌ها |

---

## ۲. دسته‌بندی رویدادها

| دسته | نمونه | عمر | انتشار |
|---|---|---|---|
| **Domain Event** | `OrderWasPaid` | گذشته‌نگر، تغییرناپذیر | داخلی ماژول + عمومی در صورت اعلام |
| **Application Event** | `UserRegistered` | جریان برنامه | سراسری |
| **Framework Event** | `KernelBooted`, `RequestHandled` | چرخهٔ اجرا | هسته |
| **Integration Event** | `payment.transaction.succeeded` | بین ماژول/سرویس | Outbox/Broker |
| **Broadcast Event** | `chat.message.received` | Realtime | WebSocket/SSE |
| **System Event** | `cache.flushed`, `queue.job.failed` | عملیاتی | Observability |

---

## ۳. ساختار یک رویداد (Event Envelope)

```php
<?php
declare(strict_types=1);

namespace Modules\Shop\Contracts\Events;

use Tavaan\Core\Contracts\Events\DomainEventInterface;
use Tavaan\Support\Id\Ulid;

/**
 * رویداد عمومی: سفارش پرداخت شد.
 *
 * @api 1.0
 * @schema shop.order.paid
 * @version 1
 */
final readonly class OrderWasPaid implements DomainEventInterface, \JsonSerializable
{
    public function __construct(
        public string $eventId,          // ULID یکتا
        public string $occurredAt,        // ISO-8601 UTC
        public string $aggregateType,     // 'shop.order'
        public string $aggregateId,       // شناسهٔ سفارش
        public int    $version,           // نسخهٔ Schema
        public string $producer,          // 'tavaan.shop'
        public string $traceId,           // W3C trace id
        public string $causationId,       // شناسهٔ فرمان مسبب
        public string $correlationId,     // شناسهٔ زنجیرهٔ کسب‌وکار
        public int    $sequence,          // شمارهٔ ترتیب در Aggregate
        public array  $payload,           // دادهٔ دامنه
        public string $idempotencyKey,    // کلید یکتایی مصرف
    ) {}

    public static function of(string $aggregateId, Money $amount, string $causationId): self
    {
        return new self(
            eventId: Ulid::generate()->toString(),
            occurredAt: Clock::utcNow()->format(\DateTimeInterface::ATOM),
            aggregateType: 'shop.order',
            aggregateId: $aggregateId,
            version: 1,
            producer: 'tavaan.shop',
            traceId: TraceContext::traceId(),
            causationId: $causationId,
            correlationId: CorrelationContext::id(),
            sequence: SequenceProvider::next('shop.order', $aggregateId),
            payload: ['amount' => $amount->amount(), 'currency' => $amount->currency()->value],
            idempotencyKey: \hash('xxh128', "shop.order.paid:{$aggregateId}:v1"),
        );
    }

    public function eventName(): string { return 'shop.order.paid'; }
    public function jsonSerialize(): array { /* envelope کامل */ }
}
```

### ۳٫۱ قواعد Envelope 🔒
| فیلد | اجباری | توضیح |
|---|---|---|
| `eventId` | 🔒 | ULID — یکتای سراسری |
| `occurredAt` | 🔒 | UTC ISO-8601 با میلی‌ثانیه |
| `aggregateType` / `aggregateId` | 🔒 | منشأ دامنه |
| `version` | 🔒 | نسخهٔ Schema (برای Migration) |
| `producer` | 🔒 | شناسهٔ ماژول/سرویس |
| `traceId`/`spanId` | 🔒 | ادامهٔ Trace توزیعی |
| `causationId` | ⚠️ | فرمان/رویداد مسبب |
| `correlationId` | 🔒 | زنجیرهٔ کسب‌وکار (مثلاً یک خرید) |
| `sequence` | ⚠️ | ترتیب در Aggregate (برای Ordering) |
| `idempotencyKey` | 🔒 | جلوگیری از پردازش دوباره |
| `payload` | 🔒 | فقط دادهٔ لازم، بدون Entity زنده |

### ۳٫۲ قواعد محتوایی
1. 🔒 رویداد **گذشته‌نگر** است: `OrderWasPaid` نه `PayOrder`.
2. 🔒 رویداد **تغییرناپذیر** است — اصلاح = رویداد جدید.
3. 🔒 payload باید **خودبسنده** باشد (مصرف‌کننده نیاز به کوئری اضافی نداشته باشد).
4. ⚠️ payload ≤ ۸KB؛ بیشتر → ارجاع به Storage (`payload_ref`).
5. 🔒 هیچ دادهٔ حساس خام (رمز، شمارهٔ کامل کارت) در payload.
6. 🔒 نسخه‌گذاری: تغییر Breaking → `version+1` و Listener دوگانه تا Migration.

---

## ۴. Event Bus — معماری داخلی

```
                       ┌─────────────────────────────┐
   dispatch($event) ─▶ │      EventDispatcher        │
                       │  ┌───────────────────────┐  │
                       │  │ ListenerRegistry      │  │  exact / wildcard / class-hierarchy
                       │  │ (sorted by priority)  │  │
                       │  └───────────┬───────────┘  │
                       │              ▼              │
                       │  ┌───────────────────────┐  │
                       │  │ Resolution            │  │  class-string|callable|array|Job
                       │  └───────────┬───────────┘  │
                       │              ▼              │
                       │  ┌───────────────────────┐  │
                       │  │ Routing               │  │  ShouldQueue? ShouldBroadcast?
                       │  └──┬────────┬────────┬──┘  │
                       └─────┼────────┼────────┼─────┘
                             ▼        ▼        ▼
                        [Sync]    [Queue]  [Broadcast]
                             │        │        │
                       Listener   Job Push  Realtime Channel
                             │
                     ┌───────┴────────┐
                     ▼                ▼
              [Stop Propagation]  [Exception Handler]
                     │                │
                     └───▶ next       └──▶ log + continue (configurable)
```

### ۴٫۱ الگوریتم Dispatch

```
function dispatch(event, payload, halt):
  1. normalized = normalize(event)            # object → class-string + name
  2. listeners  = registry.resolve(normalized)
        = exact(class) ∪ exact(eventName) ∪ wildcard(match) ∪ interface/parent(class)
        sorted by (priority DESC, registeredAt ASC)     # قطعی
  3. for each listener:
        if listener is queued       → queue.push(listener, event) ; continue
        if listener is broadcast    → broadcast.publish(event)    ; continue
        result = container.call(listener, [event, payload])
        if halt and result is not null → return result
        if event.propagationStopped    → break
  4. collect results → return halt ? last : results[]
```

### ۴٫۲ Wildcard Matching
```
'shop.order.*'      → shop.order.paid, shop.order.placed
'shop.*.paid'       → shop.order.paid, shop.invoice.paid
'*'                 → همه (فقط برای Observability/Debug)
'shop.order.paid'   → دقیق
```
🔒 تطبیق با **Segment Trie** (نه regex) → O(k) که k = تعداد segmentها.

### ۴٫۳ Priority
| بازه | معنا |
|---|---|
| `1000..∞` | Framework-critical (Error, Audit) |
| `100..999` | Security/Transaction |
| `0` | پیش‌فرض |
| `-999..-1` | Background/Analytics |

---

## ۵. Listener

### ۵٫۱ انواع Listener

```php
// ۱) Closure
$events->listen(OrderWasPaid::class, fn (OrderWasPaid $e) => $loyalty->award($e->aggregateId));

// ۲) کلاس با handle()
final class SendInvoiceOnOrderPaid
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function handle(OrderWasPaid $event): void
    {
        $this->mailer->send(new InvoiceMail($event->aggregateId));
    }
}

// ۳) Listener صف‌شونده
final class ReindexSearchOnProductUpdated implements ShouldQueue
{
    public string $queue = 'search.low';
    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 10;

    public function handle(ProductWasUpdated $event): void { /* … */ }
    public function failed(\Throwable $e): void { /* جبران */ }
}

// ۴) Subscriber (چند رویداد)
final class OrderEventSubscriber implements EventSubscriber
{
    public function subscribe(DispatcherInterface $events): array
    {
        return [
            OrderWasPlaced::class  => [$this, 'onPlaced'],
            OrderWasPaid::class    => [$this, 'onPaid'],
            'shop.order.*'         => [$this, 'onAny'],
        ];
    }
    public function onPlaced(OrderWasPlaced $e): void { /* … */ }
    public function onPaid(OrderWasPaid $e): void { /* … */ }
    public function onAny(object $e): void { /* … */ }
}
```

### ۵٫۲ قواعد Listener 🔒
| # | قاعده |
|---|---|
| LS1 | Listener باید **Idempotent** باشد (اجرای دوباره = همان نتیجه) |
| LS2 | Listener نباید روی `halt` تکیه کند مگر صریحاً طراحی شده باشد |
| LS3 | Listener صف‌شونده نباید به Session/Request جاری وابسته باشد |
| LS4 | Listener نباید Aggregate ماژول دیگر را تغییر دهد (فقط فرمان به آن ماژول) |
| LS5 | خطا در Listener پیش‌فرض = لاگ + ادامه (`events.stop_on_error = false`) |
| LS6 | زمان اجرای Listener در Trace ثبت می‌شود؛ > ۱۰۰ms → هشدار |
| LS7 | ممنوعیت Dispatch رویداد جدید داخل Listener صف‌شونده بدون Outbox |

---

## ۶. Transactional Outbox 🔒 (سازگاری DB + Event)

### ۶٫۱ مسئله
```php
// ❌ ناسازگار: اگر publish بعد از commit شکست خورد، رویداد گم می‌شود
$this->db->transaction(fn() => $this->orders->save($order));
$this->events->dispatch(new OrderWasPaid(...));
```

### ۶٫۲ راه‌حل Outbox
```
┌─────────────────────────── Transaction ──────────────────────────┐
│ 1. UPDATE shop_orders SET status='paid'                          │
│ 2. INSERT INTO tvn_outbox (id, aggregate, event_name, payload,   │
│                            trace_id, created_at, status='new')   │
└──────────────────────────────────────────────────────────────────┘
                              │ commit
                              ▼
        [Outbox Relay / Dispatcher]  ← در همان درخواست (fast path) یا Worker
                              │
              ┌───────────────┼────────────────┐
              ▼               ▼                ▼
        Local Listeners   Broker (Redis/    Broadcast
                            NATS/HTTP)
              │
              └──▶ UPDATE tvn_outbox SET status='sent', sent_at=NOW()
```

### ۶٫۳ Schema جدول Outbox

```sql
CREATE TABLE tvn_outbox (
  id              CHAR(26)      NOT NULL PRIMARY KEY,   -- ULID
  aggregate_type  VARCHAR(100)  NOT NULL,
  aggregate_id    VARCHAR(64)   NOT NULL,
  event_name      VARCHAR(150)  NOT NULL,
  event_version   SMALLINT      NOT NULL DEFAULT 1,
  payload         JSON          NOT NULL,
  headers         JSON          NULL,
  trace_id        CHAR(32)      NULL,
  causation_id    CHAR(26)      NULL,
  correlation_id  CHAR(26)      NULL,
  sequence        BIGINT        NOT NULL,
  idempotency_key CHAR(64)      NOT NULL,
  status          VARCHAR(16)   NOT NULL DEFAULT 'new',  -- new|sending|sent|failed|dead
  attempts        TINYINT       NOT NULL DEFAULT 0,
  available_at    DATETIME(3)   NOT NULL,
  created_at      DATETIME(3)   NOT NULL,
  sent_at         DATETIME(3)   NULL,
  last_error      TEXT          NULL,
  UNIQUE KEY uk_outbox_idem (idempotency_key),
  KEY idx_outbox_pending (status, available_at),
  KEY idx_outbox_agg (aggregate_type, aggregate_id, sequence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### ۶٫۴ Relay
| حالت | توضیح | تأخیر |
|---|---|---|
| `inline` | در همان درخواست پس از commit (پیش‌فرض برای مونولیت) | ~0 |
| `worker` | `tvn outbox:relay` به‌صورت daemon | ≤ ۱s |
| `cron` | هر دقیقه (fallback) | ≤ ۶۰s |
| `cdc` | از Binlog/WAL (پیشرفته، اختیاری) | ≤ ۱۰۰ms |

### ۶٫۵ قواعد Outbox
- 🔒 Batch Size ≤ ۱۰۰، با `FOR UPDATE SKIP LOCKED` (MySQL 8/PG) برای Workerهای موازی.
- 🔒 Retry با Backoff نمایی؛ پس از ۱۰ تلاش → `dead` + Alert.
- 🔒 Idempotency Key یونیک → مصرف‌کننده باید `processed_events` داشته باشد.
- 🔒 پاکسازی رکوردهای `sent` قدیمی‌تر از `outbox.retention_days` (پیش‌فرض ۷).

---

## ۷. جدول مصرف‌شده (Idempotency در مصرف‌کننده)

```sql
CREATE TABLE tvn_inbox (
  idempotency_key CHAR(64)   NOT NULL PRIMARY KEY,
  event_name      VARCHAR(150) NOT NULL,
  handled_at      DATETIME(3) NOT NULL,
  handler         VARCHAR(190) NOT NULL
) ENGINE=InnoDB;
```
```php
final class IdempotentHandler
{
    public function run(string $key, string $handler, \Closure $work): bool
    {
        return $this->db->transaction(function () use ($key, $handler, $work): bool {
            try {
                $this->db->table('tvn_inbox')->insert([
                    'idempotency_key' => $key, 'event_name' => $handler,
                    'handled_at' => Clock::utcNow(), 'handler' => $handler,
                ]);
            } catch (UniqueConstraintException) {
                return false;   // قبلاً پردازش شده
            }
            $work();
            return true;
        });
    }
}
```

---

## ۸. CQRS

```
        ┌─────────── Write Side ───────────┐      ┌────────── Read Side ──────────┐
Command ─▶ Validator ─▶ UseCase ─▶ Aggregate ─▶ Repository ─▶ DB (Normalized)
                                    │
                                    └─▶ Domain Event ─▶ Outbox ─▶ Projector ─▶ Read Model
                                                                        │
Query ─────────────────────────────────────────────────────────────▶ ReadModel ─▶ DTO ─▶ View/API
```

| عنصر | توضیح |
|---|---|
| `Command` | DTO دستوری (بدون منطق) — `PlaceOrderCommand` |
| `CommandHandler` | اجرای UseCase در تراکنش |
| `Query` | DTO پرسش — `OrderListQuery{filters, sort, page}` |
| `QueryHandler` | خواندن بهینه (بدون Domain) — می‌تواند مستقیم SQL باشد |
| `ReadModel` | جدول/View/Cache ساختار‌یافته برای خواندن سریع |
| `Projector` | به‌روزرسانی ReadModel از رویدادها (Idempotent) |

```php
final readonly class OrderListQuery
{
    public function __construct(
        public ?int $customerId = null,
        public ?OrderStatus $status = null,
        public ?DateRange $placedBetween = null,
        public SortField $sort = SortField::PlacedAt,
        public SortDirection $direction = SortDirection::Desc,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}

final class OrderListQueryHandler
{
    /** @return PaginatedResult<OrderListItem> */
    public function handle(OrderListQuery $q): PaginatedResult
    {
        return $this->readModel->connection()->table('shop_orders_read')
            ->when($q->customerId, fn($b) => $b->where('customer_id', $q->customerId))
            ->when($q->status,     fn($b) => $b->where('status', $q->status->value))
            ->when($q->placedBetween, fn($b) => $b->whereBetween('placed_at', $q->placedBetween->toPair()))
            ->orderBy($q->sort->column(), $q->direction->value)
            ->paginate(perPage: $q->perPage, page: $q->page)
            ->map(fn(array $r): OrderListItem => OrderListItem::fromRow($r));
    }
}
```

---

## ۹. Event Sourcing (اختیاری — موتور `event-store`)

| عنصر | توضیح |
|---|---|
| Event Store | جدول append-only با `stream_id`, `version`, `event_name`, `payload` |
| Aggregate Rehydration | بازسازی با Replay رویدادها |
| Snapshot | هر N رویداد (پیش‌فرض ۵۰) برای سرعت |
| Projection | ساخت Read Model |
| Upcasting | تبدیل نسخهٔ قدیمی رویداد به جدید |

```sql
CREATE TABLE tvn_events (
  stream_id     CHAR(64)   NOT NULL,
  version       INT        NOT NULL,
  event_id      CHAR(26)   NOT NULL,
  event_name    VARCHAR(150) NOT NULL,
  payload       JSON       NOT NULL,
  metadata      JSON       NULL,
  recorded_at   DATETIME(3) NOT NULL,
  PRIMARY KEY (stream_id, version),
  UNIQUE KEY uk_event_id (event_id)
) ENGINE=InnoDB;

CREATE TABLE tvn_snapshots (
  stream_id   CHAR(64) NOT NULL PRIMARY KEY,
  version     INT      NOT NULL,
  state       JSON     NOT NULL,
  taken_at    DATETIME(3) NOT NULL
);
```

> ⚠️ Event Sourcing **پیش‌فرض نیست**. فقط برای دامنه‌هایی با نیاز واقعی به Audit کامل/Undo
> (مانند حسابداری ERP یا BPMS) و با ADR فعال می‌شود.

---

## ۱۰. الگوهای مقاومت (Resiliency Patterns)

| الگو | تنظیمات پیش‌فرض | توضیح |
|---|---|---|
| Retry | ۳ بار، Exponential(1s,2s,4s) + Jitter ±20٪ | فقط برای خطاهای موقت |
| Backoff | `exponential` \| `fixed` \| `custom` | در Job/Listener |
| Dead Letter Queue | `queue.default.dlq` | پس از MaxTries |
| Timeout | ۶۰s برای Job، ۵s برای Listener همزمان | |
| Circuit Breaker | ۵ خطا در ۶۰s → Open ۳۰s → Half-Open | برای Gatewayها |
| Idempotency | Key + Inbox Table | اجباری برای مصرف‌کننده |
| Ordering | Per-Aggregate Sequence | در Outbox و Broker |
| Poison Message | پس از ۱ تلاش ناموفق با `deserialize` → DLQ فوری | |
| Backpressure | `queue.max_inflight` | در Worker |

---

## ۱۱. رویدادهای هسته (Framework Events)

| رویداد | زمان | payload |
|---|---|---|
| `kernel.booting` | قبل از register | `providers[]` |
| `kernel.booted` | پس از boot | `bootTimeMs` |
| `request.received` | شروع | `method, path, ip` |
| `route.matched` | پس از تطبیق | `routeName, params` |
| `request.handled` | پس از ساخت پاسخ | `status, durationMs` |
| `response.sent` | پس از send | `size` |
| `kernel.terminating` | قبل از terminate | — |
| `exception.occurred` | هنگام خطا | `refCode, class` |
| `query.executed` | هر کوئری (dev) | `sql, bindings, timeMs` |
| `cache.hit` / `cache.miss` | هر دسترسی | `key, store` |
| `queue.job.processed` / `failed` | هر Job | `id, attempts` |
| `auth.login.succeeded` / `failed` | احراز هویت | `userId, ip, guard` |
| `config.changed` | تغییر پیکربندی | `keys[]` |
| `module.installed` | نصب ماژول | `moduleId, version` |

---

## ۱۲. تنظیمات

```php
// config/event.php
return [
    'default_dispatcher' => 'sync',        // sync|queue|outbox
    'stop_on_error'      => false,         // خطا در Listener → ادامه
    'log_listeners'      => env_bool('TVN_EVENT_LOG_LISTENERS', false),
    'max_listeners'      => 200,           // حفاظت از حلقه
    'wildcard'           => true,
    'discovery'          => [
        'cache'  => true,
        'path'   => storage_path('cache/events.php'),
    ],
    'outbox' => [
        'enabled'      => true,
        'table'        => 'tvn_outbox',
        'relay'        => 'inline',        // inline|worker|cron
        'batch_size'   => 100,
        'max_attempts' => 10,
        'backoff'      => 'exponential',
        'retention_days' => 7,
    ],
    'inbox' => [
        'enabled' => true,
        'table'   => 'tvn_inbox',
        'ttl_days' => 30,
    ],
    'broadcast' => [
        'default' => 'redis',              // redis|sse|websocket|log|null
        'channels' => [],
    ],
];
```

---

## ۱۳. معیار پذیرش 🧪

- AC-EVT-01: dispatch ۱۰٬۰۰۰ رویداد همزمان (بدون Listener) ≤ ۵۰ms.
- AC-EVT-02: Listener با اولویت بالاتر زودتر اجرا می‌شود (تست ترتیب قطعی).
- AC-EVT-03: Wildcard `shop.order.*` فقط رویدادهای مطابقی را می‌گیرد.
- AC-EVT-04: `ShouldQueue` باعث Push به صف می‌شود نه اجرای همزمان.
- AC-EVT-05: Outbox — در تراکنش rollback شده، هیچ رویدادی منتشر نمی‌شود.
- AC-EVT-06: مصرف دوبارهٔ همان `idempotencyKey` → اثر جانبی تکرار نمی‌شود.
- AC-EVT-07: Relay با ۳ Worker موازی هیچ رویدادی را دوباره پردازش نمی‌کند (SKIP LOCKED).
- AC-EVT-08: پس از MaxAttempts → `dead` + Alert Event.
- AC-EVT-09: `stopPropagation()` جلوی Listenerهای بعدی را می‌گیرد.
- AC-EVT-10: Projector از ۱۰۰۰ رویداد ReadModel یکسان با حالت مستقیم می‌سازد (Convergence Test).
