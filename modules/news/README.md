# ماژول خبرگزاری (tavaan.news) 📰

پیاده‌سازی کامل **DOC-092** (پرتال خبری) — تیکت **TVN-0092** از فاز M9.

| متادیتا | مقدار |
|---|---|
| شناسه | `tavaan.news` |
| معماری | hexagonal (Domain / Application / Infrastructure / Http) |
| پیش‌نیاز ماژول‌ها | `tavaan.core-user`, `tavaan.content`, `tavaan.comment`, `tavaan.media` |
| پیش‌نیاز موتورها | view, i18n, search, cache, notification, database, rbac, security, media, schedule, realtime |
| نصب (کیت) | `php bin/tvn kit:install news --with-demo` |

## workflow انتشار (DOC-092 §3)

```
draft ──submit──▶ review ──approve──▶ approved ──publish──▶ published ──▶ archived
  ▲                  │                    │                      │
  └────── reject ←───┘                    └── scheduled_at → انتشار خودکار (cron/schedule)
```

- `ArticleStatus` — enum پنج‌وضعیتی با ماتریس انتقال (draft مستقیم منتشر **نمی‌شود**).
- `Article` (Aggregate) — transitionها فقط با متدهای دامنه (`submitForReview/approve/
  reject/publish/archive/unpublish/setBreaking`) + ثبت `editor_id` و رویدادها.
- زمان‌بندی: `approved` + `scheduled_at` → `tvn news:publish-scheduled`
  (در boot ماژول روی `schedule:run` هر دقیقه ثبت می‌شود).

## نقش‌ها و مجوزها (DOC-092 §4)

| نقش | مجوزها |
|---|---|
| `reporter` | view/create/submit — ویرایش خبر **خود** فقط در pipeline (Policy) |
| `editor` | + update همه، review/approve/reject، moderation نظرات |
| `chief-editor` | + publish، breaking، مدیریت دسته‌ها، delete |
| `admin` | همه |

مجوزها: `news.article.{view,create,update,delete,submit,review,approve,publish,breaking}`،
`news.category.{view,manage}`، `news.comment.{view,approve,delete}` — در manifest +
`tvn rbac:sync`. Policy از راه config اعلامی (`modules/news/config/rbac.php → rbac.policies`).

## کش و کارایی (DOC-092 §5 — بحرانی) 🔒

| لایه | پیاده‌سازی |
|---|---|
| فهرست‌ها | `Cache::tags(['news'])->remember(...)` با TTL 60s |
| صفحهٔ خبر | TTL 300s (کانفیگ) + **ETag/304** روی پاسخ (بدون رندر در درخواست تکراری) |
| Breaking | TTL 10s |
| invalidate | هر نوشتن → `Cache::tags(['news'])->flush()` |
| جست‌وجو | ایندکس `news_articles` (رویداد → indexer) |

## ویژگی‌ها (DOC-092 §6-7)

- **خبر فوری**: بنر در layout + `GET /news/breaking` (JSON) + broadcast روی کانال
  `news` (Broadcast::publish — best-effort) هنگام انتشار/علامت‌گذاری.
- **گالری**: ستون JSON از ULIDهای `tvn_media` — رندر با `MediaGatewayInterface`.
- **AMP**: `GET /news/{slug}/amp` — `<html ⚡>` + canonical + boilerplate +
  JSON-LD + تبدیل `<img>`→`<amp-img>` و حذف style درون‌خطی.
- **SEO**: meta/OG + **JSON-LD NewsArticle** (schema.org) + canonical + sitemap
  خودکار (breaking → changefreq=always/priority=1.0).
- **دستهٔ تو‌در‌تو**: `parent_id` + صفحهٔ دسته شامل زیردسته‌ها (`withDescendants`).
- **related news**: هم‌برچسب (اولویت) سپس هم‌دسته.
- RSS/Atom، آرشیو جلالی (ارقام فارسی ✓)، نظرات تو‌در‌تو با moderation،
  اعلان‌های workflow به نویسنده (approved/rejected/published).

## مسیرها

```
GET  /news                       فهرست + بنر فوری + جست‌وجو (?q=)
GET  /news/breaking              JSON خبرهای فوری
GET  /news/category/{slug}       دسته (شامل زیردسته‌ها)
GET  /news/tag/{slug}            برچسب
GET  /news/archive/{year}/{month}  آرشیو جلالی
GET  /news/{slug}                خبر + related + نظرات (ETag/304)
GET  /news/{slug}/amp            خروجی AMP
POST /news/{slug}/comments       ثبت نظر (throttle 5/10min)
GET  /news/feed                  RSS 2.0 (?format=atom)
GET  /news/sitemap.xml           sitemap
GET  /admin/news/articles        پنل + workflow (submit/approve/reject/publish/breaking/archive)
GET  /admin/news/categories      مدیریت دسته‌ها
GET  /admin/news/comments        moderation نظرات
```

## رویدادها

`news.article.{submitted,approved,rejected,published,updated,deleted}` +
`news.breaking.published` + `news.comment.{submitted,approved,deleted}` —
مصرف‌کنندگان: search indexer، cache invalidate، WorkflowNotifier، BreakingBroadcaster.

## تست

```bash
php bin/tvn test modules/news    # 27 تست — Unit (workflow/JSON-LD) + E2E-01..06
```

پوشش E2E: گردش کامل editorial + ماتریس ۴۰۳ نقش‌ها، زمان‌بندی خودکار،
breaking (بنر/JSON/403)، کش+ETag/304+invalidate، RSS/Atom/AMP، جست‌وجوی فارسی،
JSON-LD/canonical/sitemap، دستهٔ تو‌در‌تو، moderation+XSS نظرات.
