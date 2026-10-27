# ماژول پرسش‌های متداول (FAQ) — tavaan.faq

> پرسش‌های متداول دسته‌بندی‌شده با پاسخ markdown، صفحهٔ عمومی آکاردئونی،
> جست‌وجو، رأی «مفید بود» و پنل مدیریت RBAC.

- **نسخه:** 0.1.0 — **بلیت:** TVN-FAQ-001..007
- **وابستگی:** `tavaan.content` (اسلاگ، Markdown، ContentStatus، Security::sanitize مشترک)
- **جداول:** `faq_categories`، `faq_items` (prefix ماژول: `faq_`)

## قابلیت‌ها

| قابلیت | توضیح |
|---|---|
| دسته‌بندی | اسلاگ یکتا (خودکار از عنوان)، ترتیب نمایش، حذف نرم |
| چرخهٔ وضعیت | `draft → published → archived → draft` با ماتریس `ContentStatus` ماژول content |
| پاسخ | markdown → HTML (escape-first) → `Security::sanitize` (پروفیل `rich_text`) |
| صفحهٔ عمومی | `/faq` — آکاردئون `<details>` بدون JS، گروه‌بندی per دسته + «بدون دسته» |
| جست‌وجو | `/faq?q=` روی پرسش و متن پاسخ (حداقل طول: `faq.search.min_length`) |
| فیلتر دسته | `/faq?cat={slug}` |
| رأی مفید | `POST /faq/{id}/helpful` (yes/no) — یک‌بار در هر نشست + throttle مسیر |
| API | `GET /api/faqs` — درخت عمومی JSON (data/meta پایدار) |
| پنل | `/admin/faqs` (CRUD + انتشار/بایگانی/پیش‌نویس + زباله‌دان) و `/admin/faq-categories` |
| ویجت | پیشخوان مدیریت: شمار منتشرشده‌ها و دسته‌ها |

## مجوزها (RBAC)

`faq.view` • `faq.create` • `faq.update` • `faq.publish` (انتشار/بایگانی/بازگردانی) • `faq.delete`

## رویدادها

| رویداد | نوع |
|---|---|
| `faq.item.published` | کلاس `FaqItemWasPublished` |
| `faq.item.updated` | کلاس `FaqItemWasUpdated` |
| `faq.item.deleted` | payload (trash و force) |
| `faq.helpful.voted` | payload |
| `faq.category.deleted` | payload |

## پیکربندی (`config/faq.php`)

`enabled` • `per_page` • `admin_per_page` • `body_format` (markdown/html) •
`sanitize.profile`/`sanitize.extra_tags` • `helpful.enabled` • `search.min_length`

## معماری

```
Domain/Entities      FaqCategory, FaqItem (mutable + pullEvents — هم‌راستا با page/poll)
Domain/Events        FaqItemWasPublished, FaqItemWasUpdated
Domain/Repositories  FaqCategoryRepositoryInterface, FaqItemRepositoryInterface
Infrastructure       DatabaseFaqCategoryRepository, DatabaseFaqItemRepository (فقط Facade DB)
Application/UseCases FaqWriteService, FaqReadService
Http/Controllers     FaqController (عمومی/API), Admin\FaqAdminController, Admin\FaqCategoryAdminController
Providers            FaqServiceProvider (bindings + routes + lang + widget)
Dashboard            FaqWidget (Renderable)
```

🔒 **ARCH-003:** ماژول فقط از Core Contracts/Facades استفاده می‌کند؛
`Markdown` و `Slug` و `ContentStatus` از دامنهٔ ماژول content وارد می‌شوند
(وابستگی صریح در `module.json`).

## تست‌ها

- `tests/Unit/FaqDomainTest.php` — موجودیت‌ها، ماتریس وضعیت، رویدادها، نگاشت سطر
- `tests/Unit/FaqWriteServiceTest.php` — اعتبارسنجی، اسلاگ یونیک، پاک‌سازی، چرخهٔ عمر، رأی مفید
- `tests/Feature/FaqHttpTest.php` — E2E با RBAC واقعی: پنل، نمای عمومی، جست‌وجو، فیلتر، رأی، XSS، API

```bash
php bin/tvn test --filter=Faq
```
