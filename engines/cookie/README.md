# موتور کوکی رمزنگاری‌شده — `Cookie`

> کد در engines/http/src/Cookie (namespace مطابق DOC-042).
>
> مستند کامل: [COOKIE](../../docs/03-engines/cookie.md) • تیکت: TVN-0023 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.cookie` |
| namespace | `Tavaan\Engine\Http\Cookie\` |
| Facade | `Tavaan\Core\Facade\Facades\Cookie` (accessor: `cookie`) |
| وابستگی سخت | `http`، `crypt` |
| آمار | 0 فایل / ~0 خط در src • 0 فایل تست (0 متد) |

## شروع سریع

```bash
# این موتور تست محلی ندارد — پوشش غیرمستقیم از راه سوئیت سراسری
# و تست موتورهای همسایه (مثل router در engines/http/tests)
php bin/tvn test

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Cookie;

// API عمومی و مثال‌ها: docs/03-engines/cookie.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/cookie/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/cookie.md`](../../docs/03-engines/cookie.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
