# موتور صف و Worker — `Queue`

> M5 — ۵ درایور (sync/database/redis/file/null). Worker مقاوم با Retry/Backoff نمایی، Batch/Chain، Rate Limiting، Failed Jobs + DLQ. 🔒 سریال‌سازی فقط JSON + Allowlist؛ Pop اتمیک با SKIP LOCKED. ♻️ محافظ بازگشت (0.5.1): تشخیص ارجاع دوری + سقف عمق ۶۴ + رد زنجیرهٔ دوری با QueueException تمیز.
>
> مستند کامل: [QUEUE](../../docs/03-engines/queue.md) • تیکت: TVN-0061 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.queue` |
| namespace | `Tavaan\Engine\Queue\` |
| Facade | `Tavaan\Core\Facade\Facades\Queue` (accessor: `queue`) |
| پیکربندی | `config/queue.php` → `config('queue.*')` |
| Provider | `Tavaan\Engine\Queue\QueueServiceProvider` |
| وابستگی سخت | `config`، `event` |
| وابستگی نرم | `database`، `cache`، `log` |
| جداول | `tvn_jobs`، `tvn_failed_jobs`، `tvn_job_batches` |
| فرمان‌های CLI | `tvn queue:work`، `tvn queue:listen`، `tvn queue:restart`، `tvn queue:retry`، `tvn queue:failed`، `tvn queue:flush`، `tvn queue:prune-failed`، `tvn queue:monitor` |
| آمار | 48 فایل / ~5,049 خط در src • 2 فایل تست (47 متد) |

## شروع سریع

```bash
# تست‌های موتور (47 متد)
php bin/tvn test engines/queue

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

```php
// پس از بوت فریمورک — دسترسی از راه Facade:
use Tavaan\Core\Facade\Facades\Queue;

// API عمومی و مثال‌ها: docs/03-engines/queue.md (بخش public_api بلوک ماشین‌خوان)
```

## ساختار

```
engines/queue/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/queue.md`](../../docs/03-engines/queue.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
