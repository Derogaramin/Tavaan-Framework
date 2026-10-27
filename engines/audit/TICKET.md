# تیکت‌های `engines/audit`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.audit` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Audit\` |
| آمار واقعی | 10 فایل / 809 خط در src • 1 فایل تست / 9 متد تست |

---

## TVN-0058 — Audit (hash chain)

| فیلد | مقدار |
|---|---|
| فاز | M4 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-058](../../docs/03-engines/audit.md) |
| پیش‌نیاز | TVN-0004 (Event Dispatcher + Outbox)، TVN-0030 (Database + QueryBuilder + Grammar)، TVN-0007 (Log Engine (چندکاناله + Mask))، TVN-0054 (Crypt (AES-GCM + HKDF)) |
| برآورد LOC | ~700 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/audit` (9 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

