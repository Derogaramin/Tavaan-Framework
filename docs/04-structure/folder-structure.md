# DOC-080 — ساختار پوشه و فایل تَوان (Folder & File Structure) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-080 |
| وضعیت | Approved v1.0 |
| اصل | هر فایل یک جای مشخص دارد؛ هر جای یک مسئولیت |

---

## ۱. نمای کامل پروژه (نصب مرجع)

```
tavaan/
│
├── bin/                                  ← 🚪 Entry Pointهای اجرایی
│   ├── tvn                               ← اسکریپت اصلی CLI (بدون پسوند، +x)
│   ├── tvn.php                           ← نسخهٔ ویندوز/PHP
│   ├── tavaan                            ← Alias
│   └── worker.php                        ← Entry صف/Realtime
│
├── public/                               ← 🌐 تنها پوشهٔ عمومی وب
│   ├── index.php                         ← Entry Point HTTP
│   ├── .htaccess                         ← Rewrite برای Apache
│   ├── robots.txt
│   ├── favicon.ico
│   ├── assets/                           ← خروجی build (CSS/JS/Image)
│   │   ├── app.css
│   │   ├── app.js
│   │   └── modules/
│   ├── uploads/                          ← ⚠️ فقط با Nginx/FPM؛ ترجیحاً خارج از public
│   └── media/
│
├── core/                                 ← 🧠 هستهٔ صفر‌وابستگی (Tavaan\Core\)
│   ├── bootstrap.php                     ← 🔑 تنها require دستی
│   ├── Application/
│   │   ├── Application.php
│   │   ├── ApplicationInterface.php
│   │   └── ApplicationBuilder.php
│   ├── Autoloading/
│   │   ├── Autoloader.php
│   │   ├── AutoloaderRegistry.php
│   │   ├── ClassMapGenerator.php
│   │   └── AutoloadException.php
│   ├── Bootstrap/
│   │   ├── BootstrapperInterface.php
│   │   ├── LoadEnvironmentVariables.php
│   │   ├── LoadConfiguration.php
│   │   ├── HandleExceptions.php
│   │   ├── RegisterFacades.php
│   │   ├── RegisterProviders.php
│   │   ├── DiscoverModules.php
│   │   └── BootProviders.php
│   ├── Container/                        ← پایهٔ کانتینر (Impl در engines/container)
│   │   ├── Binding.php
│   │   ├── Lifetime.php
│   │   └── ContainerException.php
│   ├── Context/
│   │   ├── Context.php                   ← Fiber/Coroutine-aware
│   │   ├── ContextStore.php
│   │   ├── RequestStack.php
│   │   ├── CorrelationContext.php
│   │   └── TraceContext.php
│   ├── Contracts/                        ← 🔑 همهٔ Interfaceها (DOC-014)
│   │   ├── Container/
│   │   ├── Config/
│   │   ├── Events/
│   │   ├── Http/
│   │   ├── Routing/
│   │   ├── Database/
│   │   ├── ORM/
│   │   ├── Validation/
│   │   ├── Cache/
│   │   ├── Queue/
│   │   ├── Auth/
│   │   ├── Authorization/
│   │   ├── Filesystem/
│   │   ├── Log/
│   │   ├── Mail/
│   │   ├── Notification/
│   │   ├── I18n/
│   │   ├── Encryption/
│   │   ├── Console/
│   │   ├── Observability/
│   │   ├── Workflow/
│   │   ├── Payment/
│   │   ├── Search/
│   │   └── Support/
│   ├── Error/
│   │   ├── EarlyErrorManager.php
│   │   ├── ErrorHandler.php
│   │   ├── ExceptionHandler.php
│   │   ├── ExceptionMapper.php
│   │   ├── RefCodeGenerator.php
│   │   ├── LogMasker.php
│   │   └── ErrorCode.php
│   ├── Exceptions/
│   │   ├── TavaanException.php
│   │   ├── RenderableException.php
│   │   ├── ContextualException.php
│   │   ├── ReportableException.php
│   │   └── RetryableException.php
│   ├── Events/
│   │   ├── Event.php                     ← Base class
│   │   ├── DomainEvent.php
│   │   └── FrameworkEvents/
│   ├── Facade/
│   │   ├── Facade.php
│   │   ├── FacadeSpy.php
│   │   └── Facades/                      ← Config, Cache, Event, Log, Auth, DB, View, Route, Lang, Queue, Session, Http, Storage, Mail, Gate, Schema, Validator
│   ├── Module/
│   │   ├── Module.php
│   │   ├── ModuleRegistry.php
│   │   ├── ModuleContext.php
│   │   ├── ManifestParser.php
│   │   ├── TopologicalSorter.php
│   │   └── ModuleNotFoundException.php
│   ├── Pipeline/
│   │   ├── Pipeline.php
│   │   └── PipelineInterface.php
│   ├── Provider/
│   │   ├── ServiceProvider.php
│   │   └── DeferrableProvider.php
│   ├── Support/                          ← (Tavaan\Support\)
│   │   ├── Arr.php
│   │   ├── Str.php
│   │   ├── Number.php
│   │   ├── Persian.php                   ← ارقام فارسی، ی/ک
│   │   ├── Collection.php
│   │   ├── LazyCollection.php
│   │   ├── HigherOrderCollection.php
│   │   ├── ValueObject/
│   │   │   ├── Money.php
│   │   │   ├── Currency.php
│   │   │   ├── Email.php
│   │   │   ├── Mobile.php
│   │   │   ├── NationalId.php
│   │   │   ├── PostalCode.php
│   │   │   ├── DateRange.php
│   │   │   └── Percentage.php
│   │   ├── Math/
│   │   │   ├── Decimal.php               ← بدون bcmath
│   │   │   └── RoundingMode.php
│   │   ├── Id/
│   │   │   ├── Ulid.php
│   │   │   ├── Snowflake.php
│   │   │   └── Uuid.php                  ← v4/v7 خودنوشته
│   │   ├── Time/
│   │   │   ├── Clock.php
│   │   │   ├── Stopwatch.php
│   │   │   └── DateHelper.php
│   │   ├── Enum/
│   │   │   ├── EnumHelpers.php
│   │   │   └── TranslatableEnum.php
│   │   ├── Attributes/                   ← همهٔ Attributeها
│   │   │   ├── Route.php
│   │   │   ├── Middleware.php
│   │   │   ├── Validate.php
│   │   │   ├── Policy.php
│   │   │   ├── Cacheable.php
│   │   │   ├── Transactional.php
│   │   │   ├── Observed.php
│   │   │   ├── Singleton.php
│   │   │   ├── Column.php
│   │   │   └── Relation.php
│   │   └── helpers.php                    ← توابع سراسری (محدود و کنترل‌شده)
│   └── Kernel/
│       ├── KernelInterface.php
│       ├── HttpKernel.php                 ← Impl در engines/http
│       └── ConsoleKernel.php
│
├── engines/                              ← ⚙️ ۵۳ موتور خودنوشته (Tavaan\Engine\<X>\)
│   ├── README.md                         ← رجیستری موتورها
│   ├── autoloader/        (اختیاری؛ پایه در core)
│   ├── container/
│   │   ├── src/
│   │   │   ├── Container.php
│   │   │   ├── ContainerServiceProvider.php
│   │   │   ├── ContextualBindingBuilder.php
│   │   │   ├── LazyProxy.php
│   │   │   ├── ReflectionCache.php
│   │   │   ├── CompiledContainer.php
│   │   │   └── Exceptions/
│   │   ├── config/container.php
│   │   ├── tests/
│   │   ├── benchmarks/
│   │   ├── docs/README.md
│   │   ├── composer.json                 ← اختیاری (فقط انتشار)
│   │   └── module.json                   ← manifest موتور
│   ├── config/
│   ├── event/
│   ├── pipeline/
│   ├── error/
│   ├── log/
│   ├── cache/
│   ├── filesystem/
│   ├── http/
│   │   ├── src/
│   │   │   ├── HttpKernel.php
│   │   │   ├── HttpServiceProvider.php
│   │   │   ├── Request.php
│   │   │   ├── Response.php
│   │   │   ├── Stream.php
│   │   │   ├── Uri.php
│   │   │   ├── HeaderBag.php
│   │   │   ├── ParameterBag.php
│   │   │   ├── FileBag.php
│   │   │   ├── CookieBag.php
│   │   │   ├── UploadedFile.php
│   │   │   ├── ResponseFactory.php
│   │   │   ├── Redirector.php
│   │   │   ├── Middleware/
│   │   │   │   ├── TrustProxiesMiddleware.php
│   │   │   │   ├── SecurityHeadersMiddleware.php
│   │   │   │   ├── CorsMiddleware.php
│   │   │   │   ├── CsrfVerifyMiddleware.php
│   │   │   │   ├── StartSessionMiddleware.php
│   │   │   │   ├── RateLimitMiddleware.php
│   │   │   │   ├── MaintenanceModeMiddleware.php
│   │   │   │   ├── SetLocaleMiddleware.php
│   │   │   │   └── TraceContextMiddleware.php
│   │   │   ├── Routing/
│   │   │   │   ├── Router.php
│   │   │   │   ├── Route.php
│   │   │   │   ├── RouteGroup.php
│   │   │   │   ├── RouteCollection.php
│   │   │   │   ├── RouteCompiler.php
│   │   │   │   ├── CompiledRouteCollection.php
│   │   │   │   ├── RouteMatcher.php
│   │   │   │   ├── UrlGenerator.php
│   │   │   │   ├── ControllerResolver.php
│   │   │   │   ├── ModelBinder.php
│   │   │   │   └── Exceptions/
│   │   │   └── Server/
│   │   │       ├── FpmServer.php
│   │   │       ├── SwooleServer.php
│   │   │       └── FrankenPhpServer.php
│   │   ├── config/http.php
│   │   ├── tests/
│   │   └── module.json
│   ├── session/
│   ├── router/            (در صورت تفکیک از http)
│   ├── view/
│   ├── database/
│   ├── orm/
│   ├── migration/
│   ├── schema/
│   ├── validation/
│   ├── crypt/
│   ├── hash/
│   ├── auth/
│   ├── rbac/
│   ├── audit/
│   ├── queue/
│   ├── schedule/
│   ├── console/
│   ├── i18n/
│   ├── jalali/
│   ├── media/
│   ├── mail/
│   ├── notification/
│   ├── http-client/
│   ├── api/
│   ├── observability/
│   ├── search/
│   ├── workflow/
│   ├── report/
│   ├── payment/
│   ├── feature-flag/
│   ├── realtime/
│   ├── admin/
│   ├── plugin/
│   ├── scaffold/
│   ├── test/
│   ├── backup/
│   ├── optimizer/
│   ├── upgrade/
│   ├── tenancy/
│   ├── module/
│   └── compat/            ← Polyfillهای نسخه‌های بالاتر PHP
│
├── config/                                 ← ⚙️ پیکربندی سراسری
│   ├── app.php
│   ├── autoload.php
│   ├── cache.php
│   ├── console.php
│   ├── database.php
│   ├── event.php
│   ├── filesystem.php
│   ├── http.php
│   ├── log.php
│   ├── mail.php
│   ├── modules.php
│   ├── observability.php
│   ├── providers.php
│   ├── queue.php
│   ├── security.php
│   ├── session.php
│   ├── tenancy.php
│   ├── view.php
│   ├── architecture.php                   ← قوانین arch:check
│   └── schema/                            ← Schema اعتبارسنجی Config
│       ├── app.schema.php
│       └── …
│
├── app/                                    ← 📦 اپلیکیشن اصلی (App\)
│   ├── src/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Middleware/
│   │   │   ├── Requests/
│   │   │   ├── Resources/
│   │   │   └── Widgets/
│   │   ├── Console/Commands/
│   │   ├── Application/
│   │   │   ├── UseCases/
│   │   │   ├── Commands/
│   │   │   ├── Queries/
│   │   │   └── Dtos/
│   │   ├── Domain/
│   │   │   ├── Entities/
│   │   │   ├── ValueObjects/
│   │   │   ├── Services/
│   │   │   ├── Events/
│   │   │   └── Repositories/
│   │   ├── Infrastructure/
│   │   │   ├── Persistence/
│   │   │   └── Gateways/
│   │   ├── Providers/
│   │   │   ├── AppServiceProvider.php
│   │   │   ├── RouteServiceProvider.php
│   │   │   └── EventServiceProvider.php
│   │   ├── Policies/
│   │   └── Support/
│   ├── config/
│   ├── routes/
│   │   ├── web.php
│   │   ├── api.php
│   │   ├── admin.php
│   │   └── console.php
│   ├── views/
│   │   ├── layouts/
│   │   ├── components/
│   │   ├── partials/
│   │   └── errors/
│   └── lang/{fa,en}/
│
├── modules/                                ← 🧩 ماژول‌های HMVC (Modules\<X>\)
│   ├── core-user/                          ← ماژول پایه: کاربران/نقش‌ها
│   ├── blog/
│   ├── shop/
│   ├── forum/
│   ├── lms/
│   ├── crm/
│   ├── erp/
│   └── bpms/
│
├── kits/                                   ← 🎁 کیت‌های اپلیکیشنی
│   ├── blog/kit.json
│   ├── shop/kit.json
│   ├── lms/kit.json
│   └── erp/kit.json
│
├── plugins/                                ← 🔌 پلاگین‌های ثالث (معاف از صفر‌وابستگی)
│   ├── .gitkeep
│   ├── plugins.lock                        ← hash + signature
│   └── <plugin>/
│       ├── plugin.json
│       ├── src/
│       ├── vendor/                         ← ⚠️ فقط اینجا مجاز
│       └── …
│
├── database/                               ← 🗄️ منابع دادهٔ سراسری
│   ├── migrations/                         ← migrationهای هسته (users, jobs, cache, sessions, outbox, inbox)
│   │   ├── 2026_01_01_000001_create_users_table.php
│   │   ├── 2026_01_01_000002_create_sessions_table.php
│   │   ├── 2026_01_01_000003_create_jobs_table.php
│   │   ├── 2026_01_01_000004_create_failed_jobs_table.php
│   │   ├── 2026_01_01_000005_create_cache_table.php
│   │   ├── 2026_01_01_000006_create_outbox_table.php
│   │   ├── 2026_01_01_000007_create_inbox_table.php
│   │   ├── 2026_01_01_000008_create_audit_logs_table.php
│   │   └── 2026_01_01_000009_create_rbac_tables.php
│   ├── seeders/
│   ├── factories/
│   └── schema/                             ← snapshot schema (برای diff)
│
├── routes/                                 ← 🛣️ روت‌های سراسری (بدون ماژول)
│   ├── web.php
│   ├── api.php
│   ├── channels.php                        ← Realtime
│   └── console.php
│
├── resources/                              ← 🎨 منابع خام
│   ├── views/                              ← Viewهای سراسری
│   ├── lang/{fa,en}/
│   ├── css/
│   ├── js/
│   ├── fonts/
│   ├── images/
│   └── stubs/                              ← قالب‌های scaffold
│       ├── controller.stub
│       ├── model.stub
│       ├── module.stub
│       ├── engine.stub
│       ├── migration-create.stub
│       ├── test.stub
│       └── …
│
├── storage/                                ← 💾 دادهٔ زمان اجرا (قابل نوشتن)
│   ├── app/
│   │   ├── public/
│   │   ├── private/
│   │   └── tenants/<id>/
│   ├── cache/
│   │   ├── config.php
│   │   ├── routes.php
│   │   ├── classmap.php
│   │   ├── views/
│   │   ├── events.php
│   │   ├── container.php
│   │   ├── modules.php
│   │   ├── preload.php
│   │   └── data/                           ← FileCache driver
│   ├── framework/
│   │   ├── sessions/
│   │   ├── views/                          ← Viewهای کامپایل‌شده
│   │   ├── testing/
│   │   └── down                            ← فایل حالت تعمیر
│   ├── logs/
│   │   ├── tavaan-2026-10-15.log
│   │   ├── audit.log
│   │   ├── query.log
│   │   └── queue.log
│   ├── backups/
│   ├── temp/
│   └── .gitignore
│
├── tests/                                  ← 🧪 تست‌های سراسری
│   ├── bootstrap.php                       ← بوت تست (بدون phpunit autoload)
│   ├── Unit/
│   ├── Feature/
│   ├── Integration/
│   ├── Benchmark/
│   ├── Architecture/
│   ├── Security/
│   ├── Contract/
│   └── Fixtures/
│
├── docs/                                   ← 📚 همین مستندات
├── deploy/                                 ← 🚀 استقرار
│   ├── docker/
│   │   ├── Dockerfile
│   │   ├── Dockerfile.swoole
│   │   └── docker-compose.yml
│   ├── nginx/tavaan.conf
│   ├── apache/tavaan.conf
│   ├── php/
│   │   ├── php.ini
│   │   ├── php-fpm.conf
│   │   └── opcache-preload.ini
│   ├── k8s/
│   ├── systemd/tavaan-worker.service
│   └── cron/tavaan-scheduler
│
├── tools/                                  ← 🛠️ ابزارهای dev (اختیاری، خارج از هسته)
│   ├── phpstan/
│   ├── cs-fixer/
│   ├── infectious/
│   └── scripts/
│       ├── verify-docs.py
│       ├── check-deps.sh
│       └── bench.sh
│
├── examples/                               ← 💡 نمونه‌های اجراشدنی
│   ├── hello-world/
│   ├── blog-minimal/
│   ├── api-only/
│   └── microservice/
│
├── .env.example
├── .env.testing
├── .gitignore
├── .editorconfig
├── .php-cs-fixer.dist.php                  ← dev only
├── phpstan.neon.dist                       ← dev only
├── composer.json                           ← فقط autoload-dev (اختیاری)
├── tvaan.json                              ← 📄 manifest اصلی پروژه
├── LICENSE
├── CHANGELOG.md
├── CONTRIBUTING.md
├── SECURITY.md
└── README.md
```

