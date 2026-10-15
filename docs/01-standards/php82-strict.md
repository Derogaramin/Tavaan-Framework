# DOC-010 — قواعد اجباری PHP 8.2+ و پیشگیری از Deprecated 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-010 |
| وضعیت | Approved v1.0 — **نقض هر بند = ریجکت کد** |
| هدف | کد امروز روی PHP 8.2 بنویسد و روی 8.3/8.4/8.5 بدون اخطار اجرا شود |
| ابزار اجرا | `tvn lint:php` + CI Matrix (8.2/8.3/8.4) + PHPStan + PHP-CS-Fixer |

---

## ۱. الزامات پایهٔ هر فایل

```php
<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — <Engine Name>
 * <توضیح یک‌خطی مسئولیت کلاس>
 *
 * @package   Tavaan\Engine\<X>
 * @license   MIT
 * @copyright 2026 Tavaan
 * @see       docs/03-engines/<x>.md
 */

namespace Tavaan\Engine\Http;
```

| قاعده | وضعیت |
|---|---|
| `declare(strict_types=1);` اولین جمله بعد از `<?php` | 🔒 MUST |
| یک کلاس/اینترفیس/اینام/ترییت در هر فایل | 🔒 MUST |
| نام فایل = نام کلاس (PascalCase)؛ به‌جز `helpers.php` و `routes.php` | 🔒 MUST |
| Namespace مطابق نگاشت `docs/04-structure/composer-mapping.md` | 🔒 MUST |
| هدر مستند طبق `docs/04-structure/file-conventions.md` | 🔒 MUST |
| `?>` پایانی در فایل‌های فقط‌PHP | 🔒 MUST حذف شود |

---

## ۲. فهرست ممنوعه‌ها (Deprecated / Removed / Forbidden)

### ۲٫۱ موارد Deprecated که **هرگز** نباید نوشته شوند

| # | مورد | جایگزین صحیح | نسخهٔ هشدار |
|---|---|---|---|
| F-01 | Dynamic Property (تعریف ویژگی روی آبجکت بدون اعلان) | `readonly`/typed property یا `#[\AllowDynamicProperties]` فقط در DTO کنترل‌شده | 8.2 Deprecated |
| F-02 | `${var}` داخل رشته (Variable Interpolation با آکولاد) | `{$var}` | 8.2 Deprecated |
| F-03 | `utf8_encode()` / `utf8_decode()` | `mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1')` | 8.2 Deprecated / 9.0 حذف |
| F-04 | `mb_strimwidth()` با پارامتر `null` اشتباه / `strtolower` روی چندبایتی | `mb_strtolower()` | — |
| F-05 | `strftime()`, `gmstrftime()`, `date_sunrise/sunset` با پارامترهای منسوخ | `IntlDateFormatter` یا `Tavaan\Engine\I18n\Jalali` | 8.1 Deprecated / 9.0 حذف |
| F-06 | `DateTime::__construct` با فرمت‌های منسوخ | فرمت‌های صریح `Y-m-d H:i:s` | — |
| F-07 | `create_function()` | Closure | حذف‌شده از 8.0 |
| F-08 | `each()` | `foreach` | حذف‌شده از 8.0 |
| F-09 | `money_format()` | `NumberFormatter` / `Tavaan\Support\Money` | حذف‌شده از 7.4 |
| F-10 | Curly-brace array/string offset `$str{0}` | `$str[0]` | حذف‌شده از 8.0 |
| F-11 | `(unset)` cast | حذف | حذف‌شده از 8.0 |
| F-12 | `implode()` با ترتیب معکوس آرگومان‌ها | `implode(string $sep, array $arr)` | 7.4 Deprecated |
| F-13 | Pass-by-reference در فراخوانی تابع (`func(&$x)`) | امضای تابع `&$x` | 5.3 Deprecated |
| F-14 | `#[\ReturnTypeWillChange]` به‌جای تایپ واقعی | افزودن Return Type صریح | 8.1 موقت |
| F-15 | Implicit nullable (`function f(Foo $x = null)`) | صریح: `function f(?Foo $x = null)` | **8.4 Deprecated** |
| F-16 | `E_STRICT` در `error_reporting` | حذف؛ در 8.4 بی‌اثر | 8.4 Deprecated |
| F-17 | استفاده از `session_set_save_handler` با آرگومان‌های قدیمی | شیء `SessionHandlerInterface` | — |
| F-18 | `ReflectionParameter::getClass()`, `isCallable()` و… منسوخ | `getType()` + `NamedType` | 8.0 Deprecated |
| F-19 | `date()` با timezone ضمنی (بدون تنظیم) | `date_default_timezone_set('UTC')` در بوت یا `DateTimeImmutable` با TZ صریح | — |
| F-20 | `mt_rand` برای توکن امنیتی | `random_int` / `random_bytes` | — |
| F-21 | `unserialize()` روی دادهٔ کاربر | `json_decode` + Allowlist | — |
| F-22 | `assert()` با رشته | `assert(bool $expr)` | — |
| F-23 | Static Property در Trait بدون تفکیک | اجتناب؛ State در Container | — |
| F-24 | `#[\SensitiveParameter]` فراموش‌شده برای پارامترهای رمز | افزودن Attribute | 8.2 توصیه |
| F-25 | `readonly` روی کلاس با Clone ناقص | پیاده‌سازی `__clone` صریح یا `readonly class` | — |
| F-26 | `enum` با `match` ناقص (بدون `default`) | پوشش کامل caseها | — |
| F-27 | `mixed` به‌جای تایپ دقیق در API عمومی | Union Type یا Generic PHPDoc | — |
| F-28 | فراخوانی `parent::__construct()` از کلاس بدون والد | حذف | — |
| F-29 | `Serializable` interface | `__serialize()/__unserialize()` | 8.1 Deprecated / 9.0 حذف |
| F-30 | `final private` در کلاس غیر final | `private` ساده یا `final` روی متد protected | 8.0 Deprecated |

