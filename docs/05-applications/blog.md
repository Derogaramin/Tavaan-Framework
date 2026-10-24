# DOC-091 — کاربرد وبلاگ (Blog) ✍️

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-091 |
| سطح | پایه |
| معماری | `hmvc` (قابل شروع با `mvc`) |
| ماژول‌ها | core-user, content, comment, media |
| موتورها | view, i18n, search, media, notification |

---

## ۱. دامنه
وبلاگ شخصی/سازمانی با مقاله، دسته، برچسب، نظر، جست‌وجو، آرشیو و RSS.

### ۱٫۱ Aggregateها
| Aggregate | Root | رویداد |
|---|---|---|
| Post | `Post` | PostWasPublished/Updated |
| Category | `Category` | — |
| Tag | `Tag` | — |
| Comment | `Comment` | CommentWasSubmitted/Approved |

## ۲. مدل داده
```sql
CREATE TABLE blog_posts (
  id CHAR(26) PRIMARY KEY,
  slug VARCHAR(200) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  excerpt TEXT, body LONGTEXT,
  author_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  status VARCHAR(20) DEFAULT 'draft',     -- draft|published|scheduled|archived
  featured_image CHAR(26) NULL,           -- → tvn_media
  view_count INT DEFAULT 0,
  published_at DATETIME(3) NULL,
  seo_title VARCHAR(255), seo_description TEXT, seo_keywords TEXT,
  created_at DATETIME(3) NOT NULL, updated_at DATETIME(3), deleted_at DATETIME(3),
  KEY idx_status_published (status, published_at),
  FULLTEXT KEY ft_post (title, excerpt, body)
) ENGINE=InnoDB;

CREATE TABLE blog_tags (id BIGINT UNSIGNED PK, name VARCHAR(100), slug VARCHAR(120) UNIQUE);
CREATE TABLE blog_post_tag (post_id CHAR(26), tag_id BIGINT UNSIGNED, PRIMARY KEY(post_id, tag_id));
CREATE TABLE blog_comments (
  id CHAR(26) PRIMARY KEY, commentable_type VARCHAR(100), commentable_id CHAR(26),
  author_name VARCHAR(100), author_email VARCHAR(190), body TEXT,
  parent_id CHAR(26) NULL, status VARCHAR(20) DEFAULT 'pending',  -- pending|approved|spam
  created_at DATETIME(3), KEY idx_commentable (commentable_type, commentable_id, status)
);
```

## ۳. روت‌ها
```
GET  /blog                        فهرست مقالات (paginate)
GET  /blog/category/{slug}        بر اساس دسته
GET  /blog/tag/{slug}             بر اساس برچسب
GET  /blog/archive/{year}/{month} آرشیو
GET  /blog/{slug}                 مقاله + نظرات
POST /blog/{slug}/comments        ثبت نظر
GET  /blog/feed                   RSS
GET  /admin/blog/posts            مدیریت
```

## ۴. نقش‌ها
| نقش | مجوز |
|---|---|
| `reader` (مهمان) | خواندن، ثبت نظر |
| `author` | نوشتن مقالهٔ خود |
| `editor` | ویرایش/تأیید همه + نظرات |
| `admin` | همه |

مجوزها: `blog.post.{view,create,update,delete,publish}`, `blog.comment.{view,approve,delete}`.

## ۵. رویدادها
| رویداد | مصرف‌کننده |
|---|---|
| `blog.post.published` | search (index), notification (دنبال‌کنندگان), cache invalidate |
| `blog.comment.submitted` | moderation queue, notification (به نویسنده) |
| `blog.comment.approved` | notification (به نویسندهٔ نظر) |

## ۶. ویژگی‌ها
- ویرایشگر Markdown/RichText (با Sanitizer)
- تصویر شاخص (media variants)
- SEO (meta, sitemap, Open Graph)
- RSS/Atom feed
- جست‌وجوی فارسی (search engine)
- نظرات تو‌در‌تو (nested) با moderation
- زمان‌بندی انتشار (scheduled)
- آمار بازدید
- چندزبانه (i18n)

## ۷. تست E2E
| ID | سناریو |
|---|---|
| E2E-01 | نویسنده مقاله می‌سازد → editor تأیید → منتشر |
| E2E-02 | خواننده نظر می‌گذارد → moderation → نمایش |
| E2E-03 | جست‌وجوی فارسی |
| E2E-04 | RSS feed معتبر |
| E2E-05 | آرشیو بر اساس تاریخ جلالی |

## ۸. معیار پذیرش 🧪
- AC-BLOG-01: سناریوهای §7 سبز.
- AC-BLOG-02: SEO (meta/sitemap) کامل.
- AC-BLOG-03: XSS در نظرات/بدنه با Sanitizer دفع می‌شود.
- AC-BLOG-04: RSS با فرمت معتبر.

---

## ۹. وضعیت پیاده‌سازی (M9 — TVN-0091) ✅

| بخش | محل |
|---|---|
| ماژول دامنه | `modules/blog/` (tavaan.blog — hexagonal: Domain/Application/Infrastructure/Http) |
| ماژول‌های مشترک | `modules/core-user/` • `modules/content/` • `modules/comment/` • `modules/media/` |
| کیت | `kits/blog/` (kit.json + presets + theme + roles.json + demo) |
| نصب | `php bin/tvn kit:install blog --with-demo` |
| جداول | `blog_posts` / `blog_categories` / `blog_tags` / `blog_post_tag` / `blog_comments` (مهاجرت ماژول) |
| رویدادها | `blog.post.published|updated|deleted` + `blog.comment.submitted|approved|deleted` (نام‌محور + payload) |
| مصرف‌کنندهٔ رویدادها | search (ایندکس فارسی) • notification (نویسنده/نظردهنده) |
| نقش‌ها/مجوزها | `kits/blog/seed/roles.json` + manifest permissions → `tvn rbac:sync` |
| تست | `modules/blog/tests/` — Unit (دامنه) + Feature (E2E-01..05 سبز) |

انطباق با معیارهای پذیرش: AC-BLOG-01 (E2E §7 ✅ در `BlogE2ETest`)،
AC-BLOG-02 (meta/OG/sitemap ✅)، AC-BLOG-03 (escape-first Markdown +
Sanitizer + escape نظرات ✅)، AC-BLOG-04 (RSS/Atom با simplexml ✅).

```yaml
# machine-readable: tavaan.kit.blog.v1 (وضعیت M9)
kit:
  id: tavaan.kit.blog
  status: implemented
  ticket: TVN-0091
  modules: [tavaan.core-user, tavaan.content, tavaan.comment, tavaan.media, tavaan.blog]
  routes_public: [/blog, /blog/category/{slug}, /blog/tag/{slug}, /blog/archive/{year}/{month}, /blog/{slug}, /blog/feed, /blog/sitemap.xml]
  routes_admin: [/admin/blog/posts, /admin/blog/comments]
  events: [blog.post.published, blog.post.updated, blog.post.deleted, blog.comment.submitted, blog.comment.approved, blog.comment.deleted]
  tests: {unit: modules/blog/tests/Unit, feature: modules/blog/tests/Feature}
```
