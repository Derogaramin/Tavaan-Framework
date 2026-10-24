# کیت خبرگزاری (tavaan.kit.news) 📰

پرتال خبری/مجلهٔ کامل روی فریمورک تَوان — **DOC-092 / TVN-0092 (M9)**.

## نصب

```bash
php bin/tvn kit:install news --with-demo
```

گام‌ها: پیش‌نیازها → ماژول‌ها → preset → migrate → theme → rbac → demo → status.

## آنچه دریافت می‌کنید

| قابلیت | مسیر |
|---|---|
| صفحهٔ اصلی + بنر خبر فوری | `GET /news` |
| خبر فوری (JSON برای widget/realtime) | `GET /news/breaking` |
| دسته (شامل زیردسته‌ها) / برچسب | `GET /news/category/{slug}` • `/news/tag/{slug}` |
| آرشیو جلالی | `GET /news/archive/{year}/{month}` |
| صفحهٔ خبر (ETag/304 + related + نظرات) | `GET /news/{slug}` |
| خروجی AMP | `GET /news/{slug}/amp` |
| ثبت نظر (throttle) | `POST /news/{slug}/comments` |
| RSS/Atom | `GET /news/feed[?format=atom]` |
| sitemap خودکار | `GET /news/sitemap.xml` |
| پنل اخبار + workflow | `GET /admin/news/articles` |
| مدیریت دسته‌ها | `GET /admin/news/categories` |
| moderation نظرات | `GET /admin/news/comments` |
| انتشار خودکار زمان‌بندی‌شده | `tvn news:publish-scheduled` (ثبت خودکار روی schedule:run) |

## گردش کار ویراستاری (DOC-092 §3)

```
reporter: draft → submit
editor:   review → approve (با scheduled_at اختیاری) / reject (با دلیل)
chief:    approved → publish • breaking • دسته‌ها
```

## نقش‌ها (seed/roles.json)

`reader` • `reporter` • `editor` • `chief-editor` • `admin`

کاربران demo (با `--with-demo` — مشترک با کیت وبلاگ): `admin@blog.test` /
`editor@blog.test` / `author@blog.test` / `reader@blog.test` — رمز: `password`.

> 💡 برای اختصاص نقش‌های خبری به کاربران: `tvn user:create reporter@news.test --role=reporter`

## کش (بحرانی — DOC-092 §5)

presetها TTLها را تنظیم می‌کنند: فهرست ۶۰s / خبر ۳۰۰s + **ETag/304** /
breaking ۱۰s؛ هر نوشتن → invalidate با tag. درایور کش سریع (redis) برای
ترافیک بالا توصیه می‌شود (`TVN_CACHE_DRIVER`).

## Presetها

| preset | تفاوت |
|---|---|
| `minimal` | TTL کوتاه، بدون realtime، نظرات فقط کاربران |
| `standard` (پیش‌فرض) | مطابق DOC-092 |
| `enterprise` | ترافیک بالا: TTL بلندتر، فید ۵۰، moderation سخت‌گیرانه |

## Theme

`themes/default` — سرخط تیره با accent قرمز خبری، بنر فوری تیره/کهربایی،
RTL کامل. کپی خودکار در `resources/views/vendor/news` (override استاندارد
ViewFinder) + assets در `public/assets/kits/news`.

## مستندات

- راهنمای ادمین/کاربر: `docs/`
- بلوپرینت دامنه: `docs/05-applications/news-portal.md` (DOC-092)
- ماژول news: `modules/news/README.md`