### ۲٫۲ موارد «ممنوع مطلق» (نه فقط Deprecated)

```
❌ eval()                     ❌ goto                     ❌ global $var
❌ extract() روی ورودی کاربر   ❌ compact() مبهم            ❌ suppress با @
❌ require/include پویا از ورودی کاربر
❌ shell_exec با ورودی غیر‌پالوده
❌ die()/exit() در کد کتابخانه‌ای (فقط در Entry Point)
❌ print_r برای خروجی تولید
❌ sleep() در کد تولید (فقط تست/بک‌آف صف)
```

---

## ۳. قابلیت‌های اجباری PHP 8.2+ (استفاده شود)

| قابلیت | نسخه | کاربرد در تَوان | مثال |
|---|---|---|---|
| `readonly class` | 8.2 | همهٔ Value Object و DTO | `final readonly class Money {}` |
| `readonly` property | 8.1 | Config/Request/Event | `public readonly string $id;` |
| DNF Types | 8.2 | قراردادهای دقیق | `(A&B)\|null $x` |
| `null`/`false`/`true` standalone types | 8.2 | امضای دقیق | `public function save(): true` |
| Enum (Backed) | 8.1 | وضعیت‌ها، نوع‌ها | `enum Status: string` |
| `never` return type | 8.1 | متدهای پرتاب‌کننده | `function fail(): never` |
| Fibers | 8.1 | Async Runtime / Queue | `Fiber::suspend()` |
| First-class Callable Syntax | 8.1 | ثبت Listener | `$this->handle(...)` |
| Named Arguments | 8.0 | API خوانا | `render(view: 'x', data: [])` |
| Constructor Promotion | 8.0 | کاهش Boilerplate | `__construct(private readonly string $a)` |
| Attributes | 8.0 | Route/Middleware/Validation/Cache | `#[Route('/users')]` |
| `match` | 8.0 | جایگزین switch | `match($s) { ... }` |
| Nullsafe operator | 8.0 | زنجیرهٔ امن | `$u?->profile?->avatar` |
| Intersection Types | 8.1 | Contract ترکیبی | `Countable&Traversable` |
| `array_is_list` | 8.1 | بررسی آرایه | — |
| `json_validate` | 8.3 | اعتبارسنجی سریع JSON | `if (json_validate($s))` |
| Typed Class Constants | 8.3 | ثابت‌های نوع‌دار | `public const string NAME = 'x';` |
| `#[\Override]` | 8.3 | ایمنی Override | — |
| Dynamic Class Constant Fetch | 8.3 | `{$class}::{$const}` | — |
| Property Hooks | 8.4 | getter/setter تمیز | `public string $name { get => ...; }` |
| Asymmetric Visibility | 8.4 | `public private(set)` | DTO |
| `array_find/any/all` | 8.4 | Collection داخلی | — |
| `mb_trim/mb_ucfirst` | 8.4 | String Engine | — |
| New `without`/`with` on Clone | 8.4 | Immutable Copy | — |

