# کیت انجمن (Forum Kit) 💬

کیت آمادهٔ انجمن گفت‌وجو برای فریمورک تَوان — پیاده‌سازی **DOC-094**
(تیکت **TVN-0094** فاز M9).

## نصب سریع
```bash
php bin/tvn kit:install forum --preset=standard --with-demo
php -S 127.0.0.1:8000 -t public public/index.php
# انجمن:      http://127.0.0.1:8000/forums
# پنل مدیریت: http://127.0.0.1:8000/admin/forum/forums
```

## چه چیزی نصب می‌شود؟
| بخش | محتوا |
|---|---|
| ماژول‌ها | `tavaan.forum` + core-user/content/comment/media |
| جداول | forum_forums / forum_threads / forum_posts / forum_votes / forum_subscriptions / forum_flags / forum_profiles |
| پوسته | `themes/default` → `resources/views/vendor/forum` + `public/assets/kits/forum` (RTL, indigo) |
| نقش‌ها | member / moderator / admin (seed/roles.json) |
| دمو | تالارهای تو‌در‌تو، موضوع Q&A حل‌شده با رأی، گزارش در صف moderation |
| preset | minimal / **standard** / enterprise → overlay `config/forum.php` |

## ویژگی‌های کلیدی
- تالار تو‌در‌تو + موضوع/پست + **رأی ±۱** با toggle و **Reputation/Karma**
- **پاسخ برگزیده (Q&A)** توسط نویسندهٔ موضوع یا moderator
- **نقل‌قول** (prefill فرم + JSON)
- **@username mention** با اعلان (فارسی/لاتین، بدون code-block)
- **صف Moderation با flag** → resolve (hide) / dismiss + **ban کاربر**
- **ضداسپم سه‌لایه**: Throttle + FloodControl + honeypot + SpamFilter
- **جست‌وجوی فارسی** (ایندکس رویدادمحور + fallback با ارقام فارسی/لاتین)
- **Realtime/SSE بدون ext** (AC-FORUM-08): کانال `forum.thread.{id}` +
  بنر زنده در صفحهٔ موضوع

## presetها
| preset | مناسب | تفاوت‌ها |
|---|---|---|
| `minimal` | انجمن کوچک/آزمایش | بدون realtime/mention، flood سخت، جست‌وجو DB |
| `standard` | پیش‌فرض (DOC-094) | همه‌چیز روشن، flood ۳۰s/۱۰ |
| `enterprise` | انجمن پرحجم | auto-hide با ۳ گزارش، کش ۳۰s، سقف لینک ۲ |

## ساختار
```
kits/forum/
├── kit.json               manifest کیت
├── presets/               minimal/standard/enterprise (overlay config/forum.php)
├── themes/default/        theme.json + assets/css + views/layouts (vendor override)
├── seed/roles.json        نقش‌ها و مجوزها
├── seed/demo/             انتصاب نقش به کاربران دمو
├── install/steps.json     گام‌های نصب
└── docs/                  user-guide + admin-guide
```

## تست
ماژول `modules/forum/tests`: ۲۴ Unit + ۱۸ Feature/E2E (سناریوهای DOC-094 §8:
E2E-01..07 + matrix نقش‌ها + ban + honeypot + XSS + mention + quote +
sync-counters + realtime/SSE):

```bash
php bin/tvn test modules/forum/tests
```
