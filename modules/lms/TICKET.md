# تیکت‌های `modules/lms`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.lms` |
| نسخه | 0.1.0 |
| namespace | `Modules\Lms\` |
| آمار واقعی | 69 فایل / 9,880 خط در src • 2 فایل تست / 40 متد تست |

---

## TVN-0095 — Kit: LMS

| فیلد | مقدار |
|---|---|
| فاز | M9 |
| نوع/اولویت | kit (ماژول) |
| وضعیت | ✅ done |
| سند | [DOC-095](../../docs/05-applications/lms.md) |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test modules/lms` (40 متد)
- اعتبارسنجی manifest: `php bin/tvn module:validate`
- معماری و راهنما: [README.md](README.md)

