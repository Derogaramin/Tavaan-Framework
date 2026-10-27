# تیکت‌های `engines/pipeline`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.pipeline` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Pipeline\` |
| آمار واقعی | 5 فایل / 294 خط در src • 1 فایل تست / 12 متد تست |

---

## TVN-0005 — Pipeline

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-034](../../docs/03-engines/pipeline.md) |
| پیش‌نیاز | TVN-0002 (Container با Auto-Wiring) |
| برآورد LOC | ~250 |

### معیارهای پذیرش
- AC-PIP-01..04

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/pipeline` (12 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

