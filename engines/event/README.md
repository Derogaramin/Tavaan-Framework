# موتور رویداد و Outbox — `Event`

> Outbox/Inbox با موتور database فعال شد (M3/M4 — ADR-0009): tvn_outbox با claim اتمیک + backoff + releaseStale و event:outbox-relay.
>
> مستند کامل: [EVENT](../../docs/03-engines/event.md) • تیکت: TVN-0004 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.event` |
| namespace | `Tavaan\Engine\Event\` |
| Facade | `Tavaan\Core\Facade\Facades\Event` (accessor: `events`) |
| پیکربندی | `config/event.php` → `config('event.*')` |
| Provider | `Tavaan\Engine\Event\EventServiceProvider` |
| وابستگی سخت | `container`، `config` |
| وابستگی نرم | `queue`، `database`، `realtime` |
| جداول | `tvn_outbox`، `tvn_inbox` |
| فرمان‌های CLI | `tvn event:list`، `tvn event:outbox-relay` |
| آمار | 12 فایل / ~1,261 خط در src • 2 فایل تست (21 متد) |

## شروع سریع

```bash
# تست‌های موتور (21 متد)
php bin/tvn test engines/event

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Event;

// API عمومی و مثال‌ها: docs/03-engines/event.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/event/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/event.md`](../../docs/03-engines/event.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
