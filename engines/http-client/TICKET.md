# تیکت‌های `engines/http-client`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.http-client` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\HttpClient\` |
| آمار واقعی | 19 فایل / 2,448 خط در src • 1 فایل تست / 22 متد تست |

---

## TVN-0064 — HttpClient + SSRF

| فیلد | مقدار |
|---|---|
| فاز | M5 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-064](../../docs/03-engines/http-client.md) |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/http-client` (22 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

