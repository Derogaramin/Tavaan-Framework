# گزارش پیاده‌سازی M9 — گام ۴: کیت انجمن 💬 (TVN-0094)

| متادیتا | مقدار |
|---|---|
| تیکت | TVN-0094 — Kit: Forum |
| مستند مبنا | DOC-094 (`docs/05-applications/forum.md`) + DOC-090 (blueprint کیت) |
| نسخه | 0.12.0 |
| شاخه | `m9-kits` |
| تاریخ | 2026-10-24 |
| نتیجه | ✅ ۴۲ تست جدید (۱۴۶۸ کل / ۹۴۹۱ assertion) • arch:check ۰ نقض (۱۲۸۷ فایل) • security:scan ۰ بحرانی • نصب واقعی کیت + smoke HTTP سبز |

---

## ۱. دامنهٔ تحویل

پیاده‌سازی کامل DOC-094 در ماژول شش‌ضلعی `tavaan.forum` + کیت `tavaan.kit.forum`:

| محدوده (DOC-094) | پیاده‌سازی |
|---|---|
| §1 Aggregateها | `Forum` (تو‌در‌تو + شمارنده + قفل)، `Thread` (ULID، pinned/locked/solved)، `Post` (ULID، vote_score/is_solution/status)، `Flag` (صف moderation)، `ForumProfile` (username یکتا + karma + ban) + ردیف‌های Vote/Subscription |
| §2 مدل داده | ۷ جدول `forum_*` — هم‌راستا با DOC + `forum_flags` (صف §5) و `forum_profiles` (mention/karma/ban §4/§6). انحراف مستند: `forum_forums.last_post_id` به‌جای BIGINT از ULID (char 26) — هم‌نوع با `forum_posts.id` |
| §3 روت‌ها | همهٔ روت‌های DOC + subscribe/unsubscribe، quote JSON، ویرایش پست، پروفایل عضو، پنل `/admin/forum/*` |
| §4 نقش‌ها | member/moderator/admin — ۱۹ مجوز `forum.*` در manifest + `rbac:sync` + ThreadPolicy/PostPolicy/ForumPolicy اعلامی (`config/rbac.php`) |
| §5 رویدادها | ۹ رویداد رشته‌ای با کلاس manifest — مصرف‌کننده: search index، notification (author/subscribers/mentions/moderators)، realtime broadcast، karma |
| §6 ویژگی‌ها | Realtime/SSE • جست‌وجوی فارسی • Reputation/Karma • پاسخ برگزیده • نقل‌قول • صف flag • ضداسپم (throttle+flood+honeypot+spam) • @username mention با اعلان |
| §7 امنیت | Markdown escape-first → `Security::sanitize(rich_text)` دو‌لایه • flood control • honeypot • Policy/Gate • prepared query |

### آمار کد

| جزء | تعداد |
|---|---|
| فایل‌های PHP ماژول `modules/forum` | ۸۸ (src: ۵۹ — ۵ entity، ۲ enum، ۱ VO، ۵ دامنه-servis، ۹ رویداد، ۱ exception، ۷ پورت مخزن، ۷ آداپتر Persistence، ۶ UseCase، indexer، broadcaster، notifier، ModeratorFinder، ۳ Policy، ۷ کنترلر، Provider، command • views: ۱۷ • config: ۲ • migration: ۱ • seed: ۱ • tests: ۴ • lang: ۲) |
| کیت `kits/forum` | ۱۳ فایل (kit.json، ۳ preset، theme.json + css + layout، roles.json، demo، steps.json، ۲ راهنما، README) |
| تست | ۴۲ (۲۴ Unit + ۱۸ Feature) |
| مسیر | ۱۹ وب + ۲۲ ادمین |
| مجوز RBAC | ۱۹ کلید `forum.*` + ۳ نقش |

## ۲. انطباق با سناریوهای E2E (DOC-094 §8) و AC (§9)

