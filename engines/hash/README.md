# موتور درهم‌سازی رمز عبور — `Hash`

> M4 — Argon2id (پیش‌فرض) + bcrypt fallback با Rehash خودکار. 🔒 هرگز md5/sha برای رمز.
>
> مستند کامل: [HASH](../../docs/03-engines/hash.md) • تیکت: TVN-0055 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.hash` |
| namespace | `Tavaan\Engine\Hash\` |
| Facade | `Tavaan\Core\Facade\Facades\Hash` (accessor: `hash`) |
| پیکربندی | `config/hash.php` → `config('hash.*')` |
| Provider | `Tavaan\Engine\Hash\HashServiceProvider` |
| وابستگی سخت | `config` |
| آمار | 4 فایل / ~233 خط در src • 1 فایل تست (10 متد) |

## شروع سریع

```bash
# تست‌های موتور (10 متد)
php bin/tvn test engines/hash

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Hash;

// API عمومی و مثال‌ها: docs/03-engines/hash.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/hash/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/hash.md`](../../docs/03-engines/hash.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
