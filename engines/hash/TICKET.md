# تیکت‌های `engines/hash`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.hash` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Hash\` |
| آمار واقعی | 4 فایل / 233 خط در src • 1 فایل تست / 10 متد تست |

---

## TVN-0055 — Hash (Argon2id)

| فیلد | مقدار |
|---|---|
| فاز | M4 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-055](../../docs/03-engines/hash.md) |
| پیش‌نیاز | TVN-0003 (Config Repository + ENV Loader) |
| برآورد LOC | ~350 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/hash` (10 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

