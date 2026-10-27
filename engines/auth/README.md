# موتور احراز هویت — `Auth`

> M4 — چند Guard (session/jwt)، JWT خودنوشته با algorithm pinning، refresh rotation + reuse detection، OTP یک‌بارمصرف، TOTP RFC 6238، Device tracking، Session hardening، Password broker.
>
> مستند کامل: [AUTH](../../docs/03-engines/auth.md) • تیکت: TVN-0056 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.auth` |
| namespace | `Tavaan\Engine\Auth\` |
| Facade | `Tavaan\Core\Facade\Facades\Auth` (accessor: `auth`) |
| پیکربندی | `config/auth.php` → `config('auth.*')` |
| Provider | `Tavaan\Engine\Auth\AuthServiceProvider` |
| وابستگی سخت | `session`، `hash`، `crypt`، `event`، `database`، `cache`، `config` |
| وابستگی نرم | `http`، `orm`، `notification` |
| جداول | `password_resets`، `personal_access_tokens`، `user_devices`، `two_factor_secrets` |
| آمار | 25 فایل / ~3,052 خط در src • 1 فایل تست (23 متد) |

## شروع سریع

```bash
# تست‌های موتور (23 متد)
php bin/tvn test engines/auth

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Auth;

// API عمومی و مثال‌ها: docs/03-engines/auth.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/auth/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/auth.md`](../../docs/03-engines/auth.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
