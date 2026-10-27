# موتور تقویم جلالی — `Jalali`

> کد در engines/i18n/src/Jalali (namespace مطابق DOC-070). الگوریتم breaks-based jalaali-js — تطبیق ۱۰۰٪ با intl.
>
> مستند کامل: [JALALI](../../docs/03-engines/jalali.md) • تیکت: TVN-0026 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.jalali` |
| namespace | `Tavaan\Engine\I18n\Jalali\` |
| وابستگی سخت | `i18n` |
| آمار | 0 فایل / ~0 خط در src • 0 فایل تست (0 متد) |

## شروع سریع

```bash
# این موتور تست محلی ندارد — پوشش غیرمستقیم از راه سوئیت سراسری
# و تست موتورهای همسایه (مثل router در engines/http/tests)
php bin/tvn test

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/jalali/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/jalali.md`](../../docs/03-engines/jalali.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