| سناریو | تست | نتیجه |
|---|---|---|
| E2E-01 عضو موضوع می‌سازد → دیگران پاسخ می‌دهند | `testE2E01` — HTTP کامل، slug فارسی، پست اول ≠ reply، شمارنده‌ها، اشتراک خودکار، اعلان database | ✅ |
| E2E-02 رأی‌دهی + score | `testE2E02/02b` — upvote→score+1/karma+2، toggle→لغو ردیف رأی، downvote، تغییر رأی، یکتایی، ۴۰۳ رأی به خود | ✅ |
| E2E-03 پاسخ برگزیده | `testE2E03` — ۴۰۳ غریبه، mark توسط نویسندهٔ موضوع، is_solved، karma+15، solution_count، اعلان، unmark توسط moderator | ✅ |
| E2E-04 moderation: flag → hidden | `testE2E04/04b` — گزارش + reason، رد گزارش تکراری، صف با پیش‌نمایش، resolve→hidden، جای‌نما فقط برای moderator، ۴۰۳ عضو در پنل | ✅ |
| E2E-05 جست‌وجوی فارسی | `testE2E05` — عنوان/بدنه + **رقم فارسی ۱۴۰۵→1405** (نرمال‌سازی + واریانت LIKE) | ✅ |
| E2E-06 Realtime: پست جدید به حاضران | `testE2E06` — poll روی broker (کانال `forum.thread.{id}`)، streamer SSE رویداد تازه را تحویل می‌دهد، endpoint `/events/stream` → 200 + `text/event-stream` | ✅ |
| E2E-07 Flood control | `testE2E07` — پاسخ دومِ فوری رد شد (min_interval=30s)، reply_count بدون تغییر | ✅ |
| AC-FORUM-08 SSE بدون ext | `SseStreamer` موتور realtime (StreamedResponse) + کلاینت EventSource در theme — هیچ ext لازم نیست | ✅ |

تست‌های تکمیلی: matrix نقش‌ها (member/moderator/admin — lock/pin/move/hide)،
ban کاربر (انسداد نوشتن + انقضا + unban)، مهمان (خواندن آزاد/نوشتن redirect)،
honeypot (پست ربات ذخیره نمی‌شود)، XSS (`<script>` sanitize)، mention
(@username → اعلان)، نقل‌قول (?quote= prefill)، `forum:sync-counters`.

## ۳. تصمیم‌های طراحی

1. **پست‌های انجمن روی جدول اختصاصی `forum_posts`** (نه پورت comment) —
   رأی/solution/status/شمارنده‌ها موجودیت درجه‌یک‌اند؛ ماژول comment فقط
   dependency کیت است (DOC-094 §meta). پورت `CommentStoreInterface` دست‌نخورده.
2. **`forum_profiles` با username یکتا** — جدول `users` (متعلق به core-user)
   ستون username ندارد و تغییرش نمی‌دادیم؛ هندل انجمن (mention/URL/karma/ban)
   در پروفایل ماژول با fallback `user-{id}`.
3. **karma با delta خالص** — `f(new) − f(old)` در تغییر رأی (بدون drift)،
   `incrementKarma` اتمیک با کف صفر؛ solution → +۱۵ و برگشت‌پذیر.
4. **صف moderation = جدول flag** (DOC-094 §5: «moderation queue») — اعلان
   moderatorها نقش‌محور (config `moderator_roles` → جداول RBAC با try/catch
   best-effort؛ بدون ارجاع به کلاس موتور — ARCH-003).
5. **Realtime روی کانال موضوع** — `forum.thread.{ULID}` (+`forum` سراسری)؛
   کلاینت SSE مستقیم به endpoint موتور (`/events/stream`) — بدون روت/کلاس
   موتور در ماژول. broker پیش‌فرض array؛ production: database/redis.
6. **fallback جست‌وجو با واریانت رقم** — ایندکس native فارسی‌آگاه است اما
   LIKE روی DB نه؛ `digitVariants()` هر دو شکل ۱۴۰۵/1405 را می‌جوید +
   hit کهنه (سند بدون ردیف DB) → fallback (تاب‌آوری در برابر ایندکس مانده).
7. **FloodControl/SpamFilter دامنهٔ خالص** — تصمیم بدون I/O (تست‌پذیر)؛
   throttle روت لایهٔ بیرونی است نه جایگزین.