> ⚠️ قاعدهٔ سازگاری: اگر قابلیتی فقط در 8.3/8.4 هست (مثل Property Hooks)، استفاده از آن در هسته
> **مشروط** به `feature_exists`/polyfill داخلی است تا حداقل نسخه 8.2 حفظ شود. در غیر این صورت ممنوع.

### ۳٫۱ استراتژی سازگاری نسخه (Version Compatibility Policy) 🔒

```php
// الگوی مجاز برای قابلیت‌های نسخه‌های بالاتر
if (PHP_VERSION_ID >= 80300 && function_exists('json_validate')) {
    $valid = json_validate($raw);          // مسیر سریع
} else {
    $valid = self::fallbackJsonValidate($raw);   // مسیر سازگار
}
```
- 🔒 هیچ `if (PHP_VERSION_ID < 80200)` مجاز نیست — 8.2 کف مطلق است.
- ⚠️ هر Fallback باید تست اختصاصی داشته باشد (با Mock نسخه).
- 💡 Polyfillها در `engines/compat/` متمرکز شوند، نه پراکنده در کد.

---

## ۴. قواعد تایپ‌سازی (Typing Rules)

| # | قاعده | سطح |
|---|---|---|
| T-01 | همهٔ Propertyها تایپ‌دار (بدون `var`، بدون untyped) | 🔒 |
| T-02 | همهٔ پارامترها و Return Type صریح (شامل `void`/`never`) | 🔒 |
| T-03 | `mixed` فقط در مرز دادهٔ خام و با PHPDoc دقیق | 🔒 |
| T-04 | `?T` به‌جای `T\|null` (یکدستی) | ⚠️ |
| T-05 | برای آرایه‌ها، PHPDoc `array<key, value>` یا `list<T>` | 🔒 |
| T-06 | Generic با PHPDoc: `@template T`, `@implements`, `@var Collection<int, User>` | ⚠️ |
| T-07 | `iterable` در ورودی، `array`/Collection در خروجی (اصل Postel) | ⚠️ |
| T-08 | Enum به‌جای رشته/عدد ثابت برای مجموعهٔ بسته | 🔒 |
| T-09 | Value Object برای پول/تاریخ/ایمیل/شماره — هرگز primitive وسواسی | ⚠️ |
| T-10 | `readonly` برای هر چیزی که پس از ساخت تغییر نمی‌کند | 🔒 |
| T-11 | Nullable بودن باید معنای دامنه‌ای داشته باشد، نه راحتی | ⚠️ |
| T-12 | Covariant Return / Contravariant Param در Override رعایت شود | 🔒 |

### ۴٫۱ الگوی Value Object صحیح

