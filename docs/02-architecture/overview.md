# DOC-020 — نمای کلان معماری تَوان (Architecture Overview)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-020 |
| وضعیت | Approved v1.0 |
| نقش | سند والد همهٔ تصمیم‌های معماری |
| اسناد فرزند | DOC-021..DOC-028 |

---

## ۱. فلسفهٔ معماری: «یک هسته، پنج الگو، بی‌شمار محصول»

تَوان یک معماری واحد انتخاب **نمی‌کند**؛ بلکه یک **هستهٔ ترکیب‌پذیر** می‌سازد که پنج الگوی معماری را
به‌صورت همزمان و لایه‌ای در بر می‌گیرد. هر الگو مسئول یک «مسئلهٔ متفاوت» است:

| الگو | مسئله‌ای که حل می‌کند | لایهٔ اعمال |
|---|---|---|
| **Layered** | جداسازی مسئولیت فنی (UI/Logic/Data) | همه‌جا |
| **HMVC** (Hierarchical MVC) | بازاستفاده از قطعات UI و کنترل جریان در صفحه | Presentation |
| **Modular Monolith** | مرز دامنه‌ای و استقرار ساده | ماژول‌ها |
| **Event-Driven (ED)** | کاهش Coupling بین دامنه‌ها + ناهمزمانی | بین ماژول/موتور |
| **Hexagonal (Ports & Adapters)** | جداسازی دامنه از فناوری → آمادگی مایکروسرویس | Domain/Infrastructure |

> **قاعدهٔ طلایی:** *ماژولار مونولیت به‌صورت پیش‌فرض؛ مایکروسرویس یک گزینهٔ استقرار است، نه بازنویسی.*

---

## ۲. نمای C4 — سطح ۱ (Context)

```
                        ┌──────────────────────────────┐
   کاربر نهایی ───────▶ │                              │ ──────▶ درگاه پرداخت
   مدیر/ادمین ────────▶ │      اپلیکیشن تَوان          │ ──────▶ سرویس پیامک
   توسعه‌دهنده ───────▶ │  (Monolith ماژولار / سرویس)  │ ──────▶ SMTP
   API مصرف‌کننده ────▶ │                              │ ──────▶ Redis / Cache
                        └──────────────────────────────┘ ──────▶ MySQL / PostgreSQL / SQLite
                                     │
                                     ▼
                          Observability (Metrics/Trace/Log)
```

---

## ۳. نمای C4 — سطح ۲ (Container)

```
┌──────────────────────────────────────────────────────────────────────────┐
│                         Deployment Topology                              │
│                                                                          │
│  ┌────────────┐   ┌────────────┐   ┌────────────┐   ┌────────────────┐  │
│  │ Web (FPM)  │   │ Swoole/FR  │   │ Queue      │   │ Scheduler      │  │
│  │ Entry:     │   │ Resident   │   │ Worker     │   │ (cron → tvn    │  │
│  │ public/    │   │ HTTP+WS    │   │ tvn queue: │   │  schedule:run) │  │
│  │ index.php  │   │            │   │ work       │   │                │  │
│  └─────┬──────┘   └─────┬──────┘   └─────┬──────┘   └───────┬────────┘  │
│        └────────────────┴────────────────┴──────────────────┘            │
│                              │                                           │
│                    ┌─────────▼──────────┐                                │
│                    │   Tavaan Kernel    │  ← یکسان در همهٔ Entry Point  │
│                    │ (Core + Engines)   │                                │
│                    └─────────┬──────────┘                                │
│                              │                                           │
│      ┌───────────────────────┼───────────────────────┐                   │
│      ▼                       ▼                       ▼                   │
│  ┌────────┐            ┌──────────┐            ┌──────────┐              │
│  │ Modules│            │ Engines  │            │ Storage  │              │
│  │ (HMVC) │            │ (48)     │            │ DB/FS/   │              │
│  └────────┘            └──────────┘            │ Cache    │             │
│                                                └──────────┘              │
└──────────────────────────────────────────────────────────────────────────┘
```

