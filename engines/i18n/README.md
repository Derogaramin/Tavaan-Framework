# موتور بین‌المللی‌سازی — `I18n`

> موتور `i18n` فریمورک تَوان.
>
> مستند کامل: [I18N](../../docs/03-engines/i18n.md) • تیکت: TVN-0025 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.i18n` |
| namespace | `Tavaan\Engine\I18n\` |
| Facade | `Tavaan\Core\Facade\Facades\Lang` (accessor: `translator`) |
| پیکربندی | `config/i18n.php` → `config('i18n.*')` |
| Provider | `Tavaan\Engine\I18n\I18nServiceProvider` |
| وابستگی سخت | `config` |
| فرمان‌های CLI | `tvn lang:check` |
| آمار | 7 فایل / ~1,083 خط در src • 2 فایل تست (25 متد) |

## شروع سریع

```bash
# تست‌های موتور (25 متد)
php bin/tvn test engines/i18n

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Lang;

// API عمومی و مثال‌ها: docs/03-engines/i18n.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/i18n/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/i18n.md`](../../docs/03-engines/i18n.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
