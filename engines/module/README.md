# موتور کشف و مدیریت ماژول — `Module`

> موتور `module` فریمورک تَوان.
>
> مستند کامل: [MODULE](../../docs/03-engines/module.md) • تیکت: TVN-0010 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.module` |
| namespace | `Tavaan\Engine\Module\` |
| Facade | `Tavaan\Core\Facade\Facades\Module` (accessor: `modules`) |
| Provider | `Tavaan\Engine\Module\ModuleServiceProvider` |
| وابستگی سخت | `container`، `config` |
| فرمان‌های CLI | `tvn module:list`، `tvn module:enable`، `tvn module:disable`، `tvn module:validate` |
| آمار | 14 فایل / ~1,695 خط در src • 2 فایل تست (21 متد) |

## شروع سریع

```bash
# تست‌های موتور (21 متد)
php bin/tvn test engines/module

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Module;

// API عمومی و مثال‌ها: docs/03-engines/module.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/module/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/module.md`](../../docs/03-engines/module.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
