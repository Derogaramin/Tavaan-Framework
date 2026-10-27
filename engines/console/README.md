# موتور چارچوب CLI — `Console`

> موتور `console` فریمورک تَوان.
>
> مستند کامل: [CONSOLE](../../docs/03-engines/console.md) • تیکت: TVN-0011 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.console` |
| namespace | `Tavaan\Engine\Console\` |
| پیکربندی | `config/console.php` → `config('console.*')` |
| Provider | `Tavaan\Engine\Console\ConsoleServiceProvider` |
| وابستگی سخت | `container`، `config` |
| وابستگی نرم | `event` |
| فرمان‌های CLI | `tvn list`، `tvn about`، `tvn version`، `tvn env`، `tvn doctor`، `tvn optimize`، `tvn test` |
| آمار | 31 فایل / ~3,359 خط در src • 2 فایل تست (22 متد) |

## شروع سریع

```bash
# تست‌های موتور (22 متد)
php bin/tvn test engines/console

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/console/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/console.md`](../../docs/03-engines/console.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
