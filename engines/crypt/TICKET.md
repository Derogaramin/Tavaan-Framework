# تیکت‌های `engines/crypt`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.crypt` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Crypt\` |
| آمار واقعی | 7 فایل / 415 خط در src • 1 فایل تست / 14 متد تست |

---

## TVN-0054 — Crypt (AES-GCM + HKDF)

| فیلد | مقدار |
|---|---|
| فاز | M4 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-054](../../docs/03-engines/crypt.md) |
| پیش‌نیاز | TVN-0003 (Config Repository + ENV Loader) |
| برآورد LOC | ~600 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/crypt` (14 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