```php
<?php
declare(strict_types=1);

namespace Tavaan\Support\ValueObject;

/**
 * مقدار پول — هرگز float استفاده نمی‌شود.
 *
 * @immutable
 * @api 1.0
 */
final readonly class Money
{
    /** @param int $amount مبلغ بر حسب کوچک‌ترین واحد (ریال/سنت) */
    private function __construct(
        public int $amount,
        public Currency $currency,
    ) {
    }

    public static function of(int|string $amount, Currency $currency): self
    {
        return new self(self::toMinor((string) $amount, $currency), $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency->value];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException(
                messageId: 'money.currency_mismatch',
                context: ['expected' => $this->currency->value, 'given' => $other->currency->value],
            );
        }
    }

    private static function toMinor(string $amount, Currency $currency): int
    {
        if (!preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidAmountException(messageId: 'money.invalid_amount', context: ['amount' => $amount]);
        }
        // …
    }
}
```

---

## ۵. قواعد شیءگرایی (OOP Rules)

| # | قاعده | سطح |
|---|---|---|
| O-01 | همهٔ کلاس‌های هسته `final` مگر برای گسترش طراحی‌شده (آنگاه `abstract` + ADR) | 🔒 |
| O-02 | `private` پیش‌فرض؛ `protected` فقط در کلاس پایهٔ قابل گسترش؛ `public` کمینه | 🔒 |
| O-03 | Composition بر Inheritance؛ ارث حداکثر ۲ سطح | 🔒 |
| O-04 | Interface بدون `self::` static؛ Contractها کوچک و تک‌منظوره (ISP) | 🔒 |
| O-05 | هر کلاس یک مسئولیت؛ اگر نامش `and` داشت → تجزیه | 🔒 |
| O-06 | Constructor فقط Assign/Validate؛ بدون I/O و بدون Side Effect | 🔒 |
| O-07 | Static State ممنوع در کلاس‌های سرویس (فقط Helperهای Stateless) | 🔒 |
| O-08 | `__call/__get/__set` فقط با دلیل مستند و در کلاس‌های مشخص (Facade/Collection) | 🔒 |
| O-09 | Trait فقط برای قابلیت افقی بدون State (مثل `Macroable` محدود) | ⚠️ |
| O-10 | `clone` برای Immutable Copy با `__clone` صریح | ⚠️ |
| O-11 | `enum` برای وضعیت + متد کمکی (`label()`, `canTransitionTo()`) | 🔒 |
| O-12 | Exceptionها `final` و از سلسله‌مراتب `TavaanException` | 🔒 |

### ۵٫۱ الگوی Enum استاندارد

```php
<?php
declare(strict_types=1);

namespace Tavaan\Engine\Shop\Domain;

use Tavaan\Engine\I18n\TranslatableEnum;
use Tavaan\Support\Enum\EnumHelpers;

enum OrderStatus: string implements TranslatableEnum
{
    use EnumHelpers;

    case Draft     = 'draft';
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Shipping  = 'shipping';
    case Delivered = 'delivered';
    case Canceled  = 'canceled';
    case Refunded  = 'refunded';

    /** ماشین حالت: گذارهای مجاز */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft     => $to === self::Pending || $to === self::Canceled,
            self::Pending   => $to === self::Paid || $to === self::Canceled,
            self::Paid      => $to === self::Shipping || $to === self::Refunded,
            self::Shipping  => $to === self::Delivered,
            self::Delivered => $to === self::Refunded,
            self::Canceled, self::Refunded => false,
        };
    }

    public function translationKey(): string
    {
        return 'shop.order.status.' . $this->value;
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Canceled, self::Refunded, self::Delivered => true,
            default => false,
        };
    }
}
```

---

## ۶. قواعد آرایه، رشته و ریاضی