**۵ Entry Point با یک هسته:**

| Entry | فایل | حالت اجرا | کاربرد |
|---|---|---|---|
| Web | `public/index.php` | Per-Request (FPM) | سایت/پنل |
| CLI | `bin/tvn` | One-Shot | دستورات، Migration، Scaffold |
| Worker | `bin/tvn queue:work` | Resident | صف، Job |
| Scheduler | `bin/tvn schedule:run` | One-Shot (cron) | کارهای زمان‌بندی‌شده |
| Realtime | `bin/tvn serve:realtime` | Resident (Fiber/Swoole) | WebSocket/SSE |

---

## ۴. نمای C4 — سطح ۳ (Component) لایه‌های داخلی

```
┌───────────────────────────────────────────────────────────────────────┐
│ ENTRY LAYER   public/index.php │ bin/tvn │ worker │ realtime          │
├───────────────────────────────────────────────────────────────────────┤
│ KERNEL        Bootstrap → Autoloader → Env → Config → Container       │
│               → Providers(register) → Error/Event → Kernel(HTTP/CLI)  │
├───────────────────────────────────────────────────────────────────────┤
│ PIPELINE      Global Middleware → Route → Group Middleware → Route MW │
├───────────────────────────────────────────────────────────────────────┤
│ PRESENTATION  Controller (HMVC) │ View (.tv.php) │ Resource │ Command │
├───────────────────────────────────────────────────────────────────────┤
│ APPLICATION   UseCase │ Command/Query Handler │ DTO │ Transaction     │
├───────────────────────────────────────────────────────────────────────┤
│ DOMAIN        Entity │ ValueObject │ DomainService │ DomainEvent      │
│               Policy │ StateMachine │ Invariant                        │
├───────────────────────────────────────────────────────────────────────┤
│ INFRASTRUCTURE Repository(DB) │ Cache │ Mail │ Search │ Payment GW    │
│                Filesystem │ Queue │ Http Client │ Observability       │
├───────────────────────────────────────────────────────────────────────┤
│ ENGINES       ۵۳ موتور خودنوشته (هر کدام Provider + Contracts + Impl) │
└───────────────────────────────────────────────────────────────────────┘
```

---

## ۵. نگاشت MVC / HMVC در تَوان

### ۵٫۱ چرا MVC ساده کافی نیست؟
در یک صفحهٔ ERP ممکن است ۱۰ «قطعه» مستقل وجود داشته باشد (منو، داشبورد، جدول سفارش‌ها،
اعلان‌ها، چت، آمار…). در MVC کلاسیک، همه در یک Controller بزرگ جمع می‌شوند → **God Controller**.

### ۵٫۲ راه‌حل HMVC تَوان
```
Request
  └─▶ MasterController (Layout)
        ├─▶ Widget:DashboardStats   → Controller + Model + View (خودبسنده)
        ├─▶ Widget:RecentOrders     → Controller + Model + View
        ├─▶ Module:Notification     → Controller + Model + View
        └─▶ Content:OrderDetail     → Controller + Model + View
```

- هر **Widget/SubRequest** یک «چرخهٔ عمر کوچک» کامل دارد (Controller → Model → View).
- فراخوانی با `Module::run('shop/cart/summary')` → یک `SubRequest` داخلی (بدون I/O شبکه).
- هر سطح به Context والد دسترسی **خواندنی** دارد؛ نوشتن فقط از طریق Event.
- عمق حداکثر ۴ سطح (جلوگیری از Recursive Loop) → در غیر این صورت `HmvcDepthException`.

```php
// در View یا Controller
<?= Module::run('shop:cart/summary', ['compact' => true]) ?>
```

### ۵٫۳ جزئیات کامل
→ `docs/02-architecture/layered-hmvc.md` (DOC-021)

---

## ۶. نگارش معماری ماژولار (Modular Monolith)

