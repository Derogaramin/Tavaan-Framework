# تیکت‌های `engines/rbac`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.rbac` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Rbac\` |
| آمار واقعی | 12 فایل / 1,514 خط در src • 1 فایل تست / 12 متد تست |

---

## TVN-0057 — RBAC (Gate/Policy)

| فیلد | مقدار |
|---|---|
| فاز | M4 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-057](../../docs/03-engines/rbac.md) |
| پیش‌نیاز | TVN-0056 (Auth (Session/JWT/OTP/2FA))، TVN-0030 (Database + QueryBuilder + Grammar)، TVN-0060 (Cache (۶ درایور)) |
| برآورد LOC | ~1,600 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/rbac` (12 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

