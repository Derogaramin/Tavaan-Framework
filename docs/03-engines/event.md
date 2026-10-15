# DOC-033 — موتور Event | رویداد و Outbox

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-033 |
| Engine ID | `tavaan.engine.event` |
| Namespace | `Tavaan\Engine\Event` |
| اولویت | **P0** — فاز M1 |
| وابستگی | `container`, `config`؛ نرم: `queue`, `database` (برای Outbox) |
| Facade | `Event` → `events` |
| جداول | `tvn_outbox`, `tvn_inbox` |
| LOC | ۹۰۰ |
| مرتبط | DOC-023 (معماری ED) |

---

## ۱. هدف

یک Event Bus سریع، قطعی و قابل‌اتکا که هم **همزمان** (در درخواست) و هم **ناهمزمان**
(از طریق Queue/Outbox) کار کند و پایهٔ ارتباط بین ماژول‌ها باشد.

- ✅ Listener با Priority، Wildcard، Subscriber، Class/Callable/Job
- ✅ صف‌شدن خودکار با `ShouldQueue`
- ✅ Transactional Outbox + Relay (inline/worker/cron)
- ✅ Inbox برای Idempotency مصرف‌کننده
- ✅ Broadcast (`ShouldBroadcast`) به موتور `realtime`
- ✅ Event Discovery + Cache
- ✅ Stop Propagation، `until()`/`halt`
- ✅ Fake برای تست (`Event::fake()`)

---

## ۲. مفاهیم

| واژه | تعریف |
|---|---|
| **Event** | شیء/نام رویداد (گذشته‌نگر) |
| **Listener** | پردازشگر رویداد |
| **Subscriber** | کلاسی که چند رویداد را ثبت می‌کند |
| **Wildcard** | الگوی `shop.order.*` |
| **Outbox** | جدول واسط برای سازگاری DB+Event |
| **Relay** | فرایند انتقال Outbox → مقصد |
| **Inbox** | جدول Idempotency مصرف‌کننده |
| **Envelope** | ساختار استاندارد رویداد (DOC-023 §3) |

---

## ۳. معماری

```
                     ┌────────────────────────────────────────┐
  dispatch($e) ────▶ │            EventDispatcher             │
                     │                                        │
   ┌──────────────┐  │  ┌────────────────────────────────┐    │
   │ListenerRegistry│◀─┼─│ Resolver (exact/wildcard/hier) │    │
   │  SegmentTrie │  │  └───────────────┬────────────────┘    │
   └──────────────┘  │                  ▼                     │
                     │  ┌────────────────────────────────┐    │
                     │  │ Router: sync | queue | broadcast│   │
                     │  └──┬──────────┬──────────┬───────┘    │
                     └─────┼──────────┼──────────┼────────────┘
                           ▼          ▼          ▼
                    ┌──────────┐ ┌─────────┐ ┌───────────┐
                    │CallListener│ │QueuePush│ │Broadcaster│
                    └────┬─────┘ └─────────┘ └───────────┘
                         ▼
                  ┌────────────┐     ┌──────────────┐
                  │OutboxWriter│────▶│ OutboxRelay  │──▶ Broker/HTTP
                  │(in-tx)     │     └──────┬───────┘
                  └────────────┘            ▼
                                     ┌──────────────┐
                                     │ InboxGuard   │ (idempotency)
                                     └──────────────┘
```

### ۳٫۱ کلاس‌ها

| کلاس | مسئولیت | LOC |
|---|---|---|
| `EventDispatcher` | پیاده‌سازی `DispatcherInterface` | 260 |
| `ListenerRegistry` | ثبت و Resolve (Segment Trie) | 220 |
| `WildcardMatcher` | تطبیق الگو (Trie) | 110 |
| `ListenerResolver` | تبدیل به callable | 90 |
| `QueuedListenerHandler` | push به Queue | 70 |
| `OutboxWriter` | درج در تراکنش | 90 |
| `OutboxRelay` | Batch dispatch | 140 |
| `InboxGuard` | idempotency | 60 |
| `EventFactory` | ساخت Envelope | 80 |
| `EventDiscovery` | اسکن Listenerها + Cache | 110 |
| `EventFake` | تست | 120 |
| `EventServiceProvider` | ثبت | 70 |

---

## ۴. API