## ۴. اصلاح موتور (با تست رگرسیون)

- **`NativeDriver::suggest`** (search): termهای رقمی پس از json round-trip
  کلید int می‌شوند → `str_starts_with(): Argument #1 must be of type string`
  در مسیر «آیا منظور شما…» (total=0). هر ایندکسی با توکن عددی (۱۴۰۵، نسخه‌ها،
  قیمت‌ها) مستعد بود — کشف توسط E2E-05 انجمن.
  **Fix**: `(string) $term` در حلقهٔ candidates + تست
  `testSuggestWithNumericTermsDoesNotCrash` (۳۴/۳۴ تست search سبز).

## ۵. نصب واقعی و smoke

```
tvn rbac:sync                                   → ۱۹ مجوز forum.*
tvn kit:install forum --preset=standard --with-demo
  • preset overlay → config/forum.php
  • theme → resources/views/vendor/forum + public/assets/kits/forum
  • نقش‌های جدید: member، moderator
  • demo: تالارهای تو‌در‌تو (۵)، پروفایل/username (۳)، موضوع Q&A حل‌شده
    با رأی، موضوع گفت‌وجو با گزارش pending در صف
HTTP smoke (php -S :8901):
  /forums /forums/support /forums/faq /members/nevisandeh
  /forums/search?q=encoding /threads/moshkel-e-encoding-farsi → 200 + پوستهٔ کیت (indigo)
  badge پاسخ برگزیده + کد json_encode در موضوع ✅ • karma/آمار در پروفایل ✅
  /admin/forum/moderation → 302 /login (گارد احراز هویت) ✅
```

## ۶. دروازه‌های کیفیت (DoD — DOC-113)

| دروازه | نتیجه |
|---|---|
| `tvn test` (کل) | **1468/1468 سبز — ۹۴۹۱ assertion** (پیش از این 1425) |
| `tvn arch:check` | ۰ نقض (۱۲۸۷ فایل) — ARCH-003: فقط SDK مجاز (`Engine\Console\Command`) در src ماژول |
| `tvn security:scan` | ۰ high/critical (۸ med = checksum‌های موجود موتور — صورت‌مسئلهٔ پیشین) |
| `tvn lint:php` | ۱۷۷۲ فایل، ۰ خطا |
| `tvn module:validate` | ۶۰ manifest معتبر |
| `tvn engine:validate` | ۵۲ موتور، ۰ خطا |

## ۷. بدهی/محدودیت‌های ثبت‌شده

- اعلان moderatorها **نقش‌محور** است نه permissionمحور (یافتن کاربرانِ یک
  permission API عمومی ندارد) — نقش‌ها از config قابل تنظیم‌اند.
- ویرایشگر پست textarea ساده است (بدنهٔ HTML هنگام ویرایش به متن تبدیل
  می‌شود) — ویرایشگر rich-text در بهبودهای بعدی کیت.
- حضور آنلاین (presence) استفاده نمی‌شود — فقط کانال عمومی SSE.
- UI ورود/ثبت‌نام همچنان نبود دارد (شکاف مشترک همهٔ کیت‌ها برای Release 1.0)
  — لینک `/login` در پوسته آینده‌نگر است.
- AC-NEWS-07/08-مشابه: عدد p95/Lighthouse سخت‌افزار-وابسته — ساختار
  (کش tag-based ۶۰s + fallback) آماده است.

## ۸. پرونده‌های کلیدی

```
modules/forum/                      — ماژول (module.json، src/، views/، migrations/، seeds/، lang/، tests/، config/، README.md)
kits/forum/                         — کیت (kit.json، presets/، themes/default، seed/، install/، docs/، README.md)
engines/search/src/Drivers/NativeDriver.php   — fix suggest (رقمی)
engines/search/tests/Feature/SearchTest.php   — تست رگرسیون
docs/05-applications/forum.md       — §10 وضعیت + بلوک ماشین‌خوان
docs/08-roadmap/agent-backlog.md    — TVN-0094 done • M9: ۴ مانده/۴ انجام
CHANGELOG.md • tavaan.json • core/bootstrap.php — 0.12.0
```
