# DOC-002 — اصول بنیادین تَوان (First Principles)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-002 |
| وضعیت | Approved v1.0 |
| نقش | مرجع حل تعارض؛ هر تصمیم معماری باید به یکی از این اصول ارجاع دهد |

---

## ۱. دوازده اصل بنیادین

### P1 — اصل صفر‌وابستگی (Zero Dependency) 🔒
> هسته و موتورها **هیچ** پکیج خارجی Composer را `require` نمی‌کنند.

- تنها مجاز: افزونه‌های PHP (ext-pdo, ext-mbstring, ext-openssl, ext-intl, ext-gd/imagick…) — و آن هم
  **مشروط به Fallback**. اگر افزونه نبود، موتور باید با پیاده‌سازی خالص PHP ادامه دهد یا
  `EngineCapabilityException` واضح بدهد.
- هر موتور می‌تواند `composer.json` داشته باشد اما فقط برای **انتشار اختیاری** (Publish)، نه برای نصب وابستگی.
- **تست پذیرش:** `grep -R "vendor/" core engines` → خالی. اجرای پروژه بدون پوشهٔ `vendor/`.

### P2 — اصل قرارداد‌مقدم (Contract-First)
> هر قابلیت عمومی ابتدا با `interface` تعریف می‌شود، سپس پیاده‌سازی.

- مصرف‌کننده فقط به Interface وابسته است (DIP).
- امکان تعویض پیاده‌سازی (Swap) بدون تغییر کد کاربردی.
- Facadeها همیشه به یک Interface اشاره می‌کنند، نه به کلاس Concrete.

### P3 — اصل کمینه‌دانی هسته (Minimal Kernel)
> هسته فقط چیزی را می‌داند که برای «اجرا» لازم است؛ بقیه در موتورهاست.

- هسته = Autoloader + Container + Provider + Config + Env + Events + Errors + Facade + Pipeline + Contracts.
- اگر قابلیتی «قابل حذف» است، در هسته **نباید** باشد (مثل HTTP، DB، View).
- نتیجه: بوت فوق‌سریع، سطح حملهٔ کوچک، تست‌پذیری بالا.

### P4 — اصل مرزهای روشن (Explicit Boundaries)
> ماژول/موتور/لایه فقط از طریق «دروازهٔ عمومی» با دیگری حرف می‌زند.

- ممنوع: `use Modules\Shop\Models\Order;` از داخل `Modules\Blog`.
- مجاز: رویداد، Interface عمومی ماژول، یا Service Contract.
- ابزار بررسی خودکار: `tvn arch:check` (قوانین در `docs/02-architecture/modular-monolith.md`).

### P5 — اصل رویداد به‌جای فراخوانی (Events over Calls)
> برای ارتباط **بین دامنه‌ها** از رویداد استفاده شود، نه فراخوانی مستقیم.

- همزمانی (Sync) برای جریان اصلی؛ ناهمزمان (Async/Queue) برای اثرات جانبی.
- هر رویداد: `Schema` + `Version` + `OccurredAt` + `AggregateId` + `IdempotencyKey`.

### P6 — اصل شکست صریح (Fail Fast & Loud)
> خطا نباید پنهان شود؛ در نزدیک‌ترین نقطه به منشأ، با حداکثر context پرتاب شود.

- ممنوع: `catch (\Throwable $e) {}` خالی؛ ممنوع `@` suppression؛ ممنوع `return false` مبهم.
- هر استثنا: `MessageId` + `Code` + `Context` + `Hint` + `DocsUrl`.

### P7 — اصل امن به‌صورت پیش‌فرض (Secure by Default)
> حالت پیش‌فرض = امن‌ترین حالت. شل‌کردن نیازمند اقدام آگاهانه و ثبت در لاگ است.

- Escape خودکار در View، Prepared Statement اجباری، CSRF همیشه فعال، Session Hardened،
  Headerهای امنیتی پیش‌فرض، Rate Limit پیش‌فرض.
- غیرفعال‌سازی هر کنترل → هشدار در لاگ + نشان در Debug Bar.

### P8 — اصل شفافیت (No Hidden Magic)
> هر رفتار «جادویی» باید قابل ردیابی، قابل غیرفعال‌سازی و مستند باشد.

- Facade → به‌صورت Lazy Proxy و با `@mixin` مستند.
- Dynamic Property ممنوع؛ `__get/__set` فقط در DTO/Collection کنترل‌شده.
- هر Hook/Listener در `Debug Bar` با زمان اجرا نمایش داده می‌شود.

### P9 — اصل تک‌مسئولیتی و اندازهٔ کوچک (SRP + Small Surface)
> کلاس ≤ ۳۰۰ خط، متد ≤ ۵۰ خط، پارامتر ≤ ۵، Cyclomatic Complexity ≤ ۱۰.

