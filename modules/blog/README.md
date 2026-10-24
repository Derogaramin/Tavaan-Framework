# ماژول وبلاگ (tavaan.blog) ✍️

پیاده‌سازی کامل **DOC-091** (کاربرد وبلاگ) — تیکت **TVN-0091** از فاز M9.

| متادیتا | مقدار |
|---|---|
| شناسه | `tavaan.blog` |
| معماری | hexagonal (Domain / Application / Infrastructure / Http) |
| پیش‌نیاز ماژول‌ها | `tavaan.core-user`, `tavaan.content`, `tavaan.comment`, `tavaan.media` |
| پیش‌نیاز موتورها | view, i18n, search, media, notification, database, rbac, security |
| نصب (کیت) | `php bin/tvn kit:install blog --with-demo` |

## دامنه (DOC-091 §1)

| Aggregate | ریشه | رویدادها |
|---|---|---|
| Post | `Domain/Entities/Post` | `blog.post.published` / `blog.post.updated` (+ `deleted`) |
| Category | `Domain/Entities/Category` | — |
| Tag | `Domain/Entities/Tag` | — |
| Comment | ماژول `tavaan.comment` (پورت `CommentStoreInterface` → `blog_comments`) | `blog.comment.submitted` / `approved` / `deleted` |

`Post` invariantها را در دامنه نگه می‌دارد: ماتریس انتقال وضعیت
(`ContentStatus`)، published_at برای published/archived، زمان‌بندی آینده،
soft-delete و رویدادهای ثبت‌شده (pull-once).

## مسیرها (DOC-091 §3)

```
GET  /blog                          فهرست + جست‌وجو (?q=) + صفحه‌بندی
GET  /blog/category/{slug}          بر اساس دسته
GET  /blog/tag/{slug}               بر اساس برچسب
GET  /blog/archive/{year}/{month}   آرشیو جلالی (ارقام فارسی هم مجاز)
GET  /blog/{slug}                   مقاله + نظرات تو‌در‌تو + فرم نظر
POST /blog/{slug}/comments          ثبت نظر (مهمان/کاربر — throttle 5/10min)
GET  /blog/feed                     RSS 2.0 (?format=atom → Atom 1.0)
GET  /blog/sitemap.xml              sitemap
GET  /admin/blog/posts              پنل مدیریت مقالات (auth + Gate/Policy)
GET  /admin/blog/comments           moderation نظرات
```

## امنیت و مجوزها (DOC-091 §4 + DoD §1.4)

- مجوزها: `blog.post.{view,create,update,delete,publish}` و
  `blog.comment.{view,approve,delete}` — در manifest اعلام و با
  `tvn rbac:sync` همگام می‌شوند.
- `PostPolicy` (از راه `rbac.policies` در config ماژول — بدون resolve
  زودهنگام Gate): author فقط مقالهٔ خود، editor/admin همه.
- بدنهٔ مقاله: Markdown **escape-first** (ماژول content) + لایهٔ دوم
  `Security::sanitize` (پروفایل rich_text + تگ‌های h1/h4-h6/del/hr).
- نظرات: متن خام + escape در ویو (AC-BLOG-03 — تست XSS سبز).
- همهٔ کوئری‌ها prepared (بدون الحاق رشته).

## رویدادها و یکپارچگی (DOC-091 §5)

| رویداد | مصرف‌کننده |
|---|---|
| `blog.post.published` | `PostSearchIndexer` → ایندکس `blog_posts` (فارسی/BM25) |
| `blog.post.updated` | بازایندکس (published) یا حذف از ایندکس |
| `blog.post.deleted` | حذف از ایندکس |
| `blog.comment.submitted` | `CommentNotifier` → mail+database به نویسندهٔ مقاله |
| `blog.comment.approved` | `CommentNotifier` → mail به نویسندهٔ نظر (anonymous) |

## ساختار

```
modules/blog/
├── module.json                 ← manifest (مجوزها/رویدادها/جداول/navigation)
├── config/blog.php             ← per_page، moderation، feed، search، theme
├── config/rbac.php             ← نگاشت اعلامی PostPolicy
├── migrations/…create_blog_tables.php
├── routes/{web,admin}.php
├── seeds/demo.php              ← دادهٔ نمونه (idempotent)
├── src/
│   ├── Domain/{Entities,Events,Repositories}/
│   ├── Application/UseCases/{PostWriteService,PostReadService,CommentService,FeedService,SitemapService}.php
│   ├── Infrastructure/Persistence/{DatabasePostRepository,…,DatabaseCommentStore}.php
│   ├── Infrastructure/Search/PostSearchIndexer.php
│   ├── Infrastructure/Notifications/CommentNotifier.php
│   ├── Http/Controllers/{Blog,Comment,Feed,Sitemap}Controller.php + Admin/
│   ├── Policies/PostPolicy.php
│   ├── Console/Commands/PublishScheduledPostsCommand.php
│   └── Providers/BlogServiceProvider.php
├── views/                      ← blog:: (layouts/index/show/partials/admin)
├── lang/{fa,en}/messages.php
└── tests/{Unit,Feature}/       ← E2E-01..05 + XSS + SEO + فید + آرشیو
```

🔒 مرز ماژول (ARCH-003): src فقط به `Tavaan\Core\*` (Contracts/Facades/
Support/Exceptions)، `Modules\*\Contracts` و SDK مجاز (`Console\Command`)
ارجاع می‌دهد — `tvn arch:check` سبز.

## انتشار زمان‌بندی‌شده

```cron
* * * * * php bin/tvn blog:publish-scheduled
```

## تست

```bash
php bin/tvn test modules/blog     # Unit + Feature (E2E-01..05)
```
