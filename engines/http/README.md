# موتور درخواست/پاسخ و کرنل وب — `Http`

> Routing/Cookie/Cors زیر-namespace همین موتور هستند (مطابق DOC-040/042/046).
>
> مستند کامل: [HTTP](../../docs/03-engines/http.md) • تیکت: TVN-0020 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.http` |
| namespace | `Tavaan\Engine\Http\` |
| پیکربندی | `config/http.php` → `config('http.*')` |
| Provider | `Tavaan\Engine\Http\HttpServiceProvider` |
| وابستگی سخت | `pipeline`، `event`، `error`، `config` |
| وابستگی نرم | `session`، `router`، `view`، `cors`، `cookie`، `security`، `validation` |
| فرمان‌های CLI | `tvn serve`، `tvn route:list`، `tvn route:cache`، `tvn route:clear`، `tvn route:match`، `tvn middleware:list` |
| آمار | 47 فایل / ~6,351 خط در src • 4 فایل تست (70 متد) |

## شروع سریع

```bash
# تست‌های موتور (70 متد)
php bin/tvn test engines/http

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/http/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── benchmarks/      # بنچمارک (اهداف DOC-112)
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/http.md`](../../docs/03-engines/http.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
