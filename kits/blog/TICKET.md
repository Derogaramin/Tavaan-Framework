# تیکت‌های `kits/blog`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.kit.blog` |
| نسخه | 1.0.0 |
| ماژول‌ها | `tavaan.core-user`، `tavaan.content`، `tavaan.comment`، `tavaan.media`، `tavaan.blog` |
| نقش‌ها | 4 (reader، author، editor، admin) |

---

## TVN-0091 — Kit: Blog

| فیلد | مقدار |
|---|---|
| فاز | M9 |
| نوع/اولویت | kit |
| وضعیت | ✅ done |
| سند | [DOC-091](../../docs/05-applications/blog.md) |

### شواهد پیاده‌سازی
- نصب: `php bin/tvn kit:install blog --with-demo`
- گزارش پیاده‌سازی: [M9-blog-implementation-report.md](../../docs/reports/M9-blog-implementation-report.md)
- راهنمای کیت: [README.md](README.md)
- ماژول: `modules/core-user` ([تیکت](../../modules/core-user/TICKET.md))
- ماژول: `modules/content` ([تیکت](../../modules/content/TICKET.md))
- ماژول: `modules/comment` ([تیکت](../../modules/comment/TICKET.md))
- ماژول: `modules/media` ([تیکت](../../modules/media/TICKET.md))
- ماژول: `modules/blog` ([تیکت](../../modules/blog/TICKET.md))

