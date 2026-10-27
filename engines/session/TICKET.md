# تیکت‌های `engines/session`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.session` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Session\` |
| آمار واقعی | 12 فایل / 1,141 خط در src • 2 فایل تست / 21 متد تست |

---

## TVN-0022 — Session (۵ درایور)

| فیلد | مقدار |
|---|---|
| فاز | M2 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-041](../../docs/03-engines/session.md) |
| پیش‌نیاز | TVN-0020 (HTTP Request/Response/Kernel)، TVN-0054 (Crypt (AES-GCM + HKDF)) |
| برآورد LOC | ~900 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/session` (21 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

