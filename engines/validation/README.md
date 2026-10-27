# موتور اعتبارسنجی — `Validation`

> M3 — ۸۵+ قاعده (شامل قواعد ایرانی 🇮🇷)، FormRequest، Attribute-based، wildcard، ReDoS guard.
>
> مستند کامل: [VALIDATION](../../docs/03-engines/validation.md) • تیکت: TVN-0035 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.validation` |
| namespace | `Tavaan\Engine\Validation\` |
| Facade | `Tavaan\Core\Facade\Facades\Validator` (accessor: `validator`) |
| پیکربندی | `config/validation.php` → `config('validation.*')` |
| Provider | `Tavaan\Engine\Validation\ValidationServiceProvider` |
| وابستگی سخت | `i18n`، `config` |
| وابستگی نرم | `database`، `http` |
| آمار | 25 فایل / ~3,104 خط در src • 3 فایل تست (38 متد) |

## شروع سریع

```bash
# تست‌های موتور (38 متد)
php bin/tvn test engines/validation

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Validator;

// API عمومی و مثال‌ها: docs/03-engines/validation.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/validation/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/validation.md`](../../docs/03-engines/validation.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
