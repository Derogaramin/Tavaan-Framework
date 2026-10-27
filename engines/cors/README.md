# موتور اشتراک منابع بین‌مبدأ — `CORS`

> موتور `cors` فریمورک تَوان.
>
> مستند کامل: [CORS](../../docs/03-engines/cors.md) • تیکت: TVN-0027 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.cors` |
| namespace | `Tavaan\Engine\Http\Cors\` |
| پیکربندی | `config/cors.php` → `config('cors.*')` |
| وابستگی سخت | `http`، `config` |
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
engines/cors/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/cors.md`](../../docs/03-engines/cors.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
