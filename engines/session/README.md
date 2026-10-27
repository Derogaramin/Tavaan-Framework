# موتور نشست — `Session`

> همهٔ درایورها فعال: file/cookie/array/database (M3) + redis (M4 — روی موتور cache با fallback به file).
>
> مستند کامل: [SESSION](../../docs/03-engines/session.md) • تیکت: TVN-0022 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.session` |
| namespace | `Tavaan\Engine\Session\` |
| Facade | `Tavaan\Core\Facade\Facades\Session` (accessor: `session`) |
| پیکربندی | `config/session.php` → `config('session.*')` |
| Provider | `Tavaan\Engine\Session\SessionServiceProvider` |
| وابستگی سخت | `http`، `crypt`، `config` |
| وابستگی نرم | `database`، `cache` |
| جداول | `sessions` |
| فرمان‌های CLI | `tvn session:clear` |
| آمار | 12 فایل / ~1,141 خط در src • 2 فایل تست (21 متد) |

## شروع سریع

```bash
# تست‌های موتور (21 متد)
php bin/tvn test engines/session

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Session;

// API عمومی و مثال‌ها: docs/03-engines/session.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/session/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/session.md`](../../docs/03-engines/session.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
