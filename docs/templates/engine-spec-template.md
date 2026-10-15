# DOC-150 — قالب مستند موتور (Engine Specification Template)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-150 |
| وضعیت | Approved v1.0 |
| نقش | قالب اجباری مستند هر موتور (۱۷ بخش) |
| مرتبط | DOC-015 (استاندارد مستندسازی)، DOC-030 (رجیستری موتورها) |

> این فایل **قالب** است. برای ساخت مستند هر موتور، آن را کپی کرده و
> در `docs/03-engines/<name>.md` ذخیره کنید. هیچ بخشی نباید حذف شود مگر با `N/A: <دلیل>`.
>
> ⚠️ در بلوک قالب زیر، `DOC-0XX` یک placeholder است — هنگام کپی، آن را با DOC-ID واقعی موتور جایگزین کنید.

---

```markdown
# DOC-0XX — موتور <Name> | <نام فارسی>

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-0XX |
| Engine ID | `tavaan.engine.<name>` |
| Namespace | `Tavaan\Engine\<Pascal>` |
| مسیر | `engines/<kebab-name>/` |
| وضعیت | Draft / Review / Approved v1.0 |
| اولویت | P0 / P1 / P2 |
| فاز ساخت | M1..M8 |
| وابستگی (سخت) | engine-a, engine-b |
| وابستگی (نرم/اختیاری) | engine-c |
| افزونه‌های PHP | ext-x (اجباری) / ext-y (اختیاری + fallback) |
| Facade | `<Name>` → accessor `<name>` |
| جداول DB | `tvn_x`, `...` |
| LOC برآوردی | 0000 |
| مسئول | @owner |
| مرتبط | DOC-xxx, ADR-xxxx |

---

## ۱. هدف و دامنه

### ۱٫۱ هدف (Why)
<یک پاراگراف: چه مسئله‌ای را حل می‌کند و چرا لازم است>

### ۱٫۲ دامنه (What)
- ✅ شامل: <…>
- ❌ خارج از دامنه: <…>

### ۱٫۳ الزامات پوشش‌داده‌شده
| ID | الزام |
|---|---|
| FR-XXX-01 | … |
| NFR-XXX-01 | … |

### ۱٫۴ مقایسه با مراجع
| مرجع | چه چیزی بهتر است | چه چیزی را تغییر دادیم | چرا |
|---|---|---|---|
| Laravel X | … | … | … |
| Symfony Y | … | … | … |

---

## ۲. مفاهیم و واژگان

| واژه | تعریف |
|---|---|
| <Term> | <تعریف دقیق> |

---

## ۳. معماری داخلی

### ۳٫۱ نمودار اجزا
```
<ASCII/Mermaid diagram>
```

### ۳٫۲ فهرست کلاس‌ها
| کلاس | نوع | مسئولیت | LOC | عمومی؟ |
|---|---|---|---|---|
| `<Name>ServiceProvider` | Provider | ثبت binding | 80 | ✅ |
| `<Name>Engine` | Facade Root | نقطهٔ ورود | 120 | ✅ |
| `<Name>Manager` | Manager | مدیریت درایور | 150 | ✅ |
| … | | | | |

### ۳٫۳ نمودار توالی جریان اصلی
```mermaid
sequenceDiagram
```

### ۳٫۴ نمودار وضعیت (در صورت وجود State Machine)
```
<state diagram>
```

---

## ۴. API عمومی

### ۴٫۱ `<MainClass>`

| متد | امضا | توضیح | استثنا |
|---|---|---|---|
| `x` | `x(string $a, int $b = 0): Result` | … | `XException` |

```php
// نمونهٔ اجراشدنی
```

### ۴٫۲ Facade
```php
/** @mixin \Tavaan\Engine\<Name>\<Name>Engine */
final class <Name> extends Facade { protected static function accessor(): string { return '<name>'; } }
```

### ۴٫۳ Helper Functions
| تابع | امضا | توضیح |
|---|---|---|

### ۴٫۴ Attributes
| Attribute | پارامترها | هدف |
|---|---|---|

---

## ۵. الگوریتم‌ها و ساختار داده

### ۵٫۱ <الگوریتم ۱>
**هدف:** …
**ورودی/خروجی:** …
**شبه‌کد:**
```
function x():
  1. …
  2. …