| # | قاعده |
|---|---|
| A-01 | 🔒 برای پول/مقادیر دقیق هرگز `float`؛ از `int` (minor unit) یا `bcmath` (در صورت وجود) یا کلاس `Decimal` خودنوشته |
| A-02 | 🔒 مقایسهٔ float با `abs($a-$b) < EPSILON` |
| A-03 | 🔒 رشته‌های چندبایتی فقط با `mb_*` و Charset صریح `UTF-8` |
| A-04 | ⚠️ `str_contains/str_starts_with/str_ends_with` به‌جای `strpos !== false` |
| A-05 | 🔒 `sprintf` با آرگومان نامی/شماره‌دار برای ترجمه |
| A-06 | ⚠️ `array_map` با Key Preservation نیازمند `array_combine` |
| A-07 | 🔒 برای مجموعه‌های بزرگ از `Generator`/`iterator` استفاده شود (حافظه) |
| A-08 | ⚠️ `list()` unpacking با کلید صریح در 8.1+ |
| A-09 | 🔒 `intdiv()` برای تقسیم صحیح، نه `(int)($a/$b)` |
| A-10 | 🔒 `hash_equals` برای مقایسهٔ HMAC/Token |

### ۶٫۱ کلاس `Decimal` خودنوشته (بدون bcmath)

الزام: موتور `Tavaan\Support\Math\Decimal` باید بدون `ext-bcmath` کار کند.

| متد | امضا | توضیح |
|---|---|---|
| `of` | `static of(int\|string $value, int $scale = 18): self` | ساخت با نرمال‌سازی |
| `add` | `add(self $o): self` | جمع |
| `sub` | `sub(self $o): self` | تفریق |
| `mul` | `mul(self $o, ?int $scale = null): self` | ضرب |
| `div` | `div(self $o, ?int $scale = null): self` | تقسیم (خطا بر صفر) |
| `mod` | `mod(self $o): self` | باقی‌مانده |
| `pow` | `pow(int $n): self` | توان صحیح |
| `round` | `round(int $scale, RoundingMode $mode): self` | گردکردن (HALF_UP, HALF_EVEN, DOWN, UP, CEILING, FLOOR) |
| `compare` | `compare(self $o): int` | -1/0/1 |
| `toString` | `toString(): string` | بدون Notation علمی |

**الگوریتم مرجع:** نگهداری به‌صورت `string` صحیح + `scale`؛ پیاده‌سازی Long Arithmetic روی
رقم‌ها در بلوک‌های ۹‌تایی (`int` 64bit-safe) → سرعت مناسب بدون ext. تست با Property-Based (۱۰۰۰۰ حالت).

---

## ۷. قواعد همزمانی و Fiber

| # | قاعده |
|---|---|
| C-01 | 🔒 هسته باید Coroutine-Safe باشد: هیچ Global Mutable State |
| C-02 | 🔒 Request Context در `Tavaan\Core\Context\Context` با `Fiber`-aware storage |
| C-03 | ⚠️ برای Swoole: `Swoole\Coroutine::getContext()` اگر موجود، وگرنه `static` با کلید Fiber ID |
| C-04 | 🔒 ConnectionPool برای DB/Redis در حالت Resident |
| C-05 | 🔒 ممنوعیت `static $cache` بدون محدودیت اندازه (Memory Leak در Worker) |

---

## ۸. قواعد Attribute (Annotation جایگزین)

| Attribute | هدف | پارامترها |
|---|---|---|
| `#[Route]` | تعریف روت روی متد کنترلر | `path, methods, name, middleware, domain, where` |
| `#[Middleware]` | ثبت Middleware روی کنترلر/متد | `class[], priority` |
| `#[Validate]` | قواعد اعتبارسنجی | `rules[]` |
| `#[Policy]` | اتصال مجوز | `ability, model` |
| `#[Cacheable]` | کش پاسخ/متد | `key, ttl, tags, unless` |
| `#[Transactional]` | تراکنش خودکار | `isolation, retries, on` |
| `#[Observed]` | ثبت Audit | `event, fields` |
| `#[Inject]` | تزریق صریح | `name` |
| `#[Singleton]` | ثبت به‌عنوان Singleton | — |
| `#[Deprecated]` | علامت‌گذاری داخلی | `since, removal, use` |
| `#[Api]` | متادیتای OpenAPI | `summary, tags, responses` |
| `#[Column]` | نگاشت ORM | `type, nullable, default, cast` |
| `#[Relation]` | رابطهٔ ORM | `type, model, foreignKey, localKey` |

