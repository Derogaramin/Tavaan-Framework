# موتور رصدپذیری — `Observability`

> M6 — Metrics (Counter/Gauge/Histogram/Summary + Prometheus text format + پایستگی cache)، Tracing خودنوشته (W3C TraceContext، Span/Tracer با Sampling قطعی، exporterها: Array/Log/OTLP-HTTP/Jaeger-UDP با Thrift Compact خودنوشته)، Health Checks (live/ready)، Debug Bar فقط-dev با خروجی escape شده.
>
> مستند کامل: [OBSERVABILITY](../../docs/03-engines/observability.md) • تیکت: TVN-0067 ([TICKET.md](TICKET.md))

| کلید | مقدار |
|---|---|
| شناسه | `tavaan.engine.observability` |
| namespace | `Tavaan\Engine\Observability\` |
| پیکربندی | `config/observability.php` → `config('observability.*')` |
| Provider | `Tavaan\Engine\Observability\ObservabilityServiceProvider` |
| وابستگی سخت | `config`، `log` |
| وابستگی نرم | `event`، `http`، `cache`، `database`، `queue` |
| آمار | 27 فایل / ~3,403 خط در src • 1 فایل تست (38 متد) |

## شروع سریع

```bash
# تست‌های موتور (38 متد)
php bin/tvn test engines/observability

# اعتبارسنجی manifest
php bin/tvn engine:validate
```

## ساختار

```
engines/observability/
├── module.json      # مانیفست (id/namespace/dependencies/tables/events)
├── config/          # پیش‌فرض‌های پیکربندی
├── src/             # پیاده‌سازی
├── tests/           # تست‌های Unit/Feature
├── TICKET.md        # مستند تیکت (TVN-…)
└── README.md
```

## منابع

- مستند کامل موتور: [`docs/03-engines/observability.md`](../../docs/03-engines/observability.md)
- قوانین کد: DOC-010/DOC-011 • مرزها: `config/architecture.php` (arch:check)
- تعریف انجام‌شده: `docs/07-quality/definition-of-done.md`
