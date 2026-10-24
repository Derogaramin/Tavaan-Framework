# ماژول محتوا (tavaan.content) 📝

هستهٔ مشترک محتوا برای کیت‌های M9 (Blog/News/…) — دامنهٔ **خالص** (بدون DB/HTTP/موتور).

| متادیتا | مقدار |
|---|---|
| شناسه | `tavaan.content` |
| مستند مرجع | DOC-090 §5-6، DOC-091 §6 |
| معماری | دامنهٔ خالص (ValueObject/Service — بدون Infrastructure) |
| مصرف‌کنندگان | `tavaan.blog` (کیت وبلاگ)، خبری (M9) |

## اجزا

| کلاس | نقش |
|---|---|
| `ContentStatus` | enum چرخهٔ انتشار: draft/published/scheduled/archived + ماتریس انتقال |
| `Slug` (VO) | slugify یونیکد-امن فارسی‌اول (ی/ک، ارقام فارسی، حذف اِعراب، نیم‌فاصله) + `unique()` |
| `SeoMetadata` (VO) | meta/Open Graph با truncation استاندارد (60/160) |
| `Markdown` | زیرمجموعهٔ Markdown → HTML با طراحی **escape-first** (XSS-safe) + allowlist لینک |
| `JalaliMonth` | ماه جلالی → بازهٔ میلادی `[start, end)` برای آرشیو (الگوریتم jalaali، هم‌نتیجه با موتور i18n) |
| `FeedBuilder` | RSS 2.0 + Atom 1.0 معتبر (RFC-822/RFC-3339، ENT_XML1) |
| `SitemapBuilder` | sitemap XML (sitemaps.org 0.9) |
| `ExcerptGenerator` | خلاصهٔ mb-امن از HTML در مرز کلمه |

## 🔒 مرز معماری (ARCH-003)

ماژول هیچ ارجاعی به `Tavaan\Engine\*` ندارد. `JalaliMonth` زیرمجموعهٔ الگوریتم
jalaali (breaks-based) را بازپیاده‌سازی می‌کند — همان `BREAKS` و arithmetic موتور
`Jalali` (تست round-trip با بردارهای تأییدشده).

## پیکربندی — `config('content.*')`

```php
'markdown' => ['allow_images' => true],
'feed'     => ['limit' => 20, 'language' => 'fa'],
'excerpt'  => ['chars' => 200],
```

## ترجمه

`content::status.draft|published|scheduled|archived` در `lang/{fa,en}/status.php`.

## تست

```bash
php bin/tvn test modules/content     # ۲۸ تست / ۱۳۸ assertion
```

پوشش: ماتریس انتقال وضعیت، بردارهای slug فارسی، XSS (۸ بردار)، فید RSS/Atom با
simplexml، sitemap، بردارهای جلالی (۱۴۰۳ کبیسه/۱۴۰۲ عادی)، round-trip تبدیل.
