# DOC-004 — راهنمای شروع سریع (Quick Start) 🚀

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-004 |
| مخاطب | ایجنت‌های AI و توسعه‌دهندگانی که می‌خواهند ساخت تَوان را آغاز کنند |
| پیش‌نیاز | مطالعهٔ DOC-001، DOC-010، DOC-080 |
| نقش | نقشهٔ عملی «از صفر تا اولین ماژول» |

---

## ۱. قبل از نوشتن هر خط کد

🔒 این ۵ سند را به ترتیب بخوانید — بدون آن‌ها کد شما ریجکت می‌شود:

| # | سند | چرا |
|---|---|---|
| 1 | `docs/00-overview/goals-vision.md` (DOC-001) | بدان چه می‌سازی و چرا |
| 2 | `docs/01-standards/php82-strict.md` (DOC-010) | قوانین سفت‌وسخت زبانی + ممنوعه‌ها |
| 3 | `docs/02-architecture/request-lifecycle.md` (DOC-025) | مدل ذهنی اجرا (۱۴ فاز) |
| 4 | `docs/04-structure/folder-structure.md` (DOC-080) | هر فایل کجا می‌رود |
| 5 | `docs/03-engines/README.md` (DOC-030) | رجیستری موتورها + ترتیب ساخت |

---

## ۲. ترتیب ساخت (بدون پرش)

```
گام ۱  اسکلت پوشه‌ها            → tools/scripts/scaffold-structure.sh
گام ۲  core/bootstrap.php       → DOC-027 §10 (مرجع کامل)
گام ۳  Autoloader               → TVN-0001 / DOC-027 §2
گام ۴  Container                 → TVN-0002 / DOC-031
گام ۵  Config + Env              → TVN-0003 / DOC-032
گام ۶  Event + Outbox            → TVN-0004 / DOC-033
گام ۷  Error + Log               → TVN-0006,0007 / DOC-035,036
گام ۸  Compat                    → TVN-0008 / DOC-083E
گام ۹  Test Framework (حداقلی)   → TVN-0084 / DOC-084E
        ── اینجا M1 تمام است: هسته بوت می‌شود ──
گام ۱۰ HTTP + Router + View      → M2
        ── اینجا اولین صفحهٔ وب بالا می‌آید ──
گام ۱۱ Database + ORM            → M3
        ── اینجا اولین CRUD کار می‌کند ──
```

> ⚠️ موتور `test` باید **زود** ساخته شود (حداقل یک Assertion + Runner) تا بتوانید
> بقیه را با TDD پیش ببرید.

---

## ۳. کمینهٔ قابل اجرا (Minimum Viable Kernel)

برای اینکه «سلام دنیا» بالا بیاید، فقط این‌ها لازم است:

```
core/bootstrap.php          ← نقطهٔ ورود
core/Autoloading/Autoloader.php
core/Application/Application.php
core/Container/…            (یا engines/container)
core/Error/…
engines/config/…
engines/event/…
engines/http/…              (Request/Response/Kernel)
engines/router/…
engines/view/…
public/index.php
config/{app,autoload,providers}.php
routes/web.php
resources/views/welcome.tv.php
```

**خروجی مورد انتظار:**
```bash
$ php bin/tvn serve
Tavaan dev server started on http://127.0.0.1:8000

$ curl http://127.0.0.1:8000/
<!DOCTYPE html>… سلام، تَوان! …        ← welcome.tv.php
```

---

## ۴. چرخهٔ کار هر موتور (الگوی تکرار)

```
┌─ 1. مستند را بخوان (docs/03-engines/<name>.md)
├─ 2. Contractها را در core/Contracts/ بنویس (Contract-First، اصل P2)
├─ 3. معیار پذیرش (AC-*) را به تست تبدیل کن (TDD)
├─ 4. پیاده‌سازی را در engines/<name>/src/ بنویس
├─ 5. Provider را بنویس (register بدون side effect)
├─ 6. config + lang/{fa,en} را کامل کن
├─ 7. بلوک machine-readable مستند را پر کن
└─ 8. چک‌لیست DoD (DOC-113) + Engine Readiness (DOC-030 §8)
```

---

## ۵. ده فرمان کدنویسی (خلاصهٔ اجرایی)

```
۱.  هر فایل با declare(strict_types=1);
۲.  هیچ require/include دستی جز core/bootstrap.php
۳.  هیچ پکیج خارجی در core/ و engines/
۴.  هیچ Property پویا — همه typed/readonly
۵.  هر API عمومی → اول Interface در core/Contracts/
۶.  استثنا با messageId (نه متن خام) + کد + hint
۷.  کوئری فقط Prepared — هرگز الحاق رشته
۸.  خروجی همیشه Escape — ورودی همیشه Validate
۹.  پول = int minor-unit + Money VO — هرگز float
۱۰. قبل از Merge: php -l + PHPStan max + تست سبز + صفر Deprecated
```

---

## ۶. الگوی یک کلاس استاندارد (کپی-پیست)

