# موتور سازگاری و Polyfill — `Compat`

> موتور `compat` فریمورک تَوان.
>
> مستند کامل: [COMPAT](../../docs/03-engines/compat.md) • تیکت: TVN-0008 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.compat` |
| namespace | `Tavaan\Engine\Compat\` |
| پیکربندی | `config/compat.php` → `config('compat.*')` |
| Provider | `Tavaan\Engine\Compat\CompatServiceProvider` |
| فرمان‌های CLI | `tvn doctor` |
| آمار | 7 فایل / ~890 خط در src • 1 فایل تست (12 متد) |

## شروع سریع

```bash
# تست‌های موتور (12 متد)
php bin/tvn test engines/compat

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/compat/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/compat.md`](../../docs/03-engines/compat.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
