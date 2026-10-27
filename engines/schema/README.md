# موتور ساختار دیتابیس (Blueprint/DDL) — `Schema`

> کد در engines/database/src/Schema (namespace مطابق DOC-048 machine block).
>
> مستند کامل: [SCHEMA](../../docs/03-engines/schema.md) • تیکت: TVN-0031 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.schema` |
| namespace | `Tavaan\Engine\Database\Schema\` |
| Facade | `Tavaan\Core\Facade\Facades\Schema` (accessor: `schema`) |
| Provider | `Tavaan\Engine\Database\Schema\SchemaServiceProvider` |
| وابستگی سخت | `database` |
| آمار | 0 فایل / ~0 خط در src • 2 فایل تست (17 متد) |

## شروع سریع

```bash
# تست‌های موتور (17 متد)
php bin/tvn test engines/schema

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Schema;

// API عمومی و مثال‌ها: docs/03-engines/schema.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/schema/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/schema.md`](../../docs/03-engines/schema.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
