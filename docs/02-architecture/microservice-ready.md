# DOC-024 — آمادگی مایکروسرویس (Microservice-Ready Architecture)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-024 |
| وضعیت | Approved v1.0 |
| والد | DOC-022، DOC-023 |
| اصل | *ماژول را سرویس نکن، مگر مجبور شوی — اما از روز اول آماده باش.* |

---

## ۱. استراتژی: از مونولیت ماژولار تا سرویس

```
┌──────────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  S0: Simple  │───▶│ S1: Modular  │───▶│ S2: Outbox + │───▶│ S3: Services │
│     MVC      │    │   Monolith   │    │  Read Models │    │  Extracted   │
└──────────────┘    └──────────────┘    └──────────────┘    └──────────────┘
   پروژه کوچک          محصول متوسط         مقیاس بزرگ           سازمانی/تیمی
```

| مرحله | علامت ورود | چه چیزی اضافه می‌شود |
|---|---|---|
| S0 → S1 | بیش از ۳ دامنهٔ مستقل | ماژول‌بندی، مرزها، Manifest |
| S1 → S2 | نیاز به Read Model سریع / تیم‌های موازی | CQRS، Outbox، Projection |
| S2 → S3 | تیم مستقل + نیاز به Deploy/Scale مستقل | استخراج سرویس، Broker شبکه‌ای، Service Discovery |

> 🔒 کد Domain در همهٔ مراحل **یکسان** می‌ماند. فقط Adapter و Deployment تغییر می‌کند.

---

## ۲. شش اصل آمادگی (از روز اول رعایت می‌شود)

### R1 — دامنهٔ مستقل از فریمورک
```php
// ✅ Domain بدون هیچ use از Engine
namespace Modules\Shop\Domain\Entities;
final class Order { /* … */ }

// ❌ وابسته
use Tavaan\Engine\Database\ORM\Model;
final class Order extends Model { /* … */ }
```
برای این کار ORM تَوان دو حالت دارد:
- **Active Record** (ساده، برای S0/S1)
- **Repository + Plain Domain Object** (برای S2/S3) ← توصیه برای ماژول‌های قابل استخراج

### R2 — ارتباط فقط با Contract یا Event
```
Modules\Blog ──(Contract)──▶ Modules\Shop\Contracts\ProductReaderInterface
Modules\Blog ◀──(Event)──── shop.order.paid
```

### R3 — مالکیت داده
| قاعده |
|---|
| هر جدول یک مالک دارد |
| Join بین‌ماژولی ممنوع |
| کلید خارجی بین‌ماژولی ممنوع (Integrity در Application) |
| شناسه‌ها توزیعی (ULID) تا در سرویس مستقل هم یکتا باشند |

### R4 — Idempotency
هر نوشتن بین‌مرزی (Event مصرفی، API Call) باید `Idempotency-Key` داشته باشد.

### R5 — Observability توزیعی
`traceparent` در همهٔ فراخوانی‌ها (HTTP، Event، Queue) منتشر و بازیابی شود.

### R6 — پیکربندی خارجی
هیچ تنظیمی Hardcode نشود؛ همه از Config/ENV/Config Server.

---

## ۳. معماری مرجع سرویس مستقل

```
┌─────────────────────────────────────────────────────────┐
│  shop-service (همان ماژول، Deploy مستقل)                │
│                                                         │
│  ┌───────────┐  ┌────────────┐  ┌───────────────────┐  │
│  │ HTTP API  │  │ Event      │  │ gRPC/Message      │  │
│  │ Adapter   │  │ Consumer   │  │ Adapter (اختیاری) │  │
│  └─────┬─────┘  └─────┬──────┘  └─────────┬─────────┘  │
│        └───────────────┼───────────────────┘            │
│                        ▼                                │
│              ┌──────────────────┐                       │
│              │  Application     │  (UseCase/Handler)    │
│              └────────┬─────────┘                       │
│                       ▼                                 │
│              ┌──────────────────┐                       │
│              │     Domain       │  (بدون تغییر)         │
│              └────────┬─────────┘                       │
│                       ▼                                 │
│              ┌──────────────────┐                       │
│              │  Infrastructure  │  DB / Cache / Outbox   │
│              └──────────────────┘                       │
└─────────────────────────────────────────────────────────┘
         │                              ▲
         ▼                              │
   [Database: shop_db]          [Message Broker]
```