```
modules/
├── blog/       ← ماژول وبلاگ (خودبسنده)
├── shop/       ← ماژول فروشگاه
├── forum/      ← ماژول انجمن
├── lms/        ← ماژول آموزش
├── crm/        ← ماژول مشتریان
├── erp/        ← ماژول منابع سازمانی
└── bpms/       ← ماژول فرایندها
```

| قاعده | توضیح |
|---|---|
| 🔒 مرز سخت | ماژول A نمی‌تواند کلاس داخلی ماژول B را `use` کند |
| 🔒 دروازهٔ عمومی | فقط `Modules\X\Contracts\*` و رویدادهای عمومی |
| 🔒 دیتابیس | هر ماژول جداول خودش (با prefix ماژول)؛ Join بین‌ماژولی ممنوع → از API/Event |
| ⚠️ وابستگی | در `module.json` اعلام و Topological Sort می‌شود |
| 💡 استخراج | ماژول آمادهٔ تبدیل به سرویس مستقل (بدون تغییر کد Domain) |

→ جزئیات: `docs/02-architecture/modular-monolith.md` (DOC-022)

---

## ۷. نگارش رویداد‌محور (Event-Driven)

```
[Command] ──▶ [UseCase] ──▶ [Domain Change] ──▶ [Domain Event]
                                                     │
                          ┌──────────────────────────┼─────────────────────┐
                          ▼                          ▼                     ▼
                  [Sync Listener]            [Queued Listener]      [Outbox → Broker]
                  (validation,               (email, sms,           (سرویس‌های دیگر)
                   projection)                search index)
```

| ویژگی | پیاده‌سازی |
|---|---|
| Sync Dispatch | `EventDispatcher` با Priority + Wildcard |
| Async | `ShouldQueue` → Queue Engine |
| At-Least-Once | Idempotency Key + Retry + DLQ |
| Ordering | Sequence per Aggregate در Outbox |
| Schema | `EventSchema` + Versioning |
| CQRS | `Command`/`Query` جدا + Read Model Projection |
| Event Sourcing | اختیاری (ماژول `event-store`) با Snapshot |

→ جزئیات: `docs/02-architecture/event-driven.md` (DOC-023)

---

## ۸. آمادگی مایکروسرویس

**استراتژی سه‌مرحله‌ای (Strangler):**

| مرحله | حالت | چه چیزی تغییر می‌کند |
|---|---|---|
| S1 | Monolith | همه ماژول‌ها در یک فرایند؛ ارتباط Event Sync |
| S2 | Modular + Outbox | رویدادها به Broker می‌روند؛ Read Modelها جدا |
| S3 | Service Extraction | ماژول → سرویس مستقل؛ Event Bus محلی → شبکه‌ای؛ Contract بدون تغییر |

**الزامات معماری برای S3 (از روز اول رعایت می‌شود):**
1. 🔒 Domain هیچ وابستگی به Framework ندارد (Hexagonal).
2. 🔒 ارتباط بین ماژول فقط Event یا Public Contract.
3. 🔒 هر ماژول Schema دیتابیس اختصاصی دارد (بدون Join بین‌ماژولی).
4. 🔒 شناسه‌های توزیعی (ULID/Snowflake) نه Auto-Increment وابسته.
5. 🔒 Trace Context در همهٔ فراخوانی‌ها منتشر می‌شود.
6. 🔒 Idempotency Key برای همهٔ نوشتن‌های بین‌سرویسی.

→ جزئیات: `docs/02-architecture/microservice-ready.md` (DOC-024)

---

## ۹. نمودار جریان کلان (یک درخواست وب)

