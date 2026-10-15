# DOC-012 — قواعد نام‌گذاری تَوان (Naming Conventions) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-012 |
| وضعیت | Approved v1.0 |
| اصل | «نام، اولین مستند است» — نام باید نقش و دامنه را بدون توضیح برساند |

---

## ۱. هویت و برند

| قلم | مقدار |
|---|---|
| نام فارسی | **تَوان** |
| نام لاتین | **Tavaan** |
| ریشهٔ Namespace | `Tavaan\` |
| پیشوند CLI | `tvn` (دستور اصلی) و `tavaan` (Alias) |
| پسوند فایل اجرایی | `bin/tvn`, `bin/tvn.php` |
| دامنهٔ پیشنهادی مستندات | `tavaan.dev` / `docs.tavaan.dev` |
| Composer Vendor (برای انتشار اختیاری) | `tavaan` → `tavaan/core`, `tavaan/engine-http` |
| نام پکیج کیت‌ها | `tavaan/kit-shop`, `tavaan/kit-erp` |
| PrefiX جداول (اختیاری) | `tvn_` |
| پیشوند کلید Cache | `tvn:` |
| پیشوند صف | `tvn:queue:` |
| Header اختصاصی | `X-Tavaan-*` |

---

## ۲. ریشه‌های Namespace (نام‌های ثبت‌شده — تنها موارد مجاز) 🔒

| Namespace Root | مسیر فیزیکی | محتوا |
|---|---|---|
| `Tavaan\Core\` | `core/` | هستهٔ صفر‌وابستگی (Container، Events، Config، Errors، Contracts) |
| `Tavaan\Engine\<Name>\` | `engines/<kebab-name>/src/` | موتورهای خودنوشته |
| `Tavaan\Support\` | `core/Support/` | Helperها، Value Objectها، Collection (بدون State) |
| `Tavaan\Testing\` | `engines/test/src/` | ابزار تست |
| `App\` | `app/src/` | کد اپلیکیشن اصلی |
| `Modules\<Module>\` | `modules/<kebab>/src/` | ماژول‌های HMVC |
| `Kits\<Kit>\` | `kits/<kit>/src/` | کیت‌های اپلیکیشنی (Blog/Shop/ERP…) |
| `Plugins\<Plugin>\` | `plugins/<plugin>/src/` | پلاگین‌های ثالث (معاف از صفر‌وابستگی) |
| `Database\` | `database/seeders`, `database/factories` | Seeder/Factory |
| `Tavaan\Tests\` | `tests/` | تست‌های هسته |

> 🔒 هر namespace خارج از این فهرست = خطای `arch:check`.
> 🔒 یک کلاس نمی‌تواند همزمان در دو ریشه باشد.

---

## ۳. نام‌گذاری موتور (Engine Naming)

### ۳٫۱ الگو
```
engines/<kebab-case-name>/
├── src/                      → namespace: Tavaan\Engine\<PascalName>\
├── config/<name>.php         → کلیدهای تنظیمات
├── lang/fa/<name>.php, lang/en/<name>.php
├── migrations/               → (در صورت نیاز)
├── tests/                    → namespace: Tavaan\Tests\Engine\<PascalName>\
├── docs/<name>.md            → لینک به docs/03-engines/<name>.md
├── composer.json             → اختیاری، فقط برای انتشار
└── README.md                 → خلاصهٔ موتور
```

### ۳٫۲ جدول نام‌های رسمی موتورها (۵۳ موتور)

| # | نام لاتین (kebab) | Namespace | نام فارسی | دسته |
|---|---|---|---|---|
| 1 | `container` | `Tavaan\Engine\Container` | کانتینر DI | هسته |
| 2 | `config` | `Tavaan\Engine\Config` | پیکربندی | هسته |
| 3 | `event` | `Tavaan\Engine\Event` | رویداد | هسته |
| 4 | `pipeline` | `Tavaan\Engine\Pipeline` | خط لوله | هسته |
| 5 | `error` | `Tavaan\Engine\Error` | خطا | هسته |
| 6 | `log` | `Tavaan\Engine\Log` | گزارش | هسته |
| 7 | `optimizer` | `Tavaan\Engine\Optimizer` | بهینه‌ساز | هسته |
| 8 | `module` | `Tavaan\Engine\Module` | ماژول | هسته |
| 9 | `compat` | `Tavaan\Engine\Compat` | سازگاری | هسته |
| 10 | `http` | `Tavaan\Engine\Http` | اچ‌تی‌تی‌پی | وب |
| 11 | `router` | `Tavaan\Engine\Http\Routing` | مسیریاب | وب |
| 12 | `session` | `Tavaan\Engine\Session` | نشست | وب |
| 13 | `cookie` | `Tavaan\Engine\Http\Cookie` | کوکی | وب |
| 14 | `view` | `Tavaan\Engine\View` | نما | وب |
| 15 | `api` | `Tavaan\Engine\Api` | رابط برنامه‌نویسی | وب |
| 16 | `realtime` | `Tavaan\Engine\Realtime` | بلادرنگ | وب |
| 17 | `cors` | `Tavaan\Engine\Http\Cors` | کُرس | وب |
| 18 | `database` | `Tavaan\Engine\Database` | پایگاه داده | داده |
| 19 | `schema` | `Tavaan\Engine\Database\Schema` | ساختار | داده |
| 20 | `migration` | `Tavaan\Engine\Database\Migration` | مهاجرت | داده |
| 21 | `orm` | `Tavaan\Engine\Database\ORM` | نگاشت شیء-رابطه | داده |
| 22 | `validation` | `Tavaan\Engine\Validation` | اعتبارسنجی | داده |
| 23 | `search` | `Tavaan\Engine\Search` | جست‌وجو | داده |
| 24 | `pagination` | `Tavaan\Engine\Database\Pagination` | صفحه‌بندی | داده |
| 25 | `crypt` | `Tavaan\Engine\Crypt` | رمزنگاری | امنیت |
| 26 | `hash` | `Tavaan\Engine\Hash` | درهم‌سازی | امنیت |
| 27 | `auth` | `Tavaan\Engine\Auth` | احراز هویت | امنیت |
| 28 | `rbac` | `Tavaan\Engine\Rbac` | کنترل دسترسی | امنیت |
| 29 | `audit` | `Tavaan\Engine\Audit` | ممیزی | امنیت |
| 30 | `security` | `Tavaan\Engine\Security` | امنیت عرضی | امنیت |
| 31 | `cache` | `Tavaan\Engine\Cache` | حافظهٔ نهان | زیرساخت |
| 32 | `queue` | `Tavaan\Engine\Queue` | صف | زیرساخت |
| 33 | `schedule` | `Tavaan\Engine\Schedule` | زمان‌بندی | زیرساخت |
| 34 | `console` | `Tavaan\Engine\Console` | کنسول | زیرساخت |
| 35 | `filesystem` | `Tavaan\Engine\Filesystem` | سامانهٔ فایل | زیرساخت |
| 36 | `http-client` | `Tavaan\Engine\HttpClient` | کلاینت HTTP | زیرساخت |
| 37 | `mail` | `Tavaan\Engine\Mail` | پست | زیرساخت |
| 38 | `notification` | `Tavaan\Engine\Notification` | آگاه‌سازی | زیرساخت |
| 39 | `observability` | `Tavaan\Engine\Observability` | رصدپذیری | زیرساخت |
| 40 | `backup` | `Tavaan\Engine\Backup` | پشتیبان | زیرساخت |
| 41 | `i18n` | `Tavaan\Engine\I18n` | بین‌المللی | بومی |
| 42 | `jalali` | `Tavaan\Engine\I18n\Jalali` | تقویم جلالی | بومی |
| 43 | `workflow` | `Tavaan\Engine\Workflow` | گردش‌کار | دامنه |
| 44 | `report` | `Tavaan\Engine\Report` | گزارش | دامنه |
| 45 | `payment` | `Tavaan\Engine\Payment` | پرداخت | دامنه |
| 46 | `media` | `Tavaan\Engine\Media` | رسانه | دامنه |
| 47 | `feature-flag` | `Tavaan\Engine\FeatureFlag` | پرچم قابلیت | دامنه |
| 48 | `tenancy` | `Tavaan\Engine\Tenancy` | چنداجاره‌ای | دامنه |
| 49 | `scaffold` | `Tavaan\Engine\Scaffold` | داربست | ابزار |
| 50 | `admin` | `Tavaan\Engine\Admin` | پیشخان | ابزار |
| 51 | `plugin` | `Tavaan\Engine\Plugin` | افزونه | ابزار |
| 52 | `test` | `Tavaan\Engine\Test` | آزمون | ابزار |
| 53 | `upgrade` | `Tavaan\Engine\Upgrade` | ارتقا | ابزار |

> ⚠️ `autoloader` موتور جدا نیست؛ بخشی از هسته (`core/Autoloading/`) است — DOC-027.

### ۳٫۳ پسوند کلاس‌ها در موتورها

| پسوند | معنا | مثال |
|---|---|---|
| `*Provider` | ServiceProvider موتور | `HttpServiceProvider` |
| `*Engine` | نقطهٔ ورود عمومی موتور | `CacheEngine` |
| `*Interface` | قرارداد | `CacheStoreInterface` |
| `*Manager` | مدیر درایور/چند‌نمونه | `CacheManager` |
| `*Factory` | سازنده | `ResponseFactory` |
| `*Builder` | سازندهٔ تدریجی | `QueryBuilder` |
| `*Compiler` | کامپایلر | `TemplateCompiler` |
| `*Resolver` | حل‌کننده | `ControllerResolver` |
| `*Dispatcher` | پخش‌کننده | `EventDispatcher` |
| `*Handler` | پردازشگر | `ExceptionHandler` |
| `*Middleware` | میان‌افزار | `CsrfMiddleware` |
| `*Command` | دستور CLI یا CQRS | `CacheClearCommand` |
| `*Job` | کار صف | `SendMailJob` |
| `*Listener` | شنوندهٔ رویداد | `OrderPlacedListener` |
| `*Policy` | سیاست دسترسی | `OrderPolicy` |
| `*Request` | Form Request | `StoreOrderRequest` |
| `*Resource` | خروجی API | `OrderResource` |
| `*Exception` | استثنا | `TokenExpiredException` |
| `*Test` | تست | `RouterTest` |
| `*Driver` | پیاده‌سازی درایور | `RedisCacheDriver` |
| `*Contract` | قرارداد دامنه‌ای | `PaymentGatewayContract` |
| `*Adapter` | مبدل | `S3FilesystemAdapter` |
| `*Guard` | محافظ | `RateLimitGuard`, `SsrfGuard` |

---

## ۴. نام‌گذاری ماژول (Module Naming)

```
modules/<kebab-name>/
  ├── module.json          → manifest (id, version, deps, providers, permissions)
  ├── src/                 → namespace: Modules\<PascalName>\
  │   ├── Http/{Controllers,Middleware,Requests,Resources}
  │   ├── Domain/{Entities,ValueObjects,Services,Events}
  │   ├── Application/{Commands,Queries,Handlers,UseCases}
  │   ├── Infrastructure/{Repositories,Gateways,Persistence}
  │   ├── Providers/
  │   ├── Policies/
  │   └── Contracts/       → تنها بخش عمومی ماژول
  ├── config/  routes/  views/  lang/  migrations/  seeds/  tests/  docs/
