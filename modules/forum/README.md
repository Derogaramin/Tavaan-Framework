# ماژول انجمن (tavaan.forum) 💬

پیاده‌سازی کامل **DOC-094** (کاربرد فروم) — تیکت **TVN-0094** از فاز M9.

| متادیتا | مقدار |
|---|---|
| شناسه | `tavaan.forum` |
| معماری | hexagonal (Domain / Application / Infrastructure / Http) |
| پیش‌نیاز ماژول‌ها | `tavaan.core-user`, `tavaan.content`, `tavaan.comment`, `tavaan.media` |
| پیش‌نیاز موتورها | view, i18n, search, cache, notification, database, rbac, security, media, schedule, realtime |
| نصب (کیت) | `php bin/tvn kit:install forum --with-demo` |

## Aggregateها (DOC-094 §1)

| Aggregate | Root | جدول | نکات |
|---|---|---|---|
| Forum | `Forum` | `forum_forums` | تو‌در‌تو (parent_id) + شمارنده‌ها + قفل |
| Thread | `Thread` | `forum_threads` | ULID • pinned/locked/solved • reply_count/last_post_at |
| Post | `Post` | `forum_posts` | ULID • vote_score • is_solution • visible/hidden/deleted |
| Vote | (ردیف) | `forum_votes` | یکتا (post_id,user_id) • value=±1 |
| Subscription | (ردیف) | `forum_subscriptions` | کلید مرکب (user_id,thread_id) |
| Flag | `Flag` | `forum_flags` | صف moderation: pending→resolved/dismissed |
| ForumProfile | `ForumProfile` | `forum_profiles` | @username یکتا + karma + ban |

## نقش‌ها و مجوزها (DOC-094 §4)

| نقش | توانایی |
|---|---|
| `member` | ساخت موضوع/پست، رأی، flag، پروفایل (ویرایش پست خود) |
| `moderator` | + ویرایش همه، پنهان/حذف پست، قفل/سنجاق/جابجایی موضوع، صف moderation، پاسخ برگزیده |
| `admin` | + مدیریت تالارها، ban کاربر |

مجوزها: `forum.forum.{view,manage}`، `forum.thread.{view,create,update,delete,lock,pin,move}`،
`forum.post.{create,update,delete,hide,vote,solution}`، `forum.flag.{create,manage}`،
`forum.profile.manage`، `forum.user.ban` — در manifest + `tvn rbac:sync`.
Policyها اعلامی از `modules/forum/config/rbac.php → rbac.policies`
(`ThreadPolicy`/`PostPolicy`/`ForumPolicy`).

## رویدادها (DOC-094 §5)

| رویداد | مصرف‌کننده |
|---|---|
| `forum.thread.created` | search index • realtime broadcast • notification (moderatorها) |
| `forum.thread.updated` | search index (lock/pin/move/rename/delete) |
| `forum.post.created` | notification (نویسندهٔ موضوع + دنبال‌کنندگان + mentionها) • realtime • search |
| `forum.post.updated/hidden/deleted` | search index (به‌روزرسانی/حذف سند) |
| `forum.post.voted` | vote_score + **Reputation/Karma** (delta = f(new) − f(old)) |
| `forum.post.flagged` | صف moderation + notification (moderatorها) |
| `forum.post.solved` | karma نویسندهٔ پاسخ + notification + search |

## ویژگی‌ها (DOC-094 §6)

- **Realtime/SSE بدون ext** (AC-FORUM-08): پست جدید روی کانال‌های `forum` و
  `forum.thread.{ULID}` broadcast می‌شود؛ صفحهٔ موضوع با `EventSource` به
  `GET /events/stream?channels=forum.thread.{id}` (endpoint موتور realtime)
  وصل می‌شود و بنر «پاسخ جدید رسید» نشان می‌دهد.
- **جست‌وجوی فارسی**: ایندکس رویدادمحور (`forum_threads`/`forum_posts` —
  موتور search) + fallback LIKE با **واریانت‌های رقم فارسی/لاتین** و
  نرمال‌سازی ی/ک (۱۴۰۵ ↔ 1405).
- **Reputation/Karma**: `ReputationCalculator` — upvote دریافتی +۲، downvote −۱،
  پاسخ برگزیده +۱۵، موضوع/پست جدید +۱ (کف صفر) — روی `forum_profiles.karma`.