```
**پیچیدگی:** زمان O(…)، حافظه O(…)
**حالت‌های مرزی:** …
**اثبات صحت / استدلال:** …

### ۵٫۲ ساختار دادهٔ داخلی
```php
/** @var array<string, list<int>> */
private array $index = [];
```

---

## ۶. پیکربندی

```php
// engines/<name>/config/<name>.php
return [ /* کامل با کامنت */ ];
```

| کلید | نوع | پیش‌فرض | ENV | توضیح |
|---|---|---|---|---|
| `<name>.x` | string | `'y'` | `TVN_X` | … |

---

## ۷. قراردادها (Contracts)

```php
// core/Contracts/<Area>/<Name>Interface.php
interface <Name>Interface
{
    public function …(…): …;
}
```

### ۷٫۱ پیاده‌سازی‌ها
| Interface | پیاده‌سازی پیش‌فرض | جایگزین‌ها | Fake تست |
|---|---|---|---|

---

## ۸. نقاط توسعه (Extensibility)

### ۸٫۱ رویدادهای منتشرشده
| رویداد | نام | Payload | زمان |
|---|---|---|---|

### ۸٫۲ رویدادهای مصرف‌شده
| رویداد | Listener | عمل |
|---|---|---|

### ۸٫۳ Hook / Extend
```php
$container->extend(<Name>Interface::class, fn($impl, $app) => new MyDecorator($impl));
```

### ۸٫۴ درایور سفارشی
```php
<Name>Manager::extend('my-driver', fn($config) => new MyDriver($config));
```

---

## ۹. امنیت

| ID | تهدید | کنترل | ارجاع |
|---|---|---|---|
| SEC-… | … | … | DOC-016 |

### ۹٫۱ چک‌لیست امنیتی موتور
- [ ] ورودی‌ها Validate شده
- [ ] خروجی‌ها Escape شده
- [ ] دادهٔ حساس Mask شده
- [ ] هیچ `eval`/`unserialize` ناامن
- [ ] Rate Limit (در صورت Endpoint)
- [ ] Audit برای عملیات حساس

---

## ۱۰. کارایی

| عملیات | هدف | روش سنجش |
|---|---|---|
| … | ≤ Xms | `benchmarks/<name>.bench.php` |

### ۱۰٫۱ تکنیک‌های بهینه‌سازی به‌کاررفته
- …

### ۱۰٫۲ نقاط گلوگاه شناخته‌شده
- …

---

## ۱۱. مدیریت خطا

| استثنا | کد | MessageId | HTTP | Retryable |
|---|---|---|---|---|
| `XException` | TXX001 | `x.failed` | 500 | ❌ |

---

## ۱۲. تست

### ۱۲٫۱ استراتژی
| سطح | پوشش | ابزار |
|---|---|---|
| Unit | ≥ ۹۰٪ | `engines/<name>/tests/Unit` |
| Feature | جریان اصلی | `tests/Feature` |
| Integration | با DB/Cache واقعی (SQLite in-memory) | `tests/Integration` |
| Benchmark | اهداف §10 | `benchmarks/` |
| Security | §9 | `tests/Security` |

### ۱۲٫۲ سناریوهای تست
| ID | سناریو | انتظار |
|---|---|---|
| T-01 | … | … |

### ۱۲٫۳ Fixtures
```
engines/<name>/tests/Fixtures/…
```

---

## ۱۳. مثال End-to-End

```php
<?php
declare(strict_types=1);
// examples/<name>/basic.php
```
**خروجی مورد انتظار:**
```
…
```

---

## ۱۴. معیار پذیرش (Acceptance Criteria) 🧪

| ID | معیار | تست |
|---|---|---|
| AC-<NAME>-01 | … | `tests/Unit/XTest.php::testY` |
| AC-<NAME>-02 | … | … |

---

## ۱۵. محدودیت‌ها و کارهای آینده

### ۱۵٫۱ محدودیت‌های شناخته‌شده
- …

### ۱۵٫۲ نقشهٔ راه موتور
| نسخه | قابلیت |
|---|---|
| 1.1 | … |
| 2.0 | … |

---

## ۱۶. مراجع

| منبع | چه چیزی الهام‌گرفته شد | چه چیزی متفاوت است |
|---|---|---|
| … | … | … |
| RFC … | … | … |

---

## ۱۷. بلوک ماشین‌خوان 🧠

```yaml
# machine-readable: tavaan.engine.<name>.v1
engine:
  id: tavaan.engine.<name>
  namespace: Tavaan\Engine\<Pascal>
  path: engines/<name>
  priority: P0
  phase: M1
  depends_on: []
  soft_depends_on: []
  extensions:
    required: []
    optional: [{name: x, fallback: y}]
  facade: {name: <Name>, accessor: <name>}
  tables: []
  permissions: []
  events_emitted: []
  events_consumed: []
  config_file: engines/<name>/config/<name>.php
  config_keys: []
  public_api:
    - class: <Name>Engine
      methods: []
  loc_estimate: 0
  acceptance:
    - {id: AC-<NAME>-01, test: 'tests/Unit/…'}
```
```

---

## راهنمای استفاده برای ایجنت

1. `docs/03-engines/README.md` را بخوان تا Engine ID و فاز را پیدا کنی.
2. این قالب را کپی کن → `docs/03-engines/<name>.md`.
3. بخش‌های ۱ تا ۷ را **قبل از کدنویسی** کامل کن (Docs-First).
4. بخش ۱۴ (معیار پذیرش) را به تست تبدیل کن (TDD).
5. پس از پیاده‌سازی، بخش‌های ۹/۱۰/۱۲ را با نتایج واقعی پر کن.
6. بلوک §۱۷ را دقیق پر کن — ایجنت‌های دیگر از آن استفاده می‌کنند.
