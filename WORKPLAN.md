# نقشهٔ کار — تکمیل ماژول‌ها و افزودن ماژول‌های جدید (v0.20.0)

## وضعیت اولیه
- مخزن: Tavaan-Framework (clone در ./tavaan) — نسخه 0.19.0
- محیط: PHP 8.2.35 نصب شد، `.env` از `.env.example` (local + debug)، APP_KEY تولید شد
- Baseline تست: 1704 تست / 1682 pass / 11 error محیطی (APP_KEY خالی — رفع شد)

## ماژول‌های ناقص (بدون UI/route/controller)
| ماژول | وضعیت | کار |
|---|---|---|
| core-user | بدون route/ویو/کنترلر | احراز هویت (login/register/logout/profile) + مدیریت کاربران در پنل |
| media | فقط قرارداد/آداپتر | کتابخانهٔ رسانه (فهرست/آپلود/حذف) در پنل |
| comment | فقط دامنه | store عمومی + ثبت/نمایش نظر + moderation در پنل + API |
| content | فقط سرویس دامنه | صفحه‌های CMS (Page entity + CRUD + نمایش عمومی + sitemap) |

## ماژول‌های جدید
| ماژول | توضیح |
|---|---|
| poll | نظرسنجی: رأی‌گیری عمومی، نتایج زنده، پنل مدیریت + نمودار |
| faq | پرسش‌های متداول: دسته‌بندی، جست‌وجو، پنل مدیریت، شمارش بازدید |

## قراردادها (از ماژول blog/news آموخته شد)
- ServiceProvider: register() = bindings، boot() = lang namespace + require routes/{web,admin,api}.php
- ویوها: `module.json → views.namespace` ثبت خودکار توسط ViewServiceProvider (namespace = بخش بعد از نقطه در id)
- navigation/permissions از module.json (AdminServiceProvider + rbac:sync)
- 🔒 ARCH-003: ماژول فقط به Core (Facade/Contracts/Support) + Contracts ماژول‌های هم‌خانواده — بدون `Tavaan\Engine\` (استثنا: Console\Command، Audit\AuditEvent)
- migration: `Tavaan\Engine\Database\Migration\Migration` (فایل migrations خارج از src — مجاز)
- تست: `Tavaan\Testing\TestCase` + MakesHttpRequests + RefreshDatabase؛ کشف خودکار `modules/*/tests`
- ویو: `.tv.php` با @extends/@section/@if/@forelse/@csrf/@method + {{ }} escape خودکار
- helperها: view() trans() trans_number() jalali() is_rtl() redirect() route() csrf_field() config()

## مراحل
1. [ ] core-user: Auth UI + Admin User CRUD + ویوها + روت‌ها + widget داشبورد
2. [ ] media: Media Library پنل
3. [ ] comment: ماژول نظر مستقل (migration/store/services/controllers/views)
4. [ ] content: CMS Pages
5. [ ] poll: ماژول جدید کامل
6. [ ] faq: ماژول جدید کامل
7. [ ] مستندات: README ماژول‌ها، CHANGELOG 0.20.0، docs/manifest.json، bump نسخه
8. [ ] QA: lint:php، arch:check، security:scan، module:validate، env:check، test
9. [ ] دمو: migrate + seed + serve روی 0.0.0.0 (preview زنده)
