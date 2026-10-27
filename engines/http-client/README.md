# موتور کلاینت HTTP — `HttpClient`

> M5 — Fluent API + Retry (نمایی+jitter) + Circuit Breaker + 🔒 SSRF Guard (با re-check در redirectها) + Pool موازی (curl_multi با fallback ترتیبی) + Fake برای تست. Transport: curl → stream (بدون ext).
>
> مستند کامل: [HTTP-CLIENT](../../docs/03-engines/http-client.md) • تیکت: TVN-0064 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.http-client` |
| namespace | `Tavaan\Engine\HttpClient\` |
| Facade | `Tavaan\Core\Facade\Facades\Http` (accessor: `http.client`) |
| پیکربندی | `config/http-client.php` → `config('http-client.*')` |
| Provider | `Tavaan\Engine\HttpClient\HttpClientServiceProvider` |
| وابستگی سخت | `config`، `event` |
| وابستگی نرم | `security`، `cache`، `compat` |
| آمار | 19 فایل / ~2,448 خط در src • 1 فایل تست (22 متد) |

## شروع سریع

```bash
# تست‌های موتور (22 متد)
php bin/tvn test engines/http-client

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Http;

// API عمومی و مثال‌ها: docs/03-engines/http-client.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/http-client/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/http-client.md`](../../docs/03-engines/http-client.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
