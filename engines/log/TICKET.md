# تیکت‌های `engines/log`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.log` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Log\` |
| آمار واقعی | 20 فایل / 1,426 خط در src • 1 فایل تست / 11 متد تست |

---

## TVN-0007 — Log Engine (چندکاناله + Mask)

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-036](../../docs/03-engines/log.md) |
| پیش‌نیاز | TVN-0003 (Config Repository + ENV Loader) |
| برآورد LOC | ~900 |

### معیارهای پذیرش
- AC-LOG-01..05

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/log` (11 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