```php
// ثبت
Event::listen(OrderWasPaid::class, SendInvoiceListener::class, priority: 100);
Event::listen('shop.order.*', AuditOrderListener::class);
Event::listen('shop.order.paid', fn (OrderWasPaid $e) => $log->info('paid', ['id' => $e->aggregateId]));
Event::subscribe(OrderEventSubscriber::class);

// Dispatch
Event::dispatch(new OrderWasPaid(/*…*/));
Event::dispatch('shop.order.paid', ['orderId' => 1]);
Event::until(MaintenanceCheck::class);                 // halt on first non-null
Event::push(new OrderWasPaid(/*…*/));                   // صریحاً به صف

// Introspection
Event::hasListeners('shop.order.paid');                 // true
Event::getListeners('shop.order.paid');                 // list<ResolvedListener>
Event::forget('shop.order.paid');

// تست
Event::fake();
Event::fake([OrderWasPaid::class]);
Event::assertDispatched(OrderWasPaid::class, fn ($e) => $e->aggregateId === '01J...');
Event::assertNotDispatched(OrderWasCanceled::class);
Event::assertDispatchedTimes(OrderWasPaid::class, 1);
```

### ۴٫۱ Interface
→ `core/Contracts/Events/DispatcherInterface` (DOC-014 §2.2)

---

## ۵. الگوریتم‌ها

### ۵٫۱ Segment Trie برای Wildcard
```
ساخت:
  insert('shop.order.paid', listener)
  insert('shop.order.*',   listener)
  insert('shop.*.paid',    listener)

  root
   └─ shop
       ├─ order
       │   ├─ paid → [L1]
       │   └─ *    → [L2]
       └─ *
           └─ paid → [L3]

تطبیق 'shop.order.paid':
  traverse: shop → order → paid → collect [L1]
                 → order → *    → collect [L2]
            shop → *    → paid → collect [L3]
  result = merge + sort(priority DESC, seq ASC) + unique
```
**پیچیدگی:** ساخت O(n·k)، تطبیق O(3^k) در بدترین حالت (k = تعداد segment، معمولاً ≤ ۴) → عملاً O(1).

```php
final class WildcardMatcher
{
    private TrieNode $root;

    public function add(string $pattern, mixed $listener, int $priority, int $seq): void
    {
        $node = $this->root;
        foreach (\explode('.', $pattern) as $seg) {
            $node = $node->child($seg);
        }
        $node->listeners[] = new RegisteredListener($listener, $priority, $seq, $pattern);
    }

    /** @return list<RegisteredListener> مرتب‌شده */
    public function match(string $eventName): array
    {
        $found = [];
        $this->walk($this->root, \explode('.', $eventName), 0, $found);

        \usort($found, static fn (RegisteredListener $a, RegisteredListener $b): int
            => [$b->priority, -$a->seq] <=> [$a->priority, -$b->seq]);   // قطعی

        return $found;
    }

    private function walk(TrieNode $node, array $segments, int $i, array &$found): void
    {
        if ($i === \count($segments)) {
            foreach ($node->listeners as $l) { $found[] = $l; }
            foreach ($node->child('*')->listeners as $l) { $found[] = $l; }   // catch-all در این سطح
            return;
        }
        $seg = $segments[$i];
        if (isset($node->children[$seg])) { $this->walk($node->children[$seg], $segments, $i + 1, $found); }
        if (isset($node->children['*']))  { $this->walk($node->children['*'],  $segments, $i + 1, $found); }
    }
}
```

### ۵٫۲ ترتیب Resolve (Exact → Wildcard → Hierarchy)
```
1. exact class-string:   listeners[OrderWasPaid::class]
2. exact event name:     listeners['shop.order.paid']
3. wildcard trie:        matcher.match('shop.order.paid')
4. hierarchy:            برای هر interface و parent class رویداد
5. '*' global:           فقط اگر config.event.global_listeners = true
→ ادغام + unique (بر اساس identity) + sort(priority DESC, seq ASC)
```

### ۵٫۳ Outbox Write (در تراکنش)
```php
final class OutboxWriter
{
    public function write(DomainEventInterface $event, ?ConnectionInterface $connection = null): string
    {
        $connection ??= $this->connections->default();

        $row = [
            'id'              => $event->eventId,
            'aggregate_type'  => $event->aggregateType,
            'aggregate_id'    => $event->aggregateId,
            'event_name'      => $event->eventName(),
            'event_version'   => $event->version,
            'payload'         => \json_encode($event->jsonSerialize(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'headers'         => \json_encode($this->headers->current(), JSON_THROW_ON_ERROR),
            'trace_id'        => $event->traceId,
            'causation_id'    => $event->causationId,
            'correlation_id'  => $event->correlationId,
            'sequence'        => $event->sequence,
            'idempotency_key' => $event->idempotencyKey,
            'status'          => 'new',
            'available_at'    => Clock::utcNow(),
            'created_at'      => Clock::utcNow(),
        ];

        $connection->table($this->table)->insert($row);
        return $event->eventId;
    }
}
```
🔒 این `insert` باید **درون همان تراکنش** تغییر Aggregate باشد.

