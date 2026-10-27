# تیکت‌های `kits/forum`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.kit.forum` |
| نسخه | 1.0.0 |
| ماژول‌ها | `tavaan.core-user`، `tavaan.content`، `tavaan.comment`، `tavaan.media`، `tavaan.forum` |
| نقش‌ها | 3 (member، moderator، admin) |

---

## TVN-0094 — Kit: Forum

| فیلد | مقدار |
|---|---|
| فاز | M9 |
| نوع/اولویت | kit |
| وضعیت | ✅ done |
| سند | [DOC-094](../../docs/05-applications/forum.md) |

### شواهد پیاده‌سازی
- نصب: `php bin/tvn kit:install forum --with-demo`
- گزارش پیاده‌سازی: [M9-forum-implementation-report.md](../../docs/reports/M9-forum-implementation-report.md)
- راهنمای کیت: [README.md](README.md)
- ماژول: `modules/forum` ([تیکت](../../modules/forum/TICKET.md))

