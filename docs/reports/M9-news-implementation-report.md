# گزارش پیاده‌سازی M9 — گام ۲: کیت خبرگزاری 📰

**تاریخ:** ۲۰۲۶-۱۰-۲۴ • **فاز:** M9 (DOC-120) • **تیکت:** TVN-0092 (Kit: News Portal — DOC-092)
**نتیجه:** `۱۳۶۹ تست / ۸۹۲۸ assertion سبز` (+۲۷ از گام ۱) • ۵۹ manifest فعال (۵۲ موتور + ۶ ماژول + کیت‌ها) • arch:check سبز • security:scan بدون یافتهٔ جدید • lint پاک

---

## ۱. خروجی‌ها

| # | خروجی | محل |
|---|---|---|
| 1 | **ماژول `tavaan.news`** — پیاده‌سازی کامل DOC-092: Article aggregate با workflow پنج‌وضعیتی، ۸ رویداد دامنه، ۳ پورت مخزن + ۴ آداپتر، ۵ UseCase، ArticlePolicy، ۸ کنترلر، JsonLdBuilder، ArticleSearchIndexer، BreakingBroadcaster (realtime)، WorkflowNotifier، کش tag-based + ETag/304، `tvn news:publish-scheduled` (ثبت خودکار روی schedule:run)، ویوهای RTL + AMP، مهاجرت ۵ جدول `news_*`، seed دمو | `modules/news/` |
| 2 | **کیت `tavaan.kit.news`** — kit.json + ۳ preset (minimal/standard/enterprise با TTLهای کش متفاوت) + theme خبری (هدر گرادیان/بنر فوری تیره-کهربایی) + roles.json (reporter/editor/chief-editor/admin/reader) + demo + install/steps.json + README + راهنمای ادمین/کاربر | `kits/news/` |
| 3 | **تست‌ها** — ۲۷ تست: Unit (ماتریس workflow ۱۵ انتقال، breaking toggle/رویدادها، گالری round-trip، JSON-LD schema.org، دستهٔ تو‌در‌تو) + Feature E2E-01..06 (گردش کامل editorial + ماتریس ۴۰۳ نقش‌ها، زمان‌بندی خودکار، breaking بنر/JSON، کش+ETag/304+invalidate، RSS/Atom/AMP، جست‌وجوی فارسی، JSON-LD/canonical/sitemap، زیردسته‌ها، moderation+XSS) | `modules/news/tests/` |

## ۲. معیارهای پذیرش (DOC-092 §8-9)

| AC | وضعیت |
|---|---|
| E2E-01: نویسنده → ویراستار تأیید → انتشار | ✅ با ماتریس ۴۰۳ (reporter نمی‌تواند approve، editor نمی‌تواند publish) + reject با دلیل |
| E2E-02: زمان‌بندی انتشار خودکار | ✅ approve با scheduled_at → مخفی → `news:publish-scheduled` → منتشر؛ زمان آینده منتشر نمی‌شود |
| E2E-03: breaking + realtime | ✅ بنر در layout + `GET /news/breaking` (JSON) + Broadcast::publish (best-effort) + ۴۰۳ برای reporter |
| E2E-04: کش صفحه + invalidate در ویرایش | ✅ Cache::tags(['news']) + ETag/304 (If-None-Match) + مشاهدهٔ خبر جدید پس از invalidate |
| E2E-05: RSS/AMP | ✅ RSS/Atom با simplexml معتبر + AMP: `<html ⚡>`/canonical/boilerplate/v0.js/ld+json |
| E2E-06: جست‌وجو | ✅ ایندکس فارسی (رویداد→indexer→native BM25) + fallback LIKE |
| AC-NEWS-07: ≤30ms p95 با کش | ⚠️ ساختار کامل (304 بدون رندر + remember تگ‌محور)؛ عدد p95 سخت‌افزار-وابسته — bench اختصاصی به Release 1.0 موکول |
| AC-NEWS-08: Lighthouse ≥ 90 | ⚠️ نیازمند ابزار مرورگری (خارج sandbox) — SEO فنی (meta/OG/canonical/ld+json/AMP/sitemap) کامل است |

## ۳. نکات طراحی و یافته‌ها

1. **`$this->app` در ServiceProviderها Container است نه Application** — گارد
   «حالت تست» برای ثبت schedule با `$_SERVER['TVN_APP_ENV']` + helper
   سراسری `app()->runningUnitTests()` پیاده شد. (ثبت schedule ماژول در محیط
   تست باعث nondeterminism تست‌های موتور schedule می‌شد — ۱۳ تست.)
2. **bind نشدن `CommentStoreInterface` به‌صورت سراسری** — blog و news هر دو
   store اختصاصی خود را مستقیم تزریق می‌کنند (تداخل binding سراسری رفع شد).
3. **include ویو دادهٔ والد را ارث نمی‌برد** (تفاوت با Blade) — partialها
   با آرایهٔ صریح داده include می‌شوند.
4. **workflow سخت‌گیرانه در دامنه** — publish مستقیم از draft/review با
   `DomainException` رد می‌شود؛ کنترلر خطا را به flash تبدیل می‌کند (بدون ۵۰۰).
5. کش فهرست‌ها **rows خام (toRow)** را serialize می‌کند (پایداری بین
   درایورهای file/redis) و پس از خواندن به entity نگاشت می‌شود.

## ۴. راستی‌آزمایی دستی

```bash
php bin/tvn rbac:sync                      # +14 مجوز news
php bin/tvn kit:install news --with-demo   # migrate(1) + theme + نقش‌ها + demo
php -S 127.0.0.1:8899 -t public public/index.php
# /news → 200 theme کیت + بنر فوری • /news/breaking → JSON ✓
# /news/{slug} → 200 + ETag؛ درخواست با If-None-Match → 304 ✓
# /news/{slug}/amp → ⚡ + ld+json ✓ • category/tech (شامل زیردسته ai) ✓
# archive/۱۴۰۵/۰۸ (فارسی) ✓ • feed/atom/sitemap ✓ • ?q=فناوری ✓
# /admin/news/articles → 302 auth ✓ • schedule:list → news:publish-scheduled ✓
```

## ۵. وضعیت پس از این گام

- M9: **۲ از ۸ کیت** (Blog ✅ News ✅). زیرساخت مشترک (content/comment/
  media/core-user + KitManager) برای Shop/Forum/LMS/CRM/ERP/BPMS آماده است.
- تیکت بعدی طبق DOC-121 §7: **TVN-0093 (Kit: Shop — DOC-093)**.
