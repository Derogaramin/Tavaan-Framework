# موتور امنیت عرضی (CSRF/RateLimit/Sanitizer/SSRF) — `Security`

> M4 — CSRF (double-submit)، RateLimit (sliding/fixed/token-bucket)، Security Headers، HTML Sanitizer خالص (allowlist)، SSRF Guard با DNS pinning، Input Normalizer، Signature.
>
> مستند کامل: [SECURITY](../../docs/03-engines/security.md) • تیکت: TVN-0059 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.security` |
| namespace | `Tavaan\Engine\Security\` |
| Facade | `Tavaan\Core\Facade\Facades\Security` (accessor: `security`) |
| پیکربندی | `config/security.php` → `config('security.*')` |
| Provider | `Tavaan\Engine\Security\SecurityServiceProvider` |
| وابستگی سخت | `http`، `crypt`، `cache`، `config` |
| وابستگی نرم | `session`، `router` |
| آمار | 11 فایل / ~1,436 خط در src • 1 فایل تست (18 متد) |

## شروع سریع

```bash
# تست‌های موتور (18 متد)
php bin/tvn test engines/security

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Security;

// API عمومی و مثال‌ها: docs/03-engines/security.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/security/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/security.md`](../../docs/03-engines/security.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
