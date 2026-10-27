# موتور پیشخان و CRUD Generator — `Admin`

> M8/TVN-0078: CRUD generator declarative (۲۷ نوع فیلد، validation خودکار، jalali_date دوطرفه، sanitizer برای richtext)، منوی پویا (manifest + permission filter)، ویجت/نمودار SVG داشبورد، RTL، theme override در resources/views/vendor/admin.
>
> مستند کامل: [ADMIN](../../docs/03-engines/admin.md) • تیکت: TVN-0078 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.admin` |
| namespace | `Tavaan\Engine\Admin\` |
| Facade | `Tavaan\Core\Facade\Facades\Admin` (accessor: `admin`) |
| Provider | `Tavaan\Engine\Admin\AdminServiceProvider` |
| وابستگی سخت | `view`، `rbac`، `orm`، `validation`، `http` |
| آمار | 15 فایل / ~2,526 خط در src • 2 فایل تست (39 متد) |

## شروع سریع

```bash
# تست‌های موتور (39 متد)
php bin/tvn test engines/admin

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Admin;

// API عمومی و مثال‌ها: docs/03-engines/admin.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/admin/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/admin.md`](../../docs/03-engines/admin.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
