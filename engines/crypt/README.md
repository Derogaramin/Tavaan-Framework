# موتور رمزنگاری — `Crypt`

> زودتر از فاز M4 ساخته شد (پیش‌نیاز cookie/session در M2).
>
> مستند کامل: [CRYPT](../../docs/03-engines/crypt.md) • تیکت: TVN-0054 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.crypt` |
| namespace | `Tavaan\Engine\Crypt\` |
| Facade | `Tavaan\Core\Facade\Facades\Crypt` (accessor: `encrypter`) |
| پیکربندی | `config/crypt.php` → `config('crypt.*')` |
| Provider | `Tavaan\Engine\Crypt\CryptServiceProvider` |
| وابستگی سخت | `config` |
| فرمان‌های CLI | `tvn key:generate`، `tvn key:rotate` |
| آمار | 7 فایل / ~415 خط در src • 1 فایل تست (14 متد) |

## شروع سریع

```bash
# تست‌های موتور (14 متد)
php bin/tvn test engines/crypt

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Crypt;

// API عمومی و مثال‌ها: docs/03-engines/crypt.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/crypt/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/crypt.md`](../../docs/03-engines/crypt.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