```

| قاعده | مثال |
|---|---|
| شناسهٔ ماژول: `<vendor>.<name>` | `tavaan.shop`, `tavaan.blog` |
| نام نمایشی فارسی در `module.json` | `"title_fa": "فروشگاه"` |
| Controller: `<Entity>Controller` | `ProductController` |
| Model: اسم مفرد | `Product`, `OrderItem` |
| Table: جمع snake_case | `products`, `order_items` |
| Route Name: `<module>.<resource>.<action>` | `shop.product.update` |
| Permission: `<module>.<resource>.<action>` | `shop.product.delete` |
| View: kebab-case | `modules/shop/views/product/edit.blade.php` → در تَوان: `product/edit.tv.php` |
| Event: `<Aggregate><PastVerb>` | `OrderWasPaid` |

> پسوند فایل View در تَوان: **`.tv.php`** (Tavaan View) — تا با Blade/PHP خام اشتباه نشود.
> الگوی جایگزینی: `{{ }}` (escape)، `{!! !!}` (raw)، `@directive` (دستور).

---

## ۵. نام‌گذاری پایگاه داده

| عنصر | قاعده | مثال |
|---|---|---|
| دیتابیس | snake_case | `tavaan_shop` |
| جدول | جمع، snake_case، بدون پیشوند در ماژول (پیشوند سراسری اختیاری `tvn_`) | `users`, `order_items` |
| جدول واسط (Pivot) | الفبایی دو اسم مفرد با `_` | `product_tag`, `role_user` |
| ستون کلید اصلی | `id` (bigint unsigned / char(26) ulid) | — |
| کلید خارجی | `<singular>_id` | `user_id`, `order_id` |
| Morph | `<name>_type` + `<name>_id` | `commentable_type`, `commentable_id` |
| بولین | `is_*`, `has_*`, `can_*` | `is_active`, `has_paid` |
| تاریخ | `*_at` (datetime), `*_date` (date) | `created_at`, `birth_date` |
| شمارنده | `*_count` | `comments_count` |
| مبلغ | `*_amount` + `*_currency` | `total_amount`, `currency` |
| ایندکس | `idx_<table>_<cols>` | `idx_users_email` |
| یونیک | `uk_<table>_<cols>` | `uk_users_email` |
| Foreign Key | `fk_<table>_<ref>` | `fk_order_items_order` |
| View دیتابیس | `v_<name>` | `v_sales_monthly` |
| Procedure (اختیاری) | `sp_<name>` | `sp_recalc_stock` |
| Trigger (اختیاری) | `trg_<table>_<time>_<action>` | `trg_orders_after_insert` |
| Migration | `YYYY_MM_DD_HHMMSS_<verb>_<table>.php` | `2026_01_12_093000_create_orders_table.php` |

### ۵٫۱ ستون‌های استاندارد هر جدول (Baseline Columns)

```
id            bigint unsigned PK / یا ulid char(26)
created_at    datetime(3)  NOT NULL  (UTC)
updated_at    datetime(3)  NULL
deleted_at    datetime(3)  NULL      (Soft Delete، در صورت نیاز)
created_by    bigint NULL              (Audit)
updated_by    bigint NULL
tenant_id     bigint NULL              (Multi-Tenant، در صورت فعال بودن)
version       int NOT NULL DEFAULT 1   (Optimistic Lock، در صورت نیاز)
```

---

## ۶. نام‌گذاری تنظیمات (Config Keys)

```
<engine|module>.<group>.<key>