> 🔒 Attributeها فقط با `Attribute::TARGET_*` صریح و Constructor Promotion تعریف شوند.
> 🔒 خواندن Attributeها باید کش شود (Reflection گران است).

---

## ۹. چک‌لیست بازبینی خودکار (Automated Review Checklist)

```yaml
# machine-readable: tavaan.lint.rules.v1
rules:
  - id: TV-DECL-STRICT      # declare(strict_types=1) در هر فایل
    severity: error
  - id: TV-NO-DYNAMIC-PROP  # عدم تعریف property پویا
    severity: error
  - id: TV-NO-IMPLICIT-NULL # بدون `Type $x = null`
    severity: error
  - id: TV-NO-EVAL          # بدون eval/goto/global
    severity: error
  - id: TV-NO-FLOAT-MONEY   # ممنوعیت float برای money
    severity: error
    pattern: '(float)\s+\$(amount|price|total|balance)'
  - id: TV-NO-MT-RAND-SEC   # ممنوعیت mt_rand/rand برای توکن
    severity: error
  - id: TV-NO-UNSERIALIZE   # ممنوعیت unserialize روی ورودی
    severity: error
  - id: TV-RAW-SQL-CONCAT   # ممنوعیت الحاق رشته در SQL
    severity: error
  - id: TV-NO-DEPRECATED    # صفر E_DEPRECATED در CI
    severity: error
  - id: TV-TYPED-RETURN     # Return Type صریح
    severity: error
  - id: TV-READONLY-VO      # readonly برای Value Object
    severity: warning
  - id: TV-FINAL-CLASS      # final بودن کلاس‌های هسته
    severity: warning
  - id: TV-METHOD-LENGTH    # ≤ 50 خط
    severity: warning
  - id: TV-CLASS-LENGTH     # ≤ 300 خط
    severity: warning
  - id: TV-COMPLEXITY       # ≤ 10
    severity: warning
  - id: TV-NO-VENDOR        # ممنوعیت require پکیج خارجی
    severity: error
  - id: TV-I18N-STRING      # پیام کاربر بدون کلید ترجمه
    severity: warning
```

### ۹٫۱ اجرای دستی در CI

```bash
# ۱) بررسی نحوی همهٔ فایل‌ها
find core engines modules -name '*.php' -print0 | xargs -0 -n1 php -l

# ۲) تبدیل Deprecated به خطا
php -d error_reporting=E_ALL -d display_errors=0 bin/tvaan.php about

# ۳) ماتریس نسخه‌ها
for v in 8.2 8.3 8.4; do docker run --rm -v $PWD:/app php:$v-cli php /app/bin/tvaan.php test; done

# ۴) ممنوعیت‌های متنی
! grep -RnE '\beval\(|\bgoto\b|\$[a-zA-Z_]+\{[0-9$]|utf8_(en|de)code|strftime\(' core engines modules
```

---

## ۱۰. قالب تنظیم `error_reporting` در محیط‌ها

```php
// environments
'development' => [
    'display_errors'      => true,
    'error_reporting'     => E_ALL,
    'log_errors'          => true,
    'exception_render'    => 'detailed',   // trace + snippet
],
'production' => [
    'display_errors'      => false,
    'error_reporting'     => E_ALL & ~E_DEPRECATED,  // لاگ می‌شود، نمایش نمی‌شود
    'log_errors'          => true,
    'exception_render'    => 'safe',       // MessageId + RefCode
],
'ci' => [
    'display_errors'      => false,
    'error_reporting'     => E_ALL,        // Deprecated = Failure
    'convert_deprecations_to_exceptions' => true,
],
```
> 🔒 در CI، `E_DEPRECATED` **باید** به `ErrorException` تبدیل و باعث شکست شود.