---

## ۴. Adapterهای ارتباطی (Transport Adapters)

| Adapter | جهت | پروتکل | موتور |
|---|---|---|---|
| `HttpServerAdapter` | ورودی | REST/JSON | `http`, `api` |
| `HttpClientAdapter` | خروجی | REST/JSON | `http-client` |
| `EventPublisherAdapter` | خروجی | Outbox → Redis/NATS/AMQP/HTTP-Webhook | `event`, `queue` |
| `EventConsumerAdapter` | ورودی | Broker → Local Dispatch | `event` |
| `GrpcAdapter` | دوطرفه | HTTP/2 + Protobuf (اختیاری، ext-grpc) | `api` |
| `CliAdapter` | ورودی | Console | `console` |

### ۴٫۱ Contract مشترک (بدون تغییر Domain)
```php
interface RemoteModuleInterface
{
    /**
     * فراخوانی ماژول/سرویس راه دور با همان امضای Contract محلی.
     *
     * @template T
     * @param class-string<T> $contract
     * @return T
     */
    public function proxy(string $contract, string $service): object;
}
```
```php
// در مونولیت:  Container → پیاده‌سازی محلی
// در سرویس:    Container → RemoteModuleProxy (HTTP/gRPC)
$container->singleton(ProductReaderInterface::class, static fn ($app)
    => $app->make(ModuleResolver::class)->resolve(ProductReaderInterface::class, 'shop'));
```
🔒 **کلید طراحی:** مصرف‌کننده هیچ تفاوتی احساس نمی‌کند.

---

## ۵. الگوهای توزیعی

### ۵٫۱ Saga (Orchestration)
```
                    ┌──────────────────┐
                    │  SagaCoordinator │
                    └────────┬─────────┘
       ┌───────────┬─────────┼──────────┬────────────┐
       ▼           ▼         ▼          ▼            ▼
  [Reserve     [Charge   [Create     [Notify      [Compensate
   Inventory]   Payment]  Shipment]   Customer]     on failure]
```

| عنصر | توضیح |
|---|---|
| `SagaDefinition` | JSON/DSL گام‌ها + جبران هر گام |
| `SagaInstance` | وضعیت جاری با `correlationId` |
| `SagaStep` | `action` + `compensation` + `timeout` |
| Persistence | جدول `tvn_sagas` (State Machine) |
| Recovery | پس از Crash، از آخرین State ادامه |

```php
final class PlaceOrderSaga extends Saga
{
    protected function define(SagaBuilder $b): void
    {
        $b->step('reserve_inventory')
            ->action(ReserveInventoryCommand::class)
            ->onSuccess('charge_payment')
            ->compensate(ReleaseInventoryCommand::class);

        $b->step('charge_payment')
            ->action(ChargePaymentCommand::class)
            ->onSuccess('create_shipment')
            ->compensate(RefundPaymentCommand::class)
            ->timeout(seconds: 300);

        $b->step('create_shipment')
            ->action(CreateShipmentCommand::class)
            ->onSuccess('notify_customer')
            ->compensate(CancelShipmentCommand::class);

        $b->step('notify_customer')
            ->action(NotifyCustomerCommand::class)
            ->onSuccess(SagaOutcome::Completed);
    }
}
```

### ۵٫۲ Schema جدول Saga
```sql
CREATE TABLE tvn_sagas (
  id              CHAR(26)     NOT NULL PRIMARY KEY,
  saga_name       VARCHAR(100) NOT NULL,
  correlation_id  CHAR(26)     NOT NULL,
  current_step    VARCHAR(100) NOT NULL,
  status          VARCHAR(20)  NOT NULL,  -- running|completed|compensating|failed
  context         JSON         NOT NULL,
  attempts        TINYINT      NOT NULL DEFAULT 0,
  started_at      DATETIME(3)  NOT NULL,
  updated_at      DATETIME(3)  NOT NULL,
  completed_at    DATETIME(3)  NULL,
  KEY idx_saga_status (status, updated_at),
  KEY idx_saga_corr (correlation_id)
) ENGINE=InnoDB;
```