---

## ۲. توضیح هر پوشه (مسئولیت و قواعد)

| پوشه | مسئولیت | نوشتن در زمان اجرا؟ | عمومی وب؟ | قاعده |
|---|---|---|---|---|
| `bin/` | Entry Pointهای CLI | ❌ | ❌ | فقط bootstrap + dispatch |
| `public/` | Entry HTTP + asset | ✅ (uploads) | ✅ | 🔒 هیچ فایل PHP جز `index.php` |
| `core/` | هستهٔ صفر‌وابستگی | ❌ | ❌ | 🔒 بدون وابستگی به engines/modules |
| `engines/` | ۵۳ موتور | ❌ | ❌ | هر موتور خودبسنده با Contract |
| `config/` | پیکربندی | ❌ | ❌ | فقط `return []` |
| `app/` | اپلیکیشن اصلی | ❌ | ❌ | کد کاربر |
| `modules/` | ماژول‌های HMVC | ❌ | ❌ | مرز سخت (DOC-022) |
| `kits/` | کیت‌های آماده | ❌ | ❌ | manifest + modules |
| `plugins/` | افزونهٔ ثالث | ❌ | ❌ | ⚠️ تنها محل مجاز `vendor/` |
| `database/` | migration/seed سراسری | ❌ | ❌ | — |
| `routes/` | روت‌های سراسری | ❌ | ❌ | فقط تعریف روت |
| `resources/` | منابع خام | ❌ | ❌ | build → `public/assets` |
| `storage/` | دادهٔ زمان اجرا | ✅ | ❌ | 🔒 باید `775` و غیرقابل مرور |
| `tests/` | تست | ✅ (temp) | ❌ | — |
| `docs/` | مستندات | ❌ | ❌ | — |
| `deploy/` | پیکربندی استقرار | ❌ | ❌ | — |
| `tools/` | ابزار dev | ❌ | ❌ | ⚠️ در PROD نصب نشود |
| `examples/` | نمونه | ❌ | ❌ | اجراشدنی |