### ۵٫۴ Relay (Batch + SKIP LOCKED)
```php
public function relay(int $batchSize = 100): RelayResult
{
    $processed = $failed = 0;

    while (true) {
        $rows = $this->connection->transaction(function () use ($batchSize): array {
            $rows = $this->connection->table($this->table)
                ->where('status', 'new')
                ->orWhere(fn ($q) => $q->where('status', 'failed')->where('available_at', '<=', Clock::utcNow()))
                ->orderBy('sequence')
                ->limit($batchSize)
                ->lockForUpdateSkipLocked()
                ->get();

            if ($rows === []) { return []; }

            $this->connection->table($this->table)
                ->whereIn('id', \array_column($rows, 'id'))
                ->update(['status' => 'sending', 'attempts' => $this->raw('attempts + 1')]);

            return $rows;
        });

        if ($rows === []) { break; }

        foreach ($rows as $row) {
            try {
                $this->publisher->publish($row);
                $this->mark('sent', $row['id']);
                $processed++;
            } catch (\Throwable $e) {
                $this->handleFailure($row, $e) ? $failed++ : null;
            }
        }

        if (\count($rows) < $batchSize) { break; }
        if ($this->shouldStop()) { break; }   // memory/time/stop signal
    }

    return new RelayResult($processed, $failed);
}
```
🔒 MySQL 8.0+/PostgreSQL 9.5+: `FOR UPDATE SKIP LOCKED`. برای SQLite: fallback به `UPDATE ... WHERE id IN (SELECT ... LIMIT n)` با تراکنش انحصاری.

### ۵٫۵ Backoff برای Outbox
```
attempt 1 → 1s, 2 → 2s, 3 → 4s, 4 → 8s, … , 10 → 512s + jitter(±20%)
پس از max_attempts → status = 'dead' + Event `outbox.event.dead`
```

---

## ۶. پیکربندی

```php
// engines/event/config/event.php
return [
    'default_mode'      => env_string('TVN_EVENT_MODE', 'sync'),   // sync|outbox|queue
    'stop_on_error'     => false,
    'max_listeners'     => 500,
    'wildcard'          => true,
    'global_listeners'  => false,      // '*'
    'log_listeners'     => env_bool('TVN_EVENT_DEBUG', false),

    'discovery' => [
        'enabled' => true,
        'cache'   => storage_path('cache/events.php'),
        'paths'   => [base_path('modules'), base_path('app/src')],
        'suffix'  => 'EventServiceProvider',
    ],

    'outbox' => [
        'enabled'        => true,
        'table'          => 'tvn_outbox',
        'connection'     => null,
        'relay'          => 'inline',     // inline|worker|cron
        'batch_size'     => 100,
        'max_attempts'   => 10,
        'backoff'        => 'exponential',
        'base_seconds'   => 1,
        'jitter'         => 0.2,
        'retention_days' => 7,
        'publisher'      => 'local',      // local|redis|nats|http|amqp
        'publishers'     => [
            'local' => ['driver' => 'local'],
            'redis' => ['driver' => 'redis', 'connection' => 'default', 'stream' => 'tavaan:events'],
            'http'  => ['driver' => 'http', 'endpoints' => [], 'timeout' => 5, 'sign' => true],
        ],
    ],

    'inbox' => [
        'enabled'  => true,
        'table'    => 'tvn_inbox',
        'ttl_days' => 30,
    ],

    'broadcast' => [
        'default'  => env_string('TVN_BROADCAST_DRIVER', 'log'),
        'channels' => [],
    ],

    'queue_listeners' => [
        'connection' => null,             // پیش‌فرض = queue.default
        'queue'      => 'events',
    ],
];
```

---

## ۷. قراردادها
- `DispatcherInterface`, `ShouldQueue`, `ShouldBroadcast`, `EventSubscriber`, `DomainEventInterface`
- `OutboxPublisherInterface` (برای افزودن Broker جدید)

---

## ۸. نقاط توسعه
| نوع | نام |
|---|---|
| Extend | `Event::extend('publisher.nats', fn() => new NatsPublisher())` |
| Listener | هر کلاس با `handle()` |
| Event | هر شیء (بدون نیاز به Base Class) |
| Hook | `events.dispatching` / `events.dispatched` (برای Observability) |

---

## ۹. امنیت

| تهدید | کنترل |
|---|---|
| اجرای Listener دلخواه از ورودی کاربر | 🔒 Listener فقط از ثبت کد؛ `dispatch` با نام رشته‌ای محدود به allowlist |
| Payload حساس در Outbox | `EventSanitizer` — حذف/mask فیلدهای `secret_keys` |
| Replay Attack | Idempotency Key + Inbox |
| Poison Event (deserialize شکست) | → DLQ فوری بدون retry |
| امضای Publisher | HMAC روی payload در publisher نوع `http` |
| DoS با Listener زیاد | `max_listeners` + Timeout هر Listener |

```php
Event::allowDispatch(['shop.*', 'auth.*', 'kernel.*']);   // allowlist نام‌های رشته‌ای
```

