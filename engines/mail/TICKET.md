# تیکت‌های `engines/mail`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.mail` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Mail\` |
| آمار واقعی | 18 فایل / 2,266 خط در src • 1 فایل تست / 17 متد تست |

---

## TVN-0065 — Mail (SMTP خودنوشته)

| فیلد | مقدار |
|---|---|
| فاز | M5 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-065](../../docs/03-engines/mail.md) |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/mail` (17 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