---

## ۳. فایل `tavaan.json` (Project Manifest)

```json
{
  "$schema": "https://docs.tavaan.dev/schemas/project.v1.json",
  "name": "my-shop",
  "title_fa": "فروشگاه من",
  "version": "1.0.0",
  "tavaan": "^1.0",
  "php": ">=8.2.0",
  "environment": {
    "default": "production",
    "debug": false,
    "timezone": "Asia/Tehran",
    "locale": "fa",
    "fallback_locale": "en"
  },
  "architecture": "hexagonal",
  "paths": {
    "core": "core",
    "engines": "engines",
    "config": "config",
    "app": "app",
    "modules": "modules",
    "kits": "kits",
    "plugins": "plugins",
    "storage": "storage",
    "public": "public",
    "views": "resources/views",
    "lang": "resources/lang",
    "routes": "routes"
  },
  "engines": {
    "required": ["container","config","event","error","log","http","router","view",
                 "database","orm","migration","validation","auth","rbac","cache",
                 "session","console","i18n","filesystem","queue"],
    "optional": ["media","mail","notification","payment","search","workflow",
                 "report","audit","observability","realtime","admin","api","backup"]
  },
  "modules": {
    "enabled": ["tavaan.core-user","tavaan.shop","tavaan.blog"],
    "disabled": ["tavaan.forum"]
  },
  "kits": ["tavaan.kit.shop"],
  "providers": "config/providers.php",
  "scripts": {
    "post-install": ["tvn key:generate", "tvn migrate --force", "tvn optimize"],
    "post-update":  ["tvn optimize:clear", "tvn optimize"],
    "dev":          ["tvn serve --host=127.0.0.1 --port=8000"]
  },
  "build": {
    "assets": {"input": "resources", "output": "public/assets", "hash": true},
    "optimizer": ["config","routes","autoload","views","events"]
  },
  "security": {
    "plugins_signature_required": true,
    "allowed_plugin_vendors": ["tavaan"]
  }
}
```

