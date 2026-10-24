# کیت وبلاگ (tavaan.kit.blog) ✍️

وبلاگ کامل شخصی/سازمانی روی فریمورک تَوان — **DOC-091 / TVN-0091 (M9)**.

## نصب

```bash
php bin/tvn kit:install blog --with-demo
```

گام‌ها (DOC-090 §8): پیش‌نیازها → فعال‌سازی ماژول‌ها → preset → migrate →
rbac (مجوزها + نقش‌ها) → demo seed → theme → ثبت وضعیت.

## آنچه دریافت می‌کنید

| قابلیت | مسیر |
|---|---|
| فهرست مقالات (paginate + جست‌وجو) | `GET /blog` |
| دسته / برچسب | `GET /blog/category/{slug}` • `GET /blog/tag/{slug}` |
| آرشیو جلالی (ارقام فارسی هم قبول است) | `GET /blog/archive/{year}/{month}` |
| مقاله + نظرات تو‌در‌تو | `GET /blog/{slug}` |
| ثبت نظر (مهمان/کاربر، moderation) | `POST /blog/{slug}/comments` |
| فید RSS/Atom | `GET /blog/feed[?format=atom]` |
| sitemap | `GET /blog/sitemap.xml` |
| پنل مدیریت مقالات | `GET /admin/blog/posts` |
| moderation نظرات | `GET /admin/blog/comments` |
| انتشار زمان‌بندی‌شده (cron) | `tvn blog:publish-scheduled` |

## ماژول‌ها

`tavaan.core-user` + `tavaan.content` + `tavaan.comment` + `tavaan.media` + `tavaan.blog`

## نقش‌ها (seed/roles.json)

| نقش | توانایی |
|---|---|
| `reader` | خواندن + ثبت نظر |
| `author` | نوشتن/ویرایش مقالهٔ خود (`blog.post.view/create`) |
| `editor` | همهٔ مقالات + انتشار + moderation نظرات |
| `admin` | همهٔ مجوزهای وبلاگ + کاربران |

کاربران demo (با `--with-demo`): `admin@blog.test` / `editor@blog.test` /
`author@blog.test` / `reader@blog.test` — رمز همه: `password`.

## Presetها

| preset | تفاوت |
|---|---|
| `minimal` | بدون جست‌وجو/اعلان، نظرات فقط کاربران |
| `standard` (پیش‌فرض) | مطابق DOC-091 |
| `enterprise` | صفحه‌بندی ۲۰، فید ۵۰، moderation سخت‌گیرانه (بدون auto-approve، max ۱ لینک) |

```bash
php bin/tvn kit:install blog --preset=enterprise --with-demo
```

## Theme

`themes/default` — RTL کامل با CSS logical properties، فونت Vazirmatn،
override ویوهای ماژول بدون تغییر کد (AC-APP-04). مسیر ویوها از راه
overlay پیکربندی (`config/kits.generated.php → blog.theme.views_path`)
به ماژول blog تزریق می‌شود.

## مستندات

- راهنمای کاربری/ادمین: `docs/`
- بلوپرینت دامنه: `docs/05-applications/blog.md` (DOC-091)
- ماژول blog: `modules/blog/README.md`