- **پاسخ برگزیده (Q&A)**: نویسندهٔ موضوع یا moderator؛ هر موضوع حداکثر یک
  solution (برگزیدهٔ قبلی خودکار برداشته می‌شود) + `thread.is_solved`.
- **نقل‌قول**: `QuoteBuilder` — `GET /threads/{slug}?quote={postId}` (prefill
  فرم) و `GET /posts/{id}/quote` (JSON برای JS).
- **صف Moderation با flag**: گزارش → `forum_flags` (pending) → moderator
  resolve (پنهان‌کردن پست) یا dismiss؛ `auto_hide_threshold` اختیاری.
- **Anti-spam سه‌لایه** (DOC-094 §7): `ThrottleMiddleware` روی روت‌ها +
  `FloodControl` (حداقل فاصله ۳۰s و سقف ۱۰ پست/۵ دقیقه — دامنهٔ خالص) +
  honeypot (فیلد نامرئی `website`) + `SpamFilter` (لینک زیاد/واژهٔ مسدود).
- **@username mention**: `MentionExtractor` (فارسی/لاتین، بدون code-block،
  سقف ۱۰) → اعلان mail+database به کاربران ذکرشده.
- **ban کاربر**: `forum_profiles.is_banned/banned_until` — انقضای خودکار؛
  مسیر نوشتن (موضوع/پست/رأی) کاربران ban‌شده را مسدود می‌کند.

## امنیت (DOC-094 §7)

| تهدید | کنترل |
|---|---|
| XSS در پست | Markdown escape-first → `Security::sanitize(profile=rich_text)` دو لایه |
| Spam | throttle + honeypot + SpamFilter (لینک/واژه) |
| Flood | FloodControl (min_interval + پنجرهٔ لغزان) |
| Privilege | RBAC Policy (Thread/Post/Forum) + Gate — 403 روی نشتی نقش |
| Injection | پرس‌وجوی prepared (QueryBuilder) بدون ORM |

## روت‌ها (DOC-094 §3)

```
GET  /forums                          فهرست تالارها (درخت + آمار + آخرین‌ها)
GET  /forums/search?q=                جست‌وجوی فارسی
GET  /forums/{slug}                   موضوعات تالار (با زیرتالارها)
GET  /forums/{slug}/new               فرم موضوع جدید (auth)
POST /threads                         موضوع جدید (auth + policy)
GET  /threads/{slug}                  موضوع + پست‌ها (?quote=&page=)
POST /threads/{id}/posts              پاسخ (auth + flood + honeypot)
POST /threads/{id}/subscribe|unsubscribe
POST /posts/{id}/vote                 رأی ±۱ با toggle (JSON با Accept)
POST /posts/{id}/mark-solution        پاسخ برگزیده (نویسندهٔ موضوع/moderator)
POST /posts/{id}/flag                 گزارش → صف moderation
GET  /posts/{id}/quote                نقل‌قول JSON
GET  /members/{username}              پروفایل عمومی (+karma)
GET|POST /members/{username}/edit|…   ویرایش پروفایل خود
GET  /admin/forum/forums|threads|moderation  پنل مدیریت (+ban/lock/pin/move/hide)
```

## نگهداری

- `tvn forum:sync-counters [--dry-run]` — بازشماری شمارنده‌های تالار/موضوع/
  پروفایل (پس از import یا ناهمگامی).

## تست‌ها

- **Unit** (۲۴): FloodControl، SpamFilter، MentionExtractor، QuoteBuilder،
  ReputationCalculator، Username VO، موجودیت Post (hide/delete/solution/roundtrip).
- **Feature/E2E** (۱۸): سناریوهای DOC-094 §8 — E2E-01..07 + matrix نقش‌ها +
  ban + honeypot + XSS + mention + quote + sync-counters + realtime/SSE.

## بدهی/محدودیت

- اعلان «moderatorها» نقش‌محور است (config `forum.notifications.moderator_roles`
  → جداول RBAC) — نه permissionمحور.
- ویرایش پست بدنهٔ HTML را به متن ساده برمی‌گرداند (textarea) — ویرایشگر rich
  در کیت‌های بعدی.
- presence (چه کسی آنلاین است) استفاده نمی‌شود — فقط channel عمومی SSE.