---

## ۴. ساختار یک موتور (الگوی مرجع)

```
engines/<name>/
├── module.json                  ← manifest موتور (id, version, deps, ext)
├── composer.json                ← اختیاری؛ فقط برای انتشار (بدون require)
├── README.md                    ← راهنمای کوتاه
├── LICENSE
├── src/
│   ├── <Name>ServiceProvider.php    ← 🔑 نقطهٔ ثبت
│   ├── <Name>Engine.php             ← نقطهٔ ورود عمومی (Facade root)
│   ├── <Name>Manager.php            ← مدیر درایورها (در صورت وجود)
│   ├── Contracts/                   ← اگر قرارداد اختصاصی دارد
│   ├── Drivers/                     ← پیاده‌سازی‌های مختلف
│   ├── Support/
│   ├── Exceptions/
│   └── Attributes/
├── config/<name>.php            ← تنظیمات پیش‌فرض
├── lang/{fa,en}/<name>.php      ← پیام‌ها
├── migrations/                  ← جداول موردنیاز موتور
├── views/                       ← اگر UI دارد (مثل admin)
├── routes/                      ← اگر endpoint دارد (مثل health)
├── tests/
│   ├── Unit/
│   ├── Feature/
│   ├── Benchmark/
│   └── Fixtures/
├── benchmarks/
│   └── <name>.bench.php
├── docs/
│   ├── README.md
│   └── link → ../../docs/03-engines/<name>.md
└── examples/
    └── basic.php
```