### ۵٫۳ Choreography (بدون Orchestrator)
برای جریان‌های ساده: هر سرویس به رویداد دیگر گوش می‌دهد.
🔒 قاعده: اگر بیش از ۴ سرویس درگیر است → Saga Orchestration (قابل ردیابی‌تر).

### ۵٫۴ الگوهای داده
| الگو | کاربرد |
|---|---|
| **Database per Service** | هر سرویس DB خودش |
| **CQRS + Read Model** | کوئری ترکیبی بدون Join بین‌سرویسی |
| **Event Carried State Transfer** | کپی دادهٔ لازم در رویداد |
| **API Composition** | Gateway چند سرویس را صدا زده و ترکیب می‌کند |
| **Cache-Aside** | Read Model در Cache |

---

## ۶. API Gateway

```
Client ──▶ [Tavaan Gateway]
              ├─ AuthN (JWT Validation, mTLS)
              ├─ Rate Limit (per client/IP)
              ├─ Routing (service discovery)
              ├─ Transformation (DTO mapping)
              ├─ Aggregation (API Composition)
              ├─ Circuit Breaker per service
              ├─ Retry with backoff
              ├─ Trace propagation
              └─ Response caching (ETag)
```

| قلم | پیاده‌سازی |
|---|---|
| Routing Table | `config/gateway.php` + Service Registry |
| Service Discovery | Static / DNS / Consul / etcd (Adapter) |
| Load Balancing | Round-Robin, Weighted, Least-Conn, Hash |
| Health | Active Probe + Passive (Circuit Breaker) |
| Contract | OpenAPI تولیدشده از هر سرویس |

---

## ۷. قرارداد رویداد بین سرویس‌ها

```json
{
  "$schema": "https://docs.tavaan.dev/schemas/integration-event.v1.json",
  "event": "shop.order.paid",
  "version": 1,
  "producer": "shop-service",
  "consumers": ["loyalty-service", "notification-service", "analytics"],
  "payload_schema": {
    "type": "object",
    "required": ["orderId", "amount", "currency", "customerId", "paidAt"],
    "properties": {
      "orderId":    {"type": "string", "format": "ulid"},
      "amount":     {"type": "integer", "minimum": 0},
      "currency":   {"type": "string", "enum": ["IRR","IRT","USD","EUR"]},
      "customerId": {"type": "string", "format": "ulid"},
      "paidAt":     {"type": "string", "format": "date-time"}
    }
  },
  "compatibility": "backward",
  "delivery": "at-least-once",
  "ordering": "per-aggregate",
  "retention_days": 7
}
```

### ۷٫۱ قوانین سازگاری Schema
| تغییر | مجاز؟ |
|---|---|
| افزودن فیلد اختیاری | ✅ (Backward) |
| افزودن فیلد اجباری | ❌ (نیازمند نسخهٔ جدید) |
| حذف فیلد | ❌ → `deprecated` سپس حذف در نسخهٔ بعد |
| تغییر نوع | ❌ |
| تغییر نام | ❌ (Alias در `version+1`) |

### ۷٫۲ نسخه‌گذاری
```
shop.order.paid.v1   →   shop.order.paid.v2
Listener هر دو را تا پایان مهاجرت پردازش می‌کند (Dual Write / Dual Read).
```

---

## ۸. استخراج یک ماژول به سرویس (`tvn module:extract`)

```
ورودی: modules/shop
خروجی: services/shop-service/

گام‌ها:
1. کپی modules/shop → services/shop-service/app/
2. تبدیل Contracts به OpenAPI Spec (از Route + Resource)
3. جایگزینی Bindingهای محلی با RemoteModuleProxy
4. ایجاد deploy/ (Dockerfile, compose, k8s manifests)
5. ایجاد DB Migration برای schema اختصاصی سرویس
6. تبدیل Eventهای عمومی به Integration Event (Outbox → Broker)
7. افزودن Health/Metrics/Trace endpoints
8. تولید Contract Test (Consumer-Driven)
9. به‌روزرسانی module.json ماژول مبدأ → `replaced_by: shop-service`
10. اجرای تست‌ها در هر دو حالت (مونولیت + سرویس)
```

