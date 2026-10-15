# DOC-092 — کاربرد پرتال خبری (News Portal) 📰

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-092 |
| سطح | پایه → متوسط |
| معماری | `hmvc` |
| ماژول‌ها | core-user, content, media, comment |
| موتورها | search, cache, notification, i18n |

---

## ۱. دامنه
پرتال خبری/مجله با خبر، دسته، نویسنده، برچسب، گالری، ویراستار، زمان‌بندی انتشار،
خبر فوری، و AMP/RSS.

### ۱٫۱ تفاوت با وبلاگ
| ویژگی | وبلاگ | خبری |
|---|---|---|
| حجم محتوا | کم | زیاد و پرحجم |
| workflow انتشار | ساده | ویراستار → تأیید → انتشار |
| کش | متوسط | بحرانی (ترافیک بالا) |
| خبر فوری | ندارد | دارد (breaking) |
| AMP/SEO | خوب | حیاتی |

## ۲. مدل داده
```sql
CREATE TABLE news_articles (
  id CHAR(26) PRIMARY KEY, slug VARCHAR(255) UNIQUE,
  title VARCHAR(300), lead TEXT, body LONGTEXT,
  author_id BIGINT UNSIGNED, editor_id BIGINT UNSIGNED,
  category_id BIGINT UNSIGNED, 
  status VARCHAR(20) DEFAULT 'draft',   -- draft|review|approved|published|archived
  is_breaking TINYINT(1) DEFAULT 0, is_featured TINYINT(1) DEFAULT 0,
  featured_image CHAR(26), gallery JSON,
  view_count INT DEFAULT 0,
  published_at DATETIME(3), scheduled_at DATETIME(3),
  seo_title VARCHAR(300), seo_description TEXT,
  created_at DATETIME(3), updated_at DATETIME(3),
  KEY idx_status_pub (status, published_at), KEY idx_breaking (is_breaking, published_at)
);
CREATE TABLE news_categories (id BIGINT PK, parent_id BIGINT, title VARCHAR(190), slug VARCHAR(200) UNIQUE);
CREATE TABLE news_article_tag (article_id CHAR(26), tag_id BIGINT, PRIMARY KEY(article_id, tag_id));
```

## ۳. Workflow انتشار (با موتور workflow یا ساده)
```
draft → review (ویراستار) → approved → published
   ↑                          │
   └────── rejected ──────────┘
```
- نویسنده → draft → submit for review
- ویراستار → review → approve/reject
- زمان‌بندی: `scheduled_at` → انتشار خودکار با schedule

## ۴. نقش‌ها
| نقش | مجوز |
|---|---|
| `reporter` | نوشتن/ویرایش خبر خود، submit |
| `editor` | review/approve، ویرایش همه |
| `chief-editor` | انتشار، breaking، دسته‌ها |
| `admin` | همه |

## ۵. کش و کارایی (بحرانی) 🔒
| لایه | استراتژی |
|---|---|
| صفحهٔ خبر | Response cache با ETag + TTL |
| فهرست‌ها | Cache ۶۰s با tag invalidate |
| Breaking news | Cache کوتاه (۱۰s) یا realtime |
| تصاویر | media variants + CDN |
| جست‌وجو | search index |

```php
Route::get('/news/{slug}', [ArticleController::class, 'show'])
    ->cache(seconds: 300, tags: ['news.article'])
    ->etag();
// در انتشار/ویرایش: Cache::tags(['news.article'])->flush()
```

## ۶. ویژگی‌ها
- خبر فوری (breaking) با banner + realtime
- زمان‌بندی انتشار
- گالری تصاویر
- AMP output (برای موبایل/SEO)
- RSS/Atom
- جست‌وجوی فارسی
- نظرات با moderation
- آمار بازدید
- چندزبانه
- Related news (بر اساس دسته/برچسب)
- Sitemap خودکار

## ۷. SEO (حیاتی برای خبری)
```blade
<title>{{ $article->seo_title ?? $article->title }}</title>
<meta name="description" content="{{ $article->seo_description ?? $article->lead }}">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:title" content="{{ $article->title }}">
<meta property="og:image" content="{{ $article->featuredImage?->url() }}">
<meta property="og:type" content="article">
<script type="application/ld+json">
@json([
  '@context' => 'https://schema.org', '@type' => 'NewsArticle',
  'headline' => $article->title, 'datePublished' => $article->published_at?->toIso8601String(),
  'author' => ['@type'=>'Person','name'=>$article->author?->name],
])
</script>
```

## ۸. تست E2E
| ID | سناریو |
|---|---|
| E2E-01 | نویسنده خبر → ویراستار تأیید → انتشار |
| E2E-02 | زمان‌بندی انتشار خودکار |
| E2E-03 | breaking news + realtime |
| E2E-04 | کش صفحه + invalidate در ویرایش |
| E2E-05 | RSS/AMP |
| E2E-06 | جست‌وجو |

## ۹. معیار پذیرش 🧪
- AC-NEWS-01..06.
- AC-NEWS-07: زمان پاسخ صفحهٔ خبر کش‌شده ≤ ۳۰ms (p95).
- AC-NEWS-08: SEO score (Lighthouse) ≥ ۹۰.