### ۴٫۱ `module.json` یک موتور
```json
{
  "id": "tavaan.engine.cache",
  "type": "engine",
  "name": "Cache",
  "title_fa": "حافظهٔ نهان",
  "version": "1.0.0",
  "namespace": "Tavaan\\Engine\\Cache\\",
  "autoload": {"psr-4": {"Tavaan\\Engine\\Cache\\": "src/"}},
  "providers": ["Tavaan\\Engine\\Cache\\CacheServiceProvider"],
  "requires": {"php": ">=8.2", "engines": ["config","container","event"]},
  "optional_extensions": [
    {"name": "redis",   "purpose": "Redis driver", "fallback": "file"},
    {"name": "apcu",    "purpose": "APCu driver",  "fallback": "file"},
    {"name": "memcached","purpose": "Memcached driver", "fallback": "file"}
  ],
  "config": {"file": "config/cache.php", "key": "cache"},
  "tables": ["cache"],
  "commands": ["cache:clear","cache:stats","cache:tag-clear"],
  "facade": {"name": "Cache", "accessor": "cache"},
  "docs": "docs/03-engines/cache.md",
  "benchmarks": ["benchmarks/cache.bench.php"]
}
```

---

## ۵. ساختار ماژول دامنه‌ای (ارجاع به DOC-022 §2)