---

## ۱۰. کارایی

| عملیات | هدف |
|---|---|
| dispatch بدون Listener | ≤ ۱µs |
| dispatch با ۵ Listener همزمان | ≤ ۵۰µs (بدون زمان Listenerها) |
| Resolve wildcard (۴ segment، ۲۰۰ الگو) | ≤ ۵µs |
| ۱۰٬۰۰۰ dispatch | ≤ ۵۰ms |
| Outbox write (در تراکنش موجود) | ≤ ۰٫۳ms |
| Relay batch 100 | ≤ ۳۰۰ms |
| Discovery cache load | ≤ ۰٫۲ms |

---

## ۱۱. خطاها

| استثنا | کد | MessageId |
|---|---|---|
| `ListenerException` | TEV001 | `event.listener_failed` |
| `MaxListenersExceededException` | TEV002 | `event.max_listeners` |
| `OutboxUnavailableException` | TEV003 | `event.outbox_unavailable` |
| `EventNotAllowlistedException` | TEV004 | `event.not_allowlisted` |
| `PoisonEventException` | TEV005 | `event.poison` |

---

## ۱۲. تست

| ID | سناریو |
|---|---|
| T-01 | ترتیب Priority قطعی (۳ Listener با priority 100/0/50) |
| T-02 | Wildcard `shop.order.*` فقط مطابقی‌ها |
| T-03 | `until()` در اولین پاسخ غیر-null متوقف می‌شود |
| T-04 | `stopPropagation()` جلوی بقیه را می‌گیرد |
| T-05 | Listener با خطا → لاگ + ادامه (`stop_on_error=false`) |
| T-06 | `ShouldQueue` → push به FakeQueue، بدون اجرای همزمان |
| T-07 | Outbox در تراکنش rollback → ۰ رکورد |
| T-08 | Outbox در تراکنش commit → ۱ رکورد + Relay → `sent` |
| T-09 | Inbox: مصرف دوبارهٔ همان key → بدون اثر جانبی |
| T-10 | ۳ Relay موازی → هر رویداد فقط یک‌بار `sent` |
| T-11 | پس از `max_attempts` → `dead` + Event |
| T-12 | `Event::fake()` → `assertDispatched` درست |
| T-13 | Discovery Cache → Listenerها بدون اسکن ثبت می‌شوند |
| T-14 | Subscriber با چند رویداد |
| T-15 | Hierarchical: Listener روی Interface، رویداد پیاده‌سازی را می‌گیرد |

---

## ۱۳. معیار پذیرش 🧪

- AC-EVT-01..10 (مطابق DOC-023 §13).
- AC-EVT-11: Benchmark §10 پاس شود.
- AC-EVT-12: `tvn event:list` همهٔ رویدادها و Listenerها را با اولویت نمایش دهد.
- AC-EVT-13: `tvn outbox:relay --once` با `--batch=100` کار کند و آمار بدهد.

---

## ۱۴. مراجع

| منبع | الهام | تفاوت |
|---|---|---|
| Laravel Events | API ساده، `fake()` | افزودن Outbox/Inbox، Priority، Wildcard Trie |
| Symfony EventDispatcher | Priority، Subscriber | حذف `Event::setDispatcher` (منسوخ)، Envelope استاندارد |
| `league/event` | سادگی | افزودن Distributed features |
| microservices.io — Outbox | الگو | پیاده‌سازی با SKIP LOCKED و Relay چندحالته |
| CloudEvents 1.0 | ساختار Envelope | فیلدهای `causationId/correlationId/sequence` افزوده |

---

## ۱۵. بلوک ماشین‌خوان 🧠

```yaml
# machine-readable: tavaan.engine.event.v1
engine:
  id: tavaan.engine.event
  namespace: Tavaan\Engine\Event
  path: engines/event
  priority: P0
  phase: M1
  depends_on: [container, config]
  soft_depends_on: [queue, database, realtime]
  extensions: {required: [], optional: []}
  facade: {name: Event, accessor: events}
  tables: [tvn_outbox, tvn_inbox]
  events_emitted: [events.dispatching, events.dispatched, outbox.event.sent,
                   outbox.event.failed, outbox.event.dead]
  config_file: engines/event/config/event.php
  public_api:
    - class: EventDispatcher
      methods: [listen, subscribe, dispatch, until, push, hasListeners, getListeners,
                forget, extend, fake, assertDispatched]
    - class: OutboxRelay
      methods: [relay, retryDead, purge]
  loc_estimate: 900
  acceptance:
    - {id: AC-EVT-05, test: 'engines/event/tests/Feature/OutboxTest.php::testRollbackPublishesNothing'}
    - {id: AC-EVT-06, test: 'engines/event/tests/Feature/InboxIdempotencyTest.php'}
```
