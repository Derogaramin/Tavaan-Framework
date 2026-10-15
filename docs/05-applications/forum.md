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