- عبور از حد = بوی طراحی → تجزیه یا ثبت ADR توجیهی.
- کلاس `*Manager`/`*Helper`/`*Utils` ممنوع مگر با دامنهٔ دقیق و مستند.

### P10 — اصل کارایی بدون قربانی‌کردن خوانایی (Readable Performance)
> بهینه‌سازی فقط با اندازه‌گیری؛ Micro-optimization بدون Benchmark ممنوع.

- هر ادعای کارایی با عدد و روش اندازه‌گیری در `docs/07-quality/benchmark-targets.md` ثبت می‌شود.
- تکنیک‌های مجاز: Lazy Loading، Compile/Cache، Trie Matching، Prepared Reuse، Opcache Preload،
  Avoid Autoboxing Array Copies، String Buffer، Generators برای مجموعه‌های بزرگ.

### P11 — اصل آینده‌نگری زبانی (Language Forward-Compatibility)
> کد باید در PHP 8.2 بنویسد و در 8.3/8.4/9 بدون Deprecated اجرا شود.

- ممنوعه‌های صریح در `docs/01-standards/php82-strict.md`.
- CI باید با `error_reporting=E_ALL` و تبدیل `E_DEPRECATED` به Failure اجرا شود.

### P12 — اصل توسعه‌پذیری سه‌گانه (Extensibility Triad)
> هر موتور دقیقاً سه نقطهٔ توسعه دارد: **Hook (رویداد)**، **Contract (تعویض)**، **Decorator (تزئین)**.

- بدون Fork کردن هسته، رفتار قابل تغییر است.
- پلاگین‌های PHP (مورد اشارهٔ کاربر) از این سه نقطه استفاده می‌کنند و از قاعدهٔ صفر‌وابستگی **معاف** هستند.

---

## ۲. اولویت‌بندی هنگام تعارض (Tie-Breaker Order) 🔒

وقتی دو اصل با هم در تضاد قرار گرفتند، به این ترتیب تصمیم بگیرید:

```
1. Correctness (صحت)        — رفتار درست بر هر چیز مقدم است
2. Security (امنیت)         — هیچ بهینه‌سازی که کنترل امنیتی را دور بزند مجاز نیست
3. Simplicity (سادگی)       — راه‌حل ساده‌تر بر راه‌حل هوشمندانه مقدم است
4. Performance (کارایی)     — با اندازه‌گیری، نه حدس
5. Flexibility (انعطاف)     — فقط در صورت نیاز واقعی (YAGNI)
6. Convenience (راحتی)      — Sugar در آخرین لایه، بدون پنهان‌کردن منطق
```

> قاعدهٔ طلایی: **اگر تصمیمی برگشت‌ناپذیر است → ADR بنویس. اگر برگشت‌پذیر است → سریع تصمیم بگیر.**

---

## ۳. قواعد طراحی سطح API (API Design Rules)

| # | قاعده | مثال خوب | مثال بد |
|---|---|---|---|
| A1 | نام متد = فعل، نام کلاس = اسم | `$user->activate()` | `$user->activation()` |
| A2 | Immutable پیش‌فرض برای Value Object | `readonly class Money` | `class Money { public $v; }` |
| A3 | متدهای زنجیره‌ای `return $this` یا نمونهٔ جدید — و **مستند** کدام | `Query->where()->orderBy()` | مخلوط بدون قاعده |
| A4 | پارامترهای اختیاری در انتها، یا Named Arguments | `render(view: 'x', data: [])` | `render($a=null,$b=null,$c=null,$d=null)` |
| A5 | هرگز `null` مبهم برنگردان؛ `?Type` صریح یا استثنا | `find(): ?User` | `find(): mixed` |
| A6 | Collection به‌جای آرایه در خروجی عمومی | `UserCollection` | `array<int, array>` |
| A7 | ورودی خارجی همیشه Validate شود (Trust Boundary) | `Validator::make()` | استفادهٔ مستقیم `$_POST` |
| A8 | خروجی همیشه Encode/Escape شود | `e($name)` در View | `echo $name` |
| A9 | Enum برای مجموعهٔ بستهٔ مقادیر | `enum OrderStatus: string` | رشتهٔ خام `'paid'` |
| A10 | پایداری API با `@api` و نسخه‌گذاری | `@api 1.0 stable` | تغییر امضا بدون Deprecation |

---

## ۴. اصول لایه‌بندی (Layering Rules)

