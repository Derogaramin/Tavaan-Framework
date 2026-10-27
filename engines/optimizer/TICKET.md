# تیکت‌های `engines/optimizer`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.optimizer` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Optimizer\` |
| آمار واقعی | 6 فایل / 254 خط در src • 0 فایل تست / 0 متد تست |

---

## TVN-0009 — Optimizer (cacheها)

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P1 |
| وضعیت | ✅ done |
| سند | [DOC-037](../../docs/03-engines/optimizer.md) |
| پیش‌نیاز | TVN-0003 (Config Repository + ENV Loader) |
| برآورد LOC | ~800 |

### معیارهای پذیرش
- AC-OPT-01..03

### شواهد پیاده‌سازی
- تست محلی ندارد — پوشش غیرمستقیم از راه تست‌های سراسری/موتورهای همسایه: `php bin/tvn test`
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