→ جزئیات کامل در `docs/02-architecture/modular-monolith.md`

---

## ۶. ساختار View

```
resources/views/                ← سراسری (namespace: پیش‌فرض)
├── layouts/
│   ├── app.tv.php              ← Layout اصلی
│   ├── admin.tv.php
│   ├── auth.tv.php
│   └── print.tv.php
├── components/
│   ├── alert.tv.php
│   ├── button.tv.php
│   ├── modal.tv.php
│   ├── table.tv.php
│   ├── pagination.tv.php
│   └── form/
│       ├── input.tv.php
│       ├── select.tv.php
│       ├── textarea.tv.php
│       └── file.tv.php
├── partials/
│   ├── header.tv.php
│   ├── footer.tv.php
│   ├── sidebar.tv.php
│   └── nav.tv.php
├── errors/
│   ├── 403.tv.php
│   ├── 404.tv.php
│   ├── 419.tv.php
│   ├── 429.tv.php
│   ├── 500.tv.php
│   └── layout.tv.php
└── vendor/                     ← Viewهای override شدهٔ موتور/ماژول

modules/shop/views/             ← namespace: shop::
├── admin/product/index.tv.php
├── admin/product/edit.tv.php
├── front/product/show.tv.php
├── widgets/recent-orders.tv.php
└── mails/invoice.tv.php
```

