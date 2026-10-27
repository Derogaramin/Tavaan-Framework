# موتور بلادرنگ — `Realtime`

> M6 — SSE بدون هیچ ext (StreamedResponse + heartbeat + retry)، WebSocket خودنوشته RFC 6455 (handshake/accept-key، frame codec با mask اجباری client، fragmentation، ping/pong، Origin check)، brokerها: Array/Database(polling با TTL prune)/Redis(bازاستفاده از RedisClient صف با seq سراسری و fallback خودکار)، ChannelRegistry با {param}/wildcard، احراز کانال خصوصی (POST /broadcasting/auth + توکن HMAC)، Presence با cache TTL، قلاب ShouldBroadcast در EventDispatcher، routes/channels.php.
>
> مستند کامل: [REALTIME](../../docs/03-engines/realtime.md) • تیکت: TVN-0045 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.realtime` |
| namespace | `Tavaan\Engine\Realtime\` |
| پیکربندی | `config/realtime.php` → `config('realtime.*')` |
| Provider | `Tavaan\Engine\Realtime\RealtimeServiceProvider` |
| وابستگی سخت | `config`، `event`، `http` |
| وابستگی نرم | `database`، `cache`، `queue`، `auth` |
| فرمان‌های CLI | `tvn realtime:websocket` |
| آمار | 15 فایل / ~2,148 خط در src • 1 فایل تست (29 متد) |

## شروع سریع

```bash
# تست‌های موتور (29 متد)
php bin/tvn test engines/realtime

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/realtime/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── migrations/      # جداول موتور
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/realtime.md`](../../docs/03-engines/realtime.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
