# تیکت‌های `modules/poll`

> ماژول **جدید** (فراتر از backlog اولیه) — افزوده‌شده در تکمیل ماژول‌های M9 طبق
> درخواست «افزودن ماژول‌های جدید». مستند مرجع: DOC-113 (DoD ماژول) + README.md.

| مانیفست | مقدار |
|---|---|
| شناسه | `tavaan.poll` |
| نسخه | 0.1.0 |
| namespace | `Modules\Poll\` |
| وضعیت | ✅ implemented (تست‌ها: `php bin/tvn test modules/poll`) |

---

## TVN-NEW-POLL — ماژول نظرسنجی

| فیلد | مقدار |
|---|---|
| نوع/اولویت | module (جدید) |
| وضعیت | ✅ done |
| سند | [README.md](README.md) |

### شواهد پیاده‌سازی
- تست‌ها: `php bin/tvn test modules/poll` (Unit: PollDomainTest / PollWriteServiceTest / VoteServiceTest • Feature: PollHttpTest)
- اعتبارسنجی manifest: `php bin/tvn module:validate`
- معماری و راهنما: [README.md](README.md)