**Override سلسله‌مراتبی (View Resolution Order):**
```
1. resources/views/vendor/<module>/<path>.tv.php     ← override کاربر
2. modules/<module>/views/<path>.tv.php              ← خود ماژول
3. engines/<engine>/views/<path>.tv.php              ← موتور
4. resources/views/<path>.tv.php                     ← سراسری
```

---

## ۷. ساختار Storage و مجوزها

```
storage/                 0775  (owner: www-data, group: www-data)
├── app/                 0775
│   ├── public/          0775   ← symlink از public/storage
│   ├── private/         0750   🔒 خارج از وب
│   └── tenants/<id>/    0750
├── cache/data/          0775
├── framework/sessions/  0775   (۰۶۰۰ برای فایل‌ها)
├── framework/views/     0775
├── logs/                0775   (فایل‌ها ۰۶۴۰)
├── backups/             0750   🔒 رمزنگاری‌شده
└── temp/                0775   (با پاکسازی خودکار)
```

🔒 `storage/` و `config/` و `core/` **هرگز** نباید از وب قابل دسترس باشند.
🔒 `.gitignore` برای `storage/*` به‌جز `.gitignore` خود.

---

## ۸. قواعد مکان‌یابی فایل (Decision Tree)

```
سؤال: این فایل چیست؟

├─ یک کلاس از هسته (بدون وابستگی به موتور)؟      → core/<Area>/<Class>.php
├─ یک کلاس از یک موتور؟                          → engines/<engine>/src/<Class>.php
├─ یک Interface عمومی؟                           → core/Contracts/<Area>/<Name>Interface.php
├─ یک Value Object / Helper مشترک؟               → core/Support/<Area>/<Class>.php
├─ یک Attribute؟                                 → core/Support/Attributes/<Name>.php
├─ یک Exception؟
│   ├─ هسته                                      → core/Exceptions/
│   ├─ موتور                                     → engines/<engine>/src/Exceptions/
│   └─ ماژول                                     → modules/<m>/src/Domain/Exceptions/
├─ Controller؟                                   → <module>/src/Http/Controllers/
├─ UseCase/Handler؟                              → <module>/src/Application/
├─ Entity/VO/DomainService؟                      → <module>/src/Domain/
├─ Repository Impl / Gateway؟                    → <module>/src/Infrastructure/
├─ Migration؟
│   ├─ هسته/موتور                                → database/migrations/ یا engines/<e>/migrations/
│   └─ ماژول                                     → modules/<m>/migrations/
├─ View؟                                         → resources/views/ یا modules/<m>/views/
├─ ترجمه؟                                        → resources/lang/<locale>/ یا modules/<m>/lang/<locale>/
├─ Config؟                                       → config/<name>.php یا engines/<e>/config/
├─ Route؟                                        → routes/ یا modules/<m>/routes/
├─ Asset خام؟                                    → resources/{css,js,images,fonts}/
├─ Asset build شده؟                              → public/assets/
├─ فایل آپلود کاربر؟                             → storage/app/{public,private}/
├─ تست؟                                          → tests/ یا engines/<e>/tests/ یا modules/<m>/tests/
├─ سند؟                                          → docs/<section>/<name>.md
├─ اسکریپت استقرار؟                              → deploy/
└─ نمونه/دمو؟                                    → examples/
```

