# موتور دیتابیس (اتصال و Query Builder) — `Database`

> M3 — Prepared Statement ۱۰۰٪، Grammar سه درایور، Savepoint، Read/Write Splitting، QueryLog + N+1.
>
> مستند کامل: [DATABASE](../../docs/03-engines/database.md) • تیکت: TVN-0030، TVN-0034 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.database` |
| namespace | `Tavaan\Engine\Database\` |
| Facade | `Tavaan\Core\Facade\Facades\DB` (accessor: `db`) |
| پیکربندی | `config/database.php` → `config('database.*')` |
| Provider | `Tavaan\Engine\Database\DatabaseServiceProvider` |
| وابستگی سخت | `config`، `event`، `log` |
| آمار | 82 فایل / ~11,916 خط در src • 3 فایل تست (43 متد) |

## شروع سریع

```bash
# تست‌های موتور (43 متد)
php bin/tvn test engines/database

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\DB;

// API عمومی و مثال‌ها: docs/03-engines/database.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/database/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/database.md`](../../docs/03-engines/database.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