app.name                  app.env                 app.debug
app.locale                app.fallback_locale     app.timezone
http.middleware.global    http.trusted_proxies
db.default                db.connections.mysql.host
cache.default             cache.stores.redis.prefix
queue.default             queue.connections.redis.queue
auth.guards.web.driver    auth.providers.users.model
mail.default              mail.mailers.smtp.host
log.default               log.channels.daily.days
security.csrf.enabled     security.headers.csp
```

| قاعده |
|---|
| همه lowercase و snake_case |
| حداکثر ۴ سطح عمق |
| کلیدهای بولین با `is_`/`enable_` شروع نشوند؛ خود کلید گویا باشد (`security.csrf.enabled`) |
| مقدار پیش‌فرض همیشه در `config/<file>.php` موجود باشد (بدون نیاز به `.env`) |
| متغیرهای ENV با پیشوند `TVN_`: `TVN_DB_HOST` |

---

## ۷. نام‌گذاری روت و URL

| قاعده | مثال |
|---|---|
| kebab-case، جمع برای منابع | `/blog/posts`, `/shop/products/{product}` |
| پارامتر: اسم مفرد | `{product}`, `{slug}` |
| روت نام‌دار: `<module>.<resource>.<action>` | `shop.product.show` |
| پیشوند API: `/api/v{n}` | `/api/v1/orders` |
| پیشوند Admin: `/admin/<module>` | `/admin/shop/products` |
| Actionهای غیر CRUD با فعل | `POST /orders/{order}/cancel` |
| ممنوعیت verb در مسیر (جز استثنا) | ❌ `/getProducts` |

---

## ۸. نام‌گذاری رویداد، صف، کلید Cache، قفل

| نوع | الگو | مثال |
|---|---|---|
| Event Class | `<Aggregate><PastVerb>` | `OrderWasPaid` |
| Event Name (string) | `<domain>.<aggregate>.<verb>` | `shop.order.paid` |
| Job Class | `<Verb><Noun>Job` | `SendInvoiceEmailJob` |
| Queue Name | `<module>.<priority>` | `shop.high`, `mail.low` |
| Cache Key | `tvn:<engine>:<entity>:<id>[:<variant>]` | `tvn:orm:user:12:profile` |
| Cache Tag | `<engine>:<entity>` | `orm:user` |
| Lock Key | `tvn:lock:<resource>:<id>` | `tvn:lock:order:12` |
| Metric Name | `tavaan_<component>_<name>_<unit>` | `tavaan_http_request_duration_seconds` |
| Trace Span | `<component>.<operation>` | `db.query`, `http.route` |
| Log Channel | lowercase | `app`, `audit`, `query`, `queue` |
| Permission | `<module>.<resource>.<action>` | `shop.order.refund` |
| Role | lowercase با `-` | `super-admin`, `shop-manager` |
| Feature Flag | `<module>.<feature>` | `shop.gift-card` |

---

## ۹. نام‌گذاری فایل‌های غیر‌PHP

| نوع | قاعده | مثال |
|---|---|---|
| View | kebab-case + `.tv.php` | `product-list.tv.php` |
| Config | lowercase | `app.php`, `database.php` |
| Lang | `<namespace>.php` | `lang/fa/shop.php` |
| Migration | timestamp_snake | `2026_01_12_093000_create_orders_table.php` |
| Seed | `<Table>Seeder.php` | `ProductSeeder.php` |
| Asset | kebab + hash در build | `app.9f3c2b.css` |
| Doc | kebab-case `.md` | `docs/03-engines/router.md` |
| Test | `<Class>Test.php` | `RouterTest.php` |
| Fixture | `fixtures/<name>.json` | `fixtures/order-paid.json` |
| Env | `.env`, `.env.example`, `.env.testing` | — |

---

## ۱۰. نام‌های رزروشده (Reserved) 🔒

این نام‌ها **نمی‌توانند** برای ماژول/موتور/روت/کلید تنظیمات استفاده شوند:

```
tavaan, tvn, core, engine, engines, kernel, module, modules, kit, kits,
plugin, plugins, app, admin, api, auth, cache, config, database, db,
default, docs, events, http, log, queue, router, session, storage,
system, temp, test, tests, tmp, vendor, views
```

همچنین در سطح DB:
```
information_schema, performance_schema, mysql, sys, tavaan, migrations,
jobs, cache, sessions, users (برای prefix سراسری رزرو است)
```

---

## ۱۱. چک‌لیست نام‌گذاری برای بازبین

- [ ] Namespace مطابق جدول بخش ۲ است؟
- [ ] نام کلاس PascalCase و دارای پسوند معنایی صحیح (بخش ۳٫۳)؟
- [ ] نام متد با فعل شروع می‌شود و Bool با `is/has/can/should`؟
- [ ] نام جدول/ستون مطابق بخش ۵؟
- [ ] کلید تنظیمات ≤ ۴ سطح و با ENV متناظر `TVN_*`؟
- [ ] نام روت شامل ماژول و منبع و عمل است؟
- [ ] هیچ نام رزروشده نقض نشده؟
- [ ] نام‌های فارسی در `lang/fa` و بدون Hardcode در کد؟
