# موتور مدیریت خطا — `Error`

> موتور `error` فریمورک تَوان.
>
> مستند کامل: [ERROR](../../docs/03-engines/error.md) • تیکت: TVN-0006 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.error` |
| namespace | `Tavaan\Engine\Error\` |
| پیکربندی | `config/error.php` → `config('error.*')` |
| Provider | `Tavaan\Engine\Error\ErrorServiceProvider` |
| وابستگی سخت | `config`، `log` |
| وابستگی نرم | `event` |
| فرمان‌های CLI | `tvn error:simulate` |
| آمار | 10 فایل / ~1,101 خط در src • 2 فایل تست (19 متد) |

## شروع سریع

```bash
# تست‌های موتور (19 متد)
php bin/tvn test engines/error

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/error/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/error.md`](../../docs/03-engines/error.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