```
public/index.php
   │ 1  require core/bootstrap.php
   │ 2  Autoloader::register()
   │ 3  Env::load('.env')
   │ 4  $app = Application::create(basePath)      ← Container + Kernel
   │ 5  Config::load('config/')
   │ 6  Providers::register(all)                 ← register()
   │ 7  Providers::boot()                        ← boot()
   │ 8  $kernel->handle(Request::capture())
   │        8.1  Global Middleware Pipeline
   │        8.2  Route Matching (Compiled Cache)
   │        8.3  Module Resolution (HMVC)
   │        8.4  Route Middleware
   │        8.5  Controller Resolve + Validate (FormRequest)
   │        8.6  Authorization (Gate/Policy)
   │        8.7  Action Invoke (UseCase → Domain → Repository)
   │        8.8  Events (sync + queued)
   │        8.9  Response Build (View/Resource)
   │ 9  $response->send()
   │10  $kernel->terminate()                     ← Middleware::terminate, Session Write, Log Flush
   ▼
```
→ جزئیات ۱۴ فاز: `docs/02-architecture/request-lifecycle.md` (DOC-025)

---

## ۱۰. ماتریس تصمیم‌های کلیدی (ADR Summary)

| ADR | تصمیم | وضعیت |
|---|---|---|
| ADR-0001 | معماری ترکیبی (HMVC + Modular + ED + Hexagonal) | Accepted |
| ADR-0002 | مدل مجوزدهی (MIT هسته / تجاری Enterprise) | Proposed |
| ADR-0003 | حداقل نسخهٔ PHP = 8.2 | Accepted |
| ADR-0004 | صفر وابستگی Composer در هسته | Accepted |
| ADR-0005 | Autoloader خودنوشته با ClassMap Cache | Accepted |
| ADR-0006 | شناسهٔ اصلی: ULID (پیش‌فرض) یا BigInt | Accepted |
| ADR-0007 | ORM دوگانه (Active Record + Repository) | Accepted |
| ADR-0008 | پسوند فایل View: `.tv.php` با `@directive` | Accepted |
| ADR-0009 | Outbox به‌عنوان الگوی پیش‌فرض رویداد ناهمزمان | Accepted |
| ADR-0010 | Multi-Tenancy با Strategy Pattern (3 حالت) | Accepted |
| ADR-0011 | Context با Fiber-Aware Storage (آمادگی Swoole) | Accepted |
| ADR-0012 | تقویم جلالی در موتور `i18n` (نه پکیج خارجی) | Accepted |
| ADR-0013 | پول با `int` minor-unit + `Money` VO (نه float/bcmath) | Accepted |
| ADR-0014 | صفر `vendor/` در زمان اجرا؛ ابزار QA فقط در dev | Accepted |
| ADR-0015 | نسخه‌گذاری API با `/api/v1` + Header | Accepted |

---

## ۱۱. شاخص‌های معماری (Architecture Fitness Functions)

| شاخص | هدف | ابزار |
|---|---|---|
| ماژول بدون نقض مرز | ۱۰۰٪ | `tvn arch:check` |
| کلاس‌های هسته با Fan-In > 50 | مجاز فقط برای Kernel | تحلیل گراف |
| عمق گراف وابستگی | ≤ ۶ | تحلیل گراف |
| چرخهٔ وابستگی (Circular) | ۰ | `arch:check` |
| کلاس Domain با `use Tavaan\Engine\*` | ۰ | قانون Arch |
| درصد کلاس‌های `final` | ≥ ۹۰٪ | CS/AST |
| Interface به Implementation | ≥ ۱:۱٫۲ | تحلیل |
| تست به ازای هر کلاس عمومی | ≥ ۱ | CI |

---

## ۱۲. ارجاعات

| سند | موضوع |
|---|---|
| DOC-021 | لایه‌بندی دقیق + HMVC |
| DOC-022 | مرز ماژول و Public API |
| DOC-023 | Event Bus، CQRS، Outbox |
| DOC-024 | تجزیه به مایکروسرویس |
| DOC-025 | چرخهٔ عمر درخواست (۱۴ فاز) |
| DOC-026 | کانتینر و DI |
| DOC-027 | Bootstrap و Autoloader |
| DOC-028 | Multi-Tenancy |