```php
<?php

declare(strict_types=1);

namespace Tavaan\Engine\Cache;

use Tavaan\Core\Contracts\Cache\StoreInterface;
use Tavaan\Core\Exceptions\Cache\StoreUnavailableException;

/**
 * ذخیره‌ساز کش روی فایل.
 *
 * @api 1.0
 * @since 1.0.0
 * @see docs/03-engines/cache.md
 */
final class FileStore implements StoreInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly string $prefix = 'tvn:',
    ) {
        // 🔒 Constructor فقط assign/validate — بدون I/O سنگین
    }

    public function get(string $key): mixed
    {
        $path = $this->path($key);
        if (!\is_file($path)) {
            return null;
        }
        $raw = \file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $payload = \json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if ($payload['expires'] !== 0 && $payload['expires'] < \time()) {
            $this->forget($key);
            return null;
        }
        return $payload['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        $payload = \json_encode([
            'value'   => $value,
            'expires' => $ttl === 0 ? 0 : \time() + $ttl,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $this->atomicWrite($this->path($key), $payload);
    }

    public function forget(string $key): bool { /* … */ }
    public function flush(): bool { /* … */ }
    public function getPrefix(): string { return $this->prefix; }

    private function path(string $key): string
    {
        $hash = \hash('xxh128', $this->prefix . $key);
        $shard = \substr($hash, 0, 2);                 // توزیع در زیرپوشه‌ها
        return "{$this->directory}/{$shard}/{$hash}.cache";
    }

    /** @throws StoreUnavailableException */
    private function atomicWrite(string $path, string $data): bool
    {
        $dir = \dirname($path);
        if (!\is_dir($dir) && !\mkdir($dir, 0775, true) && !\is_dir($dir)) {
            throw new StoreUnavailableException(messageId: 'cache.dir_unwritable', context: ['dir' => $dir]);
        }
        $tmp = $path . '.' . \bin2hex(\random_bytes(4));
        \file_put_contents($tmp, $data, LOCK_EX);
        return \rename($tmp, $path);
    }
}
```

---

## ۷. چگونه تیکت بعدی را پیدا کنیم؟

```bash
# برای ایجنت: فایل بک‌لاگ را بخوان و اولین تیکت آماده را بردار
# docs/08-roadmap/agent-backlog.md
```
الگوریتم انتخاب (DOC-121 §7):
```
1. تیکت‌هایی با status=todo که همهٔ depends_on آن‌ها done هستند
2. مرتب‌سازی بر اساس: phase صعودی ← priority ← تعداد وابستگی کمتر
3. اولین = تیکت بعدی
```

---

## ۸. ابزارهای کمکی

| ابزار | مسیر | کاربرد |
|---|---|---|
| `verify-docs.py` | `tools/scripts/` | بررسی سلامت مستندات |
| `scaffold-structure.sh` | `tools/scripts/` | ساخت اسکلت پوشه‌ها |
| `tvn` (پس از M1) | `bin/tvn` | CLI اصلی فریمورک |

```bash
# ساخت اسکلت پوشه‌ها
bash tools/scripts/scaffold-structure.sh

# بررسی سلامت مستندات
python3 tools/scripts/verify-docs.py
```

---

## ۹. دام‌های رایج (از همان روز اول پرهیز کنید)

| دام | راه درست |
|---|---|
| `float` برای پول | `Money` VO + `int` minor-unit (ADR-0013) |
| `eval`/`create_function` | ممنوع مطلق (DOC-010) |
| `putenv()` برای ENV | `EnvRepository` در حافظه (DOC-032) |
| Dynamic Property | `readonly`/typed property |
| `function f(Foo $x = null)` | `function f(?Foo $x = null)` (8.4 Deprecated) |
| SQL با الحاق رشته | Prepared + Binding |
| رشتهٔ خام فارسی در کد | کلید ترجمه در `lang/` |
| `catch (\Throwable) {}` خالی | استثناهای مشخص + log |
| State جهانی `static` | Container + Scope |
| تکیه بر `ext-intl` | Fallback خودنوشته (موتور compat) |

---

## ۱۰. تعریف «اولین موفقیت» (First Win)

شما وقتی M1 را تمام کرده‌اید که:

```bash
$ php bin/tvn about
  ┌─────────────────────────────────────┐
  │  تَوان | Tavaan  1.0.0              │
  ├─────────────────────────────────────┤
  │  PHP        8.2.x                    │
  │  Environment  local                 │
  │  Boot time  6.2 ms ✅               │
  │  Engines    11 loaded               │
  │  Modules    0                        │
  │  Cache      file                    │
  │  Zero-dep   ✅ no vendor/           │
  └─────────────────────────────────────┘

$ php bin/tvn test
  ✅ 42 tests passed in 180ms

$ test -d vendor && echo "❌ vendor exists" || echo "✅ zero-dependency"
  ✅ zero-dependency
```

آنگاه آمادهٔ M2 (وب) هستید. 🎯
