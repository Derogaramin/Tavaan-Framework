# DOC-094 — کاربرد فروم (Forum / انجمن) 💬

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-094 |
| سطح | متوسط |
| معماری | `modular` |
| ماژول‌ها | core-user, forum, comment, notification, media |
| موتورها | search, realtime, rbac, queue |

---

## ۱. دامنه
انجمن گفت‌وجو با تالار (Forum)، موضوع (Thread)، پست (Post)، رأی، نشان‌گذاری،
و Moderation.

### ۱٫۱ Aggregateها
| Aggregate | Root | توضیح |
|---|---|---|
| Forum | `Forum` | تالار/دستهٔ موضوعات |
| Thread | `Thread` | یک موضوع گفت‌وجو |
| Post | `Post` | پاسخ درون موضوع |
| Vote | `Vote` | رأی به پست |

## ۲. مدل داده
```sql
CREATE TABLE forum_forums (
  id BIGINT UNSIGNED PK AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,         -- تالار تو‌در‌تو
  title VARCHAR(190), slug VARCHAR(200) UNIQUE,
  description TEXT, position INT DEFAULT 0,
  is_locked TINYINT(1) DEFAULT 0,
  thread_count INT DEFAULT 0, post_count INT DEFAULT 0,
  last_post_id BIGINT UNSIGNED NULL,
  KEY idx_parent (parent_id)
);

CREATE TABLE forum_threads (
  id CHAR(26) PRIMARY KEY, forum_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255), slug VARCHAR(280),
  is_pinned TINYINT(1) DEFAULT 0, is_locked TINYINT(1) DEFAULT 0,
  view_count INT DEFAULT 0, reply_count INT DEFAULT 0,
  last_post_at DATETIME(3), created_at DATETIME(3), updated_at DATETIME(3),
  KEY idx_forum (forum_id, is_pinned DESC, last_post_at DESC)
);

CREATE TABLE forum_posts (
  id CHAR(26) PRIMARY KEY, thread_id CHAR(26) NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL, parent_id CHAR(26) NULL,
  body LONGTEXT, vote_score INT DEFAULT 0,
  is_solution TINYINT(1) DEFAULT 0,        -- پاسخ برگزیده
  status VARCHAR(20) DEFAULT 'visible',    -- visible|hidden|deleted
  created_at DATETIME(3), updated_at DATETIME(3),
  KEY idx_thread (thread_id, created_at)
);

CREATE TABLE forum_votes (
  id BIGINT UNSIGNED PK, post_id CHAR(26), user_id BIGINT UNSIGNED,
  value TINYINT NOT NULL,                  -- +1 / -1
  UNIQUE KEY uk_vote (post_id, user_id)
);

CREATE TABLE forum_subscriptions (
  user_id BIGINT UNSIGNED, thread_id CHAR(26),
  PRIMARY KEY(user_id, thread_id)
);
```

## ۳. روت‌ها
```
GET  /forums                          فهرست تالارها
GET  /forums/{slug}                   موضوعات یک تالار
GET  /threads/{slug}                  موضوع + پست‌ها
POST /threads                         موضوع جدید
POST /threads/{id}/posts              پاسخ
POST /posts/{id}/vote                 رأی
POST /posts/{id}/mark-solution        پاسخ برگزیده (توسط نویسندهٔ موضوع)
GET  /forums/search?q=                جست‌وجو
```

## ۴. نقش‌ها و Moderation
| نقش | مجوز |
|---|---|
| `member` | نوشتن موضوع/پست، رأی |
| `moderator` | + ویرایش/حذف/پنهان، قفل موضوع، جابجایی |
| `admin` | مدیریت تالارها، ban کاربر |

## ۵. رویدادها
| رویداد | مصرف‌کننده |
|---|---|
| `forum.thread.created` | search index, notification (moderator) |
| `forum.post.created` | notification (دنبال‌کنندگان), realtime broadcast |
| `forum.post.voted` | update vote_score, reputation |
| `forum.post.flagged` | moderation queue |

## ۶. ویژگی‌ها
- Realtime (پست جدید با SSE/WebSocket) — موتور realtime
- جست‌وجوی فارسی — موتور search
- Reputation/Karma سیستم
- پاسخ برگزیده (Q&A style)
- نقل‌قول (quote)
- Moderation queue با flag
- Anti-spam: rate limit + honeypot + flood control
- ذکر کاربر (@username) با notification

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| XSS در پست | Sanitizer (rich_text) |
| Spam | rate limit + honeypot + flood |
| Flood | حداقل فاصلهٔ بین پست‌ها |
| Privilege (moderation) | rbac policy |

