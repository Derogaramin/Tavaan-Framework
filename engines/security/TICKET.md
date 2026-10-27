# تیکت‌های `engines/security`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.security` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Security\` |
| آمار واقعی | 11 فایل / 1,436 خط در src • 1 فایل تست / 18 متد تست |

---

## TVN-0059 — Security (CSRF/RateLimit/SSRF/Sanitizer)

| فیلد | مقدار |
|---|---|
| فاز | M4 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-059](../../docs/03-engines/security.md) |
| پیش‌نیاز | TVN-0020 (HTTP Request/Response/Kernel)، TVN-0054 (Crypt (AES-GCM + HKDF))، TVN-0060 (Cache (۶ درایور)) |
| برآورد LOC | ~1,500 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/security` (18 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

