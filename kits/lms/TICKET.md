# تیکت‌های `kits/lms`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.kit.lms` |
| نسخه | 1.0.0 |
| ماژول‌ها | `tavaan.core-user`، `tavaan.content`، `tavaan.comment`، `tavaan.media`، `tavaan.lms`، `tavaan.blog` |
| نقش‌ها | 5 (student، instructor، assistant، lms-admin، admin) |

---

## TVN-0095 — Kit: LMS

| فیلد | مقدار |
|---|---|
| فاز | M9 |
| نوع/اولویت | kit |
| وضعیت | ✅ done |
| سند | [DOC-095](../../docs/05-applications/lms.md) |

### شواهد پیاده‌سازی
- نصب: `php bin/tvn kit:install lms --with-demo`
- گزارش پیاده‌سازی: [M9-lms-implementation-report.md](../../docs/reports/M9-lms-implementation-report.md)
- راهنمای کیت: [README.md](README.md)
- ماژول: `modules/lms` ([تیکت](../../modules/lms/TICKET.md))

