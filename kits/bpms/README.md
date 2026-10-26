# کیت BPMS — `tavaan.kit.bpms` 🔄

> طراحی، اجرا و پایش فرایندهای کسب‌وکار — DOC-098 / TVN-0098 (آخرین گام M9).

## نصب

```bash
php bin/tvn kit:install bpms --with-demo
```

گام‌ها: پیش‌نیازها → فعال‌سازی ماژول‌ها (core-user + bpms) → preset
(standard پیش‌فرض) → migrate (۴ جدول `bpms_*`) → پوسته → RBAC (۱۶ مجوز،
۷ نقش) → seed دمو (تعاریف «درخواست مرخصی» و «تأیید خرید موازی» + ۳ نمونه).

## Presetها

| Preset | ویژگی‌ها |
|---|---|
| `minimal` | بدون mirror/realtime — اعلان‌های ضروری، تایمر فعال |
| `standard` | پیش‌فرض DOC-098: mirror موتور workflow + SSE + اعلان کامل + escalation هر دقیقه |
| `enterprise` | سقف نمونه کمتر، escalate تهاجمی، mask گستردهٔ متغیرها، batch بزرگ تایمر |

## نقش‌ها (DOC-098 §7)

`process-designer` (طراحی فرایند/فرم) • `process-admin` (مدیریت نمونه‌ها،
terminate) • `task-assignee` (انجام کارهای assigned) • `monitor` (داشبورد
پایش — خواندن) • `auditor` (تاریخچه + audit) • `manager` (تأییدکنندهٔ
کسب‌وکاری سناریوهای دمو) • `admin`

## کاربران دمو

| کاربر | نقش BPMS | رمز |
|---|---|---|
| `admin@blog.test` | process-admin | `password` |
| `editor@blog.test` | process-designer + manager | `password` |
| `author@blog.test` | task-assignee + finance | `password` |
| `reader@blog.test` | auditor + monitor | `password` |

## نقاط ورود

- پنل پایش: `/admin/bpms` (KPI/گلوگاه/SLA/بار کاری + CSV)
- فرایندساز: `/admin/bpms/definitions` (JSON designer + نمودار SVG + نسخه‌بندی)
- نمونه‌ها: `/admin/bpms/instances` (نمودار با توکن فعال، timeline، terminate/suspend)
- ردپای audit: `/admin/bpms/audit` + `php bin/tvn audit:verify`
- وب کاربران: `/bpms/tasks` (inbox) • `/bpms/processes` (آغاز) • `/bpms/instances` (نمونه‌های من)
- تایمرها: `php bin/tvn bpms:check-timers` (schedule هر دقیقه)
- Realtime: `GET /events/stream?channels=bpms`

## مستندات

- `docs/admin-guide.md` — راهنمای مدیر (طراحی فرایند/فرم، اکشن‌ها، تایمر، نقش‌ها)
- `docs/user-guide.md` — راهنمای کاربر (inbox/claim/complete/delegate/آغاز)
- `modules/bpms/README.md` — معماری ماژول