```
┌───────────────────────────────────────────────────────────┐
│  Presentation : Controller / View / API Resource / CLI    │  ← فقط ترجمهٔ ورودی/خروجی
├───────────────────────────────────────────────────────────┤
│  Application    : UseCase / Command / Query / Handler     │  ← منطق جریان کار، بدون SQL
├───────────────────────────────────────────────────────────┤
│  Domain         : Entity / ValueObject / DomainService   │  ← قوانین کسب‌وکار، صفر وابستگی
├───────────────────────────────────────────────────────────┤
│  Infrastructure : Repository / Gateway / Cache / Mail     │  ← پیاده‌سازی Contractها
├───────────────────────────────────────────────────────────┤
│  Kernel         : Container / Events / Config / Errors   │  ← بستر اجرا
└───────────────────────────────────────────────────────────┘
```

**قوانین جهت وابستگی:**
1. 🔒 وابستگی فقط **رو به پایین** (Presentation → Application → Domain).
2. 🔒 Domain **هیچ** وابستگی به Infrastructure ندارد (فقط Interface).
3. 🔒 Infrastructure با Dependency Inversion به Domain وصل می‌شود.
4. ⚠️ Kernel هرگز به ماژول/اپلیکیشن وابسته نمی‌شود.
5. ⚠️ DTO در مرز لایه‌ها تبدیل می‌شود (Entity به بیرون نشت نکند).

---

## ۵. اصول داده (Data Principles)

| # | اصل | توضیح |
|---|---|---|
| D1 | Schema-first | هیچ Model بدون Migration/Schema تعریف‌شده |
| D2 | Migration برگشت‌پذیر | `up()` + `down()` اجباری (مگر با دلیل در ADR) |
| D3 | Soft Delete استاندارد | `deleted_at` + Scope خودکار |
| D4 | Timestamp استاندارد | `created_at`, `updated_at` (UTC در DB، نمایش Locale) |
| D5 | کلید اصلی | `id` از نوع `bigint unsigned` یا `ulid`؛ انتخاب در ADR-0006 |
| D6 | پول | هرگز float؛ همیشه `decimal(18,4)` + Value Object `Money` |
| D7 | Enum در DB | `varchar` + Enum در PHP (نه native enum MySQL) برای انعطاف |
| D8 | JSON | فقط برای دادهٔ بدون کوئری؛ در غیر این صورت ستون مجزا |
| D9 | نام جدول | snake_case جمع: `users`, `order_items` |
| D10 | ایندکس | هر کلید خارجی + هر ستون پر‌کوئری ایندکس دارد |

---

## ۶. اصول خطا و بازیابی (Resilience Principles)

1. **Retry** فقط برای عملیات Idempotent + Backoff نمایی با Jitter.
2. **Circuit Breaker** برای هر فراخوانی شبکهٔ خارجی (آستانه: ۵ خطا در ۶۰ ثانیه).
3. **Timeout** اجباری برای هر I/O (پیش‌فرض: DB 5s، HTTP 10s، Cache 1s).
4. **Bulkhead**: هر صف/کارگر دارای محدودیت همزمانی مستقل.
5. **Graceful Degradation**: اگر Cache مرد → ادامه با DB؛ اگر Mail مرد → صف + هشدار.
6. **Idempotency Key** برای همهٔ عملیات نوشتاری شبکه‌ای.
7. **Dead Letter Queue** برای پیام‌های شکست‌خوردهٔ بیش از N بار.

---

## ۷. اصول مستندسازی

- هر کلاس عمومی: PHPDoc با `@since`, `@api`, `@throws`.
- هر موتور: یک فایل `docs/03-engines/<name>.md` (طبق `docs/templates/engine-spec-template.md`).
- هر تصمیم برگشت‌ناپذیر: یک ADR.
- مستند **قبل از کد** نوشته می‌شود (Docs-First).

---

## ۸. نقشهٔ اصل → الزام → سند

| اصل | الزام‌های مشتق | سند جزئیات |
|---|---|---|
| P1 | NFR-DEP-01..05 | `01-standards/php82-strict.md`, `04-structure/composer-mapping.md` |
| P2 | NFR-ARC-01 | `01-standards/contracts-interfaces.md` |
| P3 | NFR-PERF-01 | `02-architecture/bootstrapping.md` |
| P4 | NFR-ARC-04 | `02-architecture/modular-monolith.md` |
| P5 | NFR-ARC-06 | `02-architecture/event-driven.md` |
| P6 | NFR-QUA-02 | `01-standards/error-handling.md` |
| P7 | NFR-SEC-01..12 | `01-standards/security-policy.md` |
| P8 | NFR-QUA-05 | `02-architecture/container-di.md` |
| P9 | NFR-QUA-03 | `01-standards/coding-style.md` |
| P10 | NFR-PERF-02..12 | `07-quality/benchmark-targets.md` |
| P11 | NFR-QUA-01 | `01-standards/php82-strict.md` |
| P12 | NFR-ARC-08 | `03-engines/plugin.md` |
