# تیکت‌های `engines/config`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.config` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Config\` |
| آمار واقعی | 13 فایل / 1,081 خط در src • 3 فایل تست / 20 متد تست |

---

## TVN-0003 — Config Repository + ENV Loader

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-032](../../docs/03-engines/config.md) |
| پیش‌نیاز | TVN-0002 (Container با Auto-Wiring) |
| برآورد LOC | ~600 |

### معیارهای پذیرش
- AC-CFG-01..06

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/config` (20 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

### راهنمای پیاده‌سازی (agent_hints)
> بدون putenv! Dot-path get/set. Merge: list جایگزین، map ادغام. Schema validation.