### ۸٫۱ Dockerfile تولیدشده
```dockerfile
FROM php:8.4-cli-alpine AS base
RUN docker-php-ext-install pdo_mysql opcache pcntl sockets
WORKDIR /app
COPY . .
RUN php bin/tvn optimize
EXPOSE 8080
HEALTHCHECK --interval=10s --timeout=3s CMD php bin/tvn health:check --json
CMD ["php", "bin/tvn", "serve", "--host=0.0.0.0", "--port=8080"]
```

---

## ۹. Contract Testing (Consumer-Driven)

```php
final class ProductReaderContractTest extends ContractTestCase
{
    public function testFindByIds_ReturnsProducts(): void
    {
        // قرارداد مصرف‌کننده (Blog)
        $this->consumer('blog-service')
            ->expectsCallTo(ProductReaderInterface::class, 'findByIds', [[1, 2]])
            ->willReturn([
                new ProductDto(id: 1, title: 'A', price: 1000),
                new ProductDto(id: 2, title: 'B', price: 2000),
            ]);

        // تولیدکننده (Shop) باید همین رفتار را داشته باشد
        $this->verifyProvider('shop-service');
    }
}
```
🔒 Pact-like: قراردادها در `contracts/` ذخیره و در CI هر دو طرف بررسی می‌شوند.

---

## ۱۰. پیکربندی و Secrets در حالت توزیعی

| منبع | اولویت | توضیح |
|---|---|---|
| Config Server (etcd/Consul) | ۱ (بالاتر) | در حالت سرویس |
| ENV (k8s secret) | ۲ | استاندارد |
| `.env` | ۳ | فقط توسعه |
| `config/*.php` پیش‌فرض | ۴ | — |

🔒 Watch برای تغییر Config → رویداد `config.changed` + Hot Reload امن (بدون Restart برای موارد غیربحرانی).

---

## ۱۱. Observability توزیعی

| سیگنال | استاندارد | پیاده‌سازی |
|---|---|---|
| Trace | W3C TraceContext (`traceparent`, `tracestate`) | `observability` engine + OTLP Exporter خودنوشته |
| Metrics | Prometheus Text Format | `/metrics` endpoint |
| Log | JSON Structured + `trace_id` | `log` engine |
| Baggage | W3C Baggage | انتشار `tenant_id`, `user_id` (غیرحساس) |

```
[Gateway] traceparent: 00-<traceid>-<spanid>-01
    │
    ├──▶ [shop-service]  span: http.server → db.query → outbox.publish
    │        │
    │        └──▶ [payment-service] span: http.client → gateway.charge
    │
    └──▶ [notification-service] span: event.consume → mail.send
```

---

## ۱۲. معیار پذیرش 🧪

- AC-MS-01: ماژول `shop` در هر دو حالت (مونولیت و سرویس) تست‌های یکسان را پاس می‌کند.
- AC-MS-02: `tvn module:extract shop` خروجی قابل Build و Deploy تولید می‌کند.
- AC-MS-03: `traceparent` از Gateway تا DB در همهٔ Spanها حفظ می‌شود.
- AC-MS-04: Saga با شکست در گام ۳، همهٔ جبران‌ها را به‌ترتیب معکوس اجرا می‌کند.
- AC-MS-05: مصرف‌کننده با `RemoteModuleProxy` بدون تغییر کد کار می‌کند.
- AC-MS-06: Contract Test هر دو طرف در CI سبز است.
- AC-MS-07: رویداد نسخهٔ ۱ توسط مصرف‌کنندهٔ نسخهٔ ۲ پردازش می‌شود (Backward Compat).
- AC-MS-08: Circuit Breaker پس از ۵ خطا، ۳۰s باز می‌ماند و سپس Half-Open می‌شود.
- AC-MS-09: هیچ Join بین‌ماژولی در codebase وجود ندارد (`arch:check`).
- AC-MS-10: هر سرویس Health Check `liveness` و `readiness` دارد.
