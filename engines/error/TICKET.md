# تیکت‌های `engines/error`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.error` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Error\` |
| آمار واقعی | 10 فایل / 1,101 خط در src • 2 فایل تست / 19 متد تست |

---

## TVN-0006 — Error/Exception Handler + LogMasker

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-035](../../docs/03-engines/error.md) • [DOC-013](../../docs/01-standards/error-handling.md) |
| پیش‌نیاز | TVN-0007 (Log Engine (چندکاناله + Mask)) |
| برآورد LOC | ~800 |

### معیارهای پذیرش
- AC-ERR-01..11

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/error` (19 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

### راهنمای پیاده‌سازی (agent_hints)
> LogMasker با pattern. RefCode. Deprecated→failure در CI. Renderers (Json/Html/Cli/Debug).

