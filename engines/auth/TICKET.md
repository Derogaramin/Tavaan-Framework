# تیکت‌های `engines/auth`

> مستند تیکت‌محور این ساختار — تولیدشده با `php tools/scripts/generate-ticket-docs.php` (منبع: `docs/08-roadmap/agent-backlog.md`). آخرین تولید: 2026-10-26

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.engine.auth` |
| نسخه | 0.1.0 |
| namespace | `Tavaan\Engine\Auth\` |
| آمار واقعی | 25 فایل / 3,052 خط در src • 1 فایل تست / 23 متد تست |

---

## TVN-0056 — Auth (Session/JWT/OTP/2FA)

| فیلد | مقدار |
|---|---|
| فاز | M4 |
| نوع/اولویت | engine |
| وضعیت | ✅ done |
| سند | [DOC-056](../../docs/03-engines/auth.md) |
| پیش‌نیاز | TVN-0022 (Session (۵ درایور))، TVN-0055 (Hash (Argon2id))، TVN-0054 (Crypt (AES-GCM + HKDF))، TVN-0004 (Event Dispatcher + Outbox) |
| برآورد LOC | ~2,000 |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test engines/auth` (23 متد)
- اعتبارسنجی manifest: `php bin/tvn engine:validate`

