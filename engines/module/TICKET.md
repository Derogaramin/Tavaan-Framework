# تیکت‌های `engines/module`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.module` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Module\` |
| آمار واقعی | 14 فایل / 1,695 خط در src • 2 فایل تست / 21 متد تست |

---

## TVN-0010 — Module Registry + Topological Sort

| فیلد | مقدار |
|---|---|
| فاز | M1 |
| نوع/اولویت | engine / P0 |
| وضعیت | ✅ done |
| سند | [DOC-038](../../docs/03-engines/module.md) • [DOC-022](../../docs/02-architecture/modular-monolith.md) |
| پیش‌نیاز | TVN-0002 (Container با Auto-Wiring)، TVN-0003 (Config Repository + ENV Loader) |
| برآورد LOC | ~900 |

### معیارهای پذیرش
- AC-MOD-01..08

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/module` (21 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

