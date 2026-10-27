# موتور لایهٔ API — `API`

> M6 — JsonResource با when/whenLoaded/whenHas/whenCounted/mergeWhen/whenPivotLoaded، AnonymousResourceCollection با meta/links صفحه‌بندی، Versioning (url/header/query + هدرهای Deprecation/Sunset)، Idempotency-Key با قفل in-progress و RFC 9457، Throttle (نام‌دار و عددی، per-user/key/IP، هدرهای X-RateLimit-*)، OpenAPI 3.1 generator خودنوشته (attribute-based + FormRequest rules + YAML emitter داخلی).
>
> مستند کامل: [API](../../docs/03-engines/api.md) • تیکت: TVN-0044 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.api` |
| namespace | `Tavaan\Engine\Api\` |
| پیکربندی | `config/api.php` → `config('api.*')` |
| Provider | `Tavaan\Engine\Api\ApiServiceProvider` |
| وابستگی سخت | `config`، `http` |
| وابستگی نرم | `validation`، `orm`، `cache`، `router` |
| فرمان‌های CLI | `tvn api:docs` |
| آمار | 17 فایل / ~2,107 خط در src • 1 فایل تست (40 متد) |

## شروع سریع

```bash
# تست‌های موتور (40 متد)
php bin/tvn test engines/api

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/api/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/api.md`](../../docs/03-engines/api.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
