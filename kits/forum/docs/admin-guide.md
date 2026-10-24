# راهنمای مدیر کیت انجمن 🛠️

## نصب
```bash
php bin/tvn kit:install forum --preset=standard --with-demo
```
گام‌ها: پیش‌نیازها → فعال‌سازی ماژول‌ها → preset → migrate → نصب پوسته
(vendor override) → `rbac:sync` + نقش‌ها → seed دمو → وضعیت در
`storage/framework/kits.status.php`.

پست‌ها: `minimal` (سبک/سخت‌گیرانه)، `standard` (پیش‌فرض DOC-094)،
`enterprise` (پرحجم + auto-hide با ۳ گزارش).

## نقش‌ها (DOC-094 §4)
| نقش | توانایی |
|---|---|
| `member` | موضوع/پست/رأی/flag/پروفایل |
| `moderator` | + پنهان/حذف پست، قفل/سنجاق/جابجایی موضوع، صف moderation، پاسخ برگزیده |
| `admin` | + مدیریت تالارها، ban کاربر |

انتصاب: `tvn rbac:sync` نقش‌ها را از `seed/roles.json` می‌سازد؛ demo کیت
نقش‌ها را به کاربران نمونه (admin/editor/author/reader @blog.test — رمز
`password`) می‌دهد.

## پنل مدیریت (`/admin/forum/…`)
- **تالارها** `/admin/forum/forums`: ساخت/ویرایش تالار تو‌در‌تو (والد + ترتیب)،
  قفل تالار (موضوع جدید نمی‌پذیرد)، حذف (فقط تالار خالی — گارد data-integrity).
- **موضوعات** `/admin/forum/threads`: فیلتر تالار، قفل/بازکردن، سنجاق،
  جابجایی بین تالارها، تغییر عنوان، حذف کامل (با همهٔ پست/رأی/اشتراک/گزارش).
- **صف Moderation** `/admin/forum/moderation`: گزارش‌های pending با پیش‌نمایش
  پست؛ «اقدام شد (پنهان)» = hide پست + resolve همهٔ گزارش‌های باز همان پست؛
  «رد گزارش» = dismiss؛ **ban نویسنده** (روزانه یا دائم).

## Moderation و ban
- flag → `forum_flags` (pending) + اعلان به نقش‌های moderator/admin.
- `forum.moderation.auto_hide_threshold` (preset سازمانی: ۳) → پنهان خودکار.
- ban روی `forum_profiles` — مسیر نوشتن (موضوع/پست/رأی) را می‌بندد؛
  `banned_until` گذشته → لغو خودکار. unban از همان صفحهٔ صف.

## ضد-اسپم (لایه‌ها)
1. `ThrottleMiddleware` روی روت‌های نوشتن (۱۰/۲۰ درخواست در ۱۰ دقیقه).
2. `FloodControl` (config `forum.flood`): حداقل فاصله + پنجرهٔ لغزان.
3. honeypot (`forum.spam.honeypot_field` — فیلد نامرئی فرم پاسخ).
4. `SpamFilter`: سقف لینک + واژه‌های مسدود (config `forum.spam.blocked_words`).

## Realtime (SSE)
- کانال‌ها: `forum` (سراسری) و `forum.thread.{ULID}`.
- کلاینت: `GET /events/stream?channels=forum.thread.{id}` (endpoint موتور
  realtime — بدون ext؛ `text/event-stream`).
- broker پیش‌فرض `array` (تک‌فرایند). استقرار چندفرایندی:
  `config('realtime.broker') = database|redis` (redis با fallback خودکار).

## جست‌وجو
- ایندکس‌ها: `forum_threads` (عنوان + بدنهٔ پست اول) و `forum_posts`.
- رویدادمحور (thread/post created/updated/hidden/deleted) + fallback LIKE با
  واریانت‌های رقم فارسی/لاتین.
- reindex دستی: `tvn search:reindex` (موتور search).

## نگهداری
```bash
php bin/tvn forum:sync-counters            # بازشماری آمار تالار/موضوع/پروفایل
php bin/tvn forum:sync-counters --dry-run  # فقط گزارش
```

## عیب‌یابی
| علامت | بررسی |
|---|---|
| ۴۰۳ روی نوشتن | نقش کاربر: `member` + rbac:sync اجرا شده؟ |
| «برای ارسال بعدی صبر کنید» | flood control — `forum.flood.min_seconds` |
| نشانگر «آفلاین» در موضوع | endpoint `/events/stream` و کانال‌ها؛ broker |
| جست‌وجو نتیجه ندارد | `tvn search:reindex`؛ `forum.search.enabled` |
| اعلان نمی‌رسد | `forum.notifications.*` + کانال mail/database موتور notification |