---

## ۹. قواعد `.gitignore`

```gitignore
# Environment
.env
.env.local
.env.*.local
!.env.example
!.env.testing

# Runtime
/storage/*
!/storage/
!/storage/**/.gitignore
/storage/cache/*
/storage/logs/*
/storage/framework/sessions/*
/storage/framework/views/*
/storage/app/private/*
/storage/backups/*
/storage/temp/*

# Public uploads (در صورت عدم استفاده از VCS برای media)
/public/uploads/*
!/public/uploads/.gitignore

# Build
/public/assets/*
!/public/assets/.gitignore
/node_modules/

# Tools & dev
/vendor/
/tools/vendor/
.phpunit.result.cache
.php-cs-fixer.cache
composer.lock        # ⚠️ چون وابستگی نداریم، اختیاری

# IDE/OS
/.idea/
/.vscode/
*.swp
.DS_Store
Thumbs.db
```

---

## ۱۰. معیار پذیرش 🧪

- AC-STR-01: نصب تازه با کپی مخزن و اجرای `tvn key:generate && tvn migrate && tvn serve` کار می‌کند.
- AC-STR-02: `tvn about` ساختار کامل و وضعیت هر پوشه را نمایش می‌دهد.
- AC-STR-03: هیچ فایل PHP اجرایی در `public/` جز `index.php` وجود ندارد (تست خودکار).
- AC-STR-04: `storage/` از وب قابل دسترسی نیست (با `.htaccess`/Nginx rule + تست).
- AC-STR-05: `tvn arch:check` ساختار پوشه را با `tavaan.json` مطابقت می‌دهد.
- AC-STR-06: هر موتور دارای `module.json` معتبر است (`tvn engine:validate`).
- AC-STR-07: حذف یک موتور اختیاری، پروژه را نمی‌شکند (فقط قابلیت مربوطه غیرفعال).
