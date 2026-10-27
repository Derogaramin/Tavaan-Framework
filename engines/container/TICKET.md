# تیکت‌های `engines/container`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.container` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Container\` |
| آمار واقعی | 14 فایل / 1,608 خط در src • 1 فایل تست / 27 متد تست |

---

## TVN-0002 — Container با Auto-Wiring

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-031](../../docs/03-engines/container.md) • [DOC-026](../../docs/02-architecture/container-di.md) |
| پیش‌نیاز | TVN-0001 (Autoloader خودنوشته با ClassMap) |
| برآورد LOC | ~1,200 |

### معیارهای پذیرش
- AC-CON-01..06

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/container` (27 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

### راهنمای پیاده‌سازی (agent_hints)
> ReflectionCache حیاتی است. Union/DNF resolve با قطعیت. CycleDetector با مسیر کامل.

