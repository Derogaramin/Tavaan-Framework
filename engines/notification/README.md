# موتور آگاه‌سازی چندکاناله — `Notification`

> M5 — ۸ کانال (mail/sms/database/broadcast/push/webhook/telegram/bale/eitaa)، ۵ درگاه پیامک ایرانی (کاوه‌نگار/ملی‌پیامک/قاصدک/SMS.ir/فراز) + Log، In-App با unread/markAsRead، 🔒 Rate Limit ضد SMS Bombing، mask شماره در لاگ، AnonymousNotifiable، Fake/Assertions.
>
> مستند کامل: [NOTIFICATION](../../docs/03-engines/notification.md) • تیکت: TVN-0066 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.notification` |
| namespace | `Tavaan\Engine\Notification\` |
| Facade | `Tavaan\Core\Facade\Facades\Notification` (accessor: `notification`) |
| پیکربندی | `config/notification.php` → `config('notification.*')` |
| Provider | `Tavaan\Engine\Notification\NotificationServiceProvider` |
| وابستگی سخت | `config`، `event` |
| وابستگی نرم | `mail`، `queue`، `database`، `http-client`، `cache`، `log` |
| جداول | `tvn_notifications` |
| آمار | 33 فایل / ~2,455 خط در src • 1 فایل تست (16 متد) |

## شروع سریع

```bash
# تست‌های موتور (16 متد)
php bin/tvn test engines/notification

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Notification;

// API عمومی و مثال‌ها: docs/03-engines/notification.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/notification/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/notification.md`](../../docs/03-engines/notification.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
