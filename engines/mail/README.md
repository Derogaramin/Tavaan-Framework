# موتور پست الکترونیک — `Mail`

> M5 — SMTP client خالص خودنوشته (STARTTLS/SSL + AUTH LOGIN/PLAIN، بدون ext)، MIME builder با RFC 2047 فارسی، ۶ transport (smtp/sendmail/mail/log/array/ses)، Mailable + Queue + Fake/Assertions. 🔒 محافظ Header Injection.
>
> مستند کامل: [MAIL](../../docs/03-engines/mail.md) • تیکت: TVN-0065 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.mail` |
| namespace | `Tavaan\Engine\Mail\` |
| Facade | `Tavaan\Core\Facade\Facades\Mail` (accessor: `mailer`) |
| پیکربندی | `config/mail.php` → `config('mail.*')` |
| Provider | `Tavaan\Engine\Mail\MailServiceProvider` |
| وابستگی سخت | `config`، `event` |
| وابستگی نرم | `view`، `queue`، `log`، `filesystem` |
| آمار | 18 فایل / ~2,266 خط در src • 1 فایل تست (17 متد) |

## شروع سریع

```bash
# تست‌های موتور (17 متد)
php bin/tvn test engines/mail

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Mail;

// API عمومی و مثال‌ها: docs/03-engines/mail.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/mail/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/mail.md`](../../docs/03-engines/mail.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