## ۸. تست E2E
| ID | سناریو |
|---|---|
| E2E-01 | عضو موضوع می‌سازد → دیگران پاسخ می‌دهند |
| E2E-02 | رأی‌دهی + محاسبهٔ score |
| E2E-03 | پاسخ برگزیده |
| E2E-04 | moderation: flag → hidden |
| E2E-05 | جست‌وجوی فارسی |
| E2E-06 | Realtime: پست جدید به حاضران |
| E2E-07 | Flood control |

## ۹. معیار پذیرش 🧪
- AC-FORUM-01..07.
- AC-FORUM-08: Realtime بدون ext (SSE) کار می‌کند.

---

## ۱۰. وضعیت پیاده‌سازی (M9 — TVN-0094) ✅

| بخش | محل |
|---|---|
| ماژول دامنه | `modules/forum/` (tavaan.forum — hexagonal) |
| کیت | `kits/forum/` (kit.json + ۳ preset + theme indigo + roles.json + demo + docs) |
| نصب | `php bin/tvn kit:install forum --with-demo` |
| جداول | `forum_forums` (parent_id) / `forum_threads` / `forum_posts` / `forum_votes` (uk post+user) / `forum_subscriptions` / `forum_flags` / `forum_profiles` (username یکتا + karma + ban) |
| Aggregateها | Forum / Thread / Post / Flag / ForumProfile (+ ردیف‌های Vote/Subscription) — ULID برای Thread/Post |
| نقش‌ها | member / moderator / admin (kits/forum/seed/roles.json + ۱۹ مجوز manifest + ThreadPolicy/PostPolicy/ForumPolicy اعلامی) |
| ضداسپم | ThrottleMiddleware (روت) + FloodControl (min_interval+پنجرهٔ لغزان) + honeypot + SpamFilter (لینک/واژه) |
| Reputation | ReputationCalculator (upvote ۲+ / downvote ۱− / solution ۱۵+ / فعالیت ۱+، کف صفر) روی forum_profiles.karma |
| Realtime | ForumBroadcaster → کانال‌های `forum` و `forum.thread.{ULID}` + کلاینت SSE در صفحهٔ موضوع (`/events/stream` موتور — AC-FORUM-08) |
| جست‌وجو | ایندکس `forum_threads`/`forum_posts` (رویدادمحور) + fallback LIKE با واریانت رقم فارسی/لاتین |
| Moderation | flag → forum_flags (pending) → پنل `/admin/forum/moderation` → resolve(hide)/dismiss + auto_hide_threshold + ban کاربر (انقضای خودکار) |
| Mention | MentionExtractor (فارسی/لاتین، بدون code-block، سقف) → اعلان mail+database |
| نگهداری | `tvn forum:sync-counters [--dry-run]` |
| تست | `modules/forum/tests/` — ۴۲ تست (۲۴ Unit + ۱۸ Feature: E2E-01..07 + matrix نقش‌ها/ban/honeypot/XSS/mention/quote/SSE) |

انطباق: E2E-01 (موضوع+پاسخ+اشتراک+اعلان) ✅ • E2E-02 (رأی/toggle/score/karma) ✅ •
E2E-03 (پاسخ برگزیده: نویسندهٔ موضوع/moderator + ۴۰۳ غریبه) ✅ • E2E-04 (flag →
صف → resolve → hidden + جای‌نما برای moderator) ✅ • E2E-05 (جست‌وجوی فارسی +
رقم فارسی ۱۴۰۵↔1405) ✅ • E2E-06 (broadcast روی کانال موضوع + SSE endpoint
بدون ext) ✅ • E2E-07 (flood control — پاسخ سریع دوم رد شد) ✅ • AC-FORUM-08
(SSE موتور realtime بدون ext — StreamedResponse + EventSource کلاینت) ✅

```yaml
# machine-readable: tavaan.kit.forum.v1 (وضعیت M9)
kit:
  id: tavaan.kit.forum
  status: implemented
  ticket: TVN-0094
  modules: [tavaan.core-user, tavaan.content, tavaan.comment, tavaan.media, tavaan.forum]
  routes_public: [/forums, /forums/search, /forums/{slug}, /forums/{slug}/new, /threads, /threads/{slug}, /threads/{id}/posts, /threads/{id}/subscribe, /threads/{id}/unsubscribe, /posts/{id}/vote, /posts/{id}/mark-solution, /posts/{id}/flag, /posts/{id}/quote, /posts/{id}/edit, /members/{username}]
  routes_admin: [/admin/forum/forums, /admin/forum/threads, /admin/forum/moderation, /admin/forum/posts/{id}/hide, /admin/forum/users/{id}/ban]
  events: [forum.thread.created, forum.thread.updated, forum.post.created, forum.post.updated, forum.post.hidden, forum.post.deleted, forum.post.voted, forum.post.flagged, forum.post.solved]
  channels: [forum, forum.thread.{threadId}]
  tests: {unit: modules/forum/tests/Unit, feature: modules/forum/tests/Feature}
```
