# گزارش پیاده‌سازی M9 — گام ۱: زیرساخت کیت‌ها + کیت وبلاگ ✍️

**تاریخ:** ۲۰۲۶-۱۰-۲۴ • **فاز:** M9 (DOC-120) • **تیکت:** TVN-0091 (Kit: Blog — DOC-091)
**نتیجه:** `۱۳۴۲ تست / ۸۷۴۹ assertion سبز` (افزایش از ۱۲۵۲/۸۳۵۱ در پایان M8) • ۵۷ manifest فعال (۵۲ موتور + ۵ ماژول) • arch:check سبز (۱۱۲۷ فایل) • security:scan بدون یافتهٔ جدید • lint ۱۵۲۴ فایل پاک

---

## ۱. دامنهٔ این گام

طبق DOC-121 §7 (الگوریتم انتخاب تیکت)، اولین تیکت آمادهٔ M9 = **TVN-0091 (Kit: Blog)**.
خروجی‌ها:

| # | خروجی | محل |
|---|---|---|
| 1 | **زیرساخت کیت** — KitManifest/KitManager/KitException + `tvn kit:list` + `tvn kit:install` (زنجیرهٔ ۸ گامی DOC-090 §8، idempotent) | `engines/module/src/Kit/`، `engines/module/src/Console/Kit*Command.php` |
| 2 | **ثبت Provider ماژول‌ها در بوت** — حلقهٔ مفقودهٔ DOC-022 §4 گام ۴ (تا M8 کسی manifest.providers ماژول‌ها را ثبت نمی‌کرد) | `core/Bootstrap/DiscoverModules.php` |
| 3 | **ماژول `tavaan.content`** — ContentStatus، Slug فارسی‌اول، SeoMetadata/OG، Markdown امن (escape-first)، JalaliMonth (آرشیو)، FeedBuilder (RSS/Atom)، SitemapBuilder، ExcerptGenerator | `modules/content/` |
| 4 | **ماژول `tavaan.comment`** — Comment entity، CommentableInterface + CommentStoreInterface (پورت)، CommentModerator، CommentThreadBuilder (تو‌در‌تو + یتیم→ریشه + سقف عمق) | `modules/comment/` |
| 5 | **ماژول `tavaan.media`** — MediaAttachableInterface، MediaGatewayInterface + آداپتر Facade، MediaFile DTO | `modules/media/` |
| 6 | **ماژول `tavaan.core-user`** — جدول `users`، User (Authenticatable+Notifiable)، UserReaderInterface/UserSnapshot، DatabaseUserRepository، `tvn user:create`، seeder دمو | `modules/core-user/` |
| 7 | **ماژول `tavaan.blog`** — کل دامنهٔ DOC-091: Post/Category/Tag، ۵ UseCase، ۴ مخزن، ۴+۲ رویداد، PostPolicy، ۹ کنترلر (عمومی+ادمین)، ویوهای RTL، آرشیو جلالی، فید، sitemap، ایندکس جست‌وجو، اعلان‌ها، `tvn blog:publish-scheduled`، مهاجرت ۵ جدول، seed دمو | `modules/blog/` |
| 8 | **کیت `tavaan.kit.blog`** — kit.json، ۳ preset (minimal/standard/enterprise)، theme پیش‌فرض RTL (Vazirmatn + CSS logical properties)، seed/roles.json (reader/author/editor/admin)، demo، install/steps.json، راهنمای کاربر/ادمین | `kits/blog/` |
| 9 | **تست‌ها** — ۹۱ تست جدید: Unit (content 28، comment 12، blog entity 12، media 1) + Feature (core-user 9، blog E2E 14، kit 8، module 7، view 2 رگرسیون) | `modules/*/tests/`، `engines/*/tests/` |
| 10 | **مستندات** — DOC-091 §9 (وضعیت)، DOC-038 (بخش کیت‌ها)، DOC-057/066/043 (افزودنی‌های M9)، `docs/schemas/kit.v1.json`، ۵ README ماژول، README+docs کیت، بک‌لاگ (TVN-0091 → done) | — |

## ۲. معیارهای پذیرش

### AC-BLOG (DOC-091 §8)
| AC | وضعیت |
|---|---|
| AC-BLOG-01: سناریوهای E2E §7 سبز | ✅ E2E-01..05 در `BlogE2ETest` (نویسنده→editor→انتشار، نظر→moderation→نمایش، جست‌وجوی فارسی، RSS معتبر با simplexml، آرشیو جلالی ۱۴۰۳/۰۷ با ارقام لاتین و فارسی) |
| AC-BLOG-02: SEO (meta/sitemap) کامل | ✅ meta description/keywords + Open Graph در صفحهٔ مقاله + `GET /blog/sitemap.xml` (XML معتبر) |
| AC-BLOG-03: XSS در نظرات/بدنه دفع می‌شود | ✅ نظرات: escape در ویو (تست `testE2E02b`) • بدنه: Markdown escape-first + `Security::sanitize` (۸ بردار XSS در `ContentCoreTest`) |
| AC-BLOG-04: RSS با فرمت معتبر | ✅ RSS 2.0 + Atom 1.0 — parse با simplexml، pubDate RFC-822، guid isPermaLink=false، escape کامل |

### AC-APP (DOC-090 §9)
| AC | وضعیت |
|---|---|
| AC-APP-01: `tvn kit:install` اپلیکیشن کاربردی می‌سازد | ✅ نصب از DB خالی: ۱۶ migration + ۴ نقش + ۱۲ مجوز + ۶ کاربر + ۴ مقاله + ۲ نظر؛ همهٔ مسیرها ۲۰۰ (HTTP واقعی با `php -S` راستی‌آزمایی شد) |
| AC-APP-02: سازگاری arch:check | ✅ صفر نقض در ۱۱۲۷ فایل — مرز ماژول‌ها فقط Core Contracts/Facades/Support + Contracts هم‌خانواده |
| AC-APP-03: قراردادهای مشترک رعایت می‌شود | ✅ UserReaderInterface (core-user→blog)، CommentableInterface/CommentStoreInterface (comment→blog)، MediaAttachableInterface/MediaGatewayInterface (media→blog) |
| AC-APP-04: override پوسته بدون تغییر کد ماژول | ✅ مکانیزم استاندارد `resources/views/vendor/blog` (اولویت اول ViewFinder) — کپی خودکار توسط kit:install؛ theme کیت فعال و راستی‌آزمایی شد |
| AC-APP-05: نصب با demo ≤ ۵ دقیقه | ✅ کل زنجیرهٔ نصب < ۱ ثانیه (SQLite) |

## ۳. باگ‌های نهفتهٔ کشف‌شده و رفع‌شده (حین M9)

| # | باگ | ریشه | رفع |
|---|---|---|---|
| 1 | `@include` درون `@section` کل خروجی صفحه را می‌خورد (۱ بایت!) | `ViewFactory::renderInclude` از چرخهٔ `View::render` عبور می‌کرد و `popExtends()` قالب والد را از پشتهٔ مشترک مصرف می‌کرد | پشتهٔ extends مستقل برای include (save/restore) + ۲ تست رگرسیون |
| 2 | چرخهٔ وابستگی کاذب `tavaan.media` | `sorted()` با شناسهٔ **کوتاه** کلید می‌خورد → موتور media و ماژول tavaan.media روی `media` برخورد + `requires.engines:['media']` خودیال می‌شد | کلید کامل در sorted() + resolve سه‌مرحله‌ای dep (کلید دقیق → tavaan.engine.* → کوتاه) + first-wins برای shortIds + ۳ تست رگرسیون |
| 3 | `requires.modules` در مرتب‌سازی توپولوژیک نادیده بود | `Module::dependencies()` فقط engines برمی‌گرداند | ادغام engines+modules در dependencies() + تطبیق کوتاه/کامل در dependentsOf |
| 4 | `rbac:sync` قالب schema مجوزها (`{group,items}`) را نمی‌خواند | `PermissionSync::readManifest` فقط فهرست مسطح | پشتیبانی هر دو قالب + انتقال title_fa/en به createPermission |
| 5 | ۴۰۳ مجوز در تست‌ها با نقش‌های صحیح | `Gate::policy` در boot ماژول → ساخت زودهنگام PermissionResolver با اتصال پیش‌فرض (قبل از سوییچ RefreshDatabase به sqlite_memory) | **policies اعلامی در config** (`rbac.policies`) — Gate در اولین مصرف ساخته می‌شود |
| 6 | مسیرهای percent-encoded در تست درون‌فرایندی ۴۰۴ | `Request::create` برخلاف `fromGlobals` مسیر را decode نمی‌کرد | decode + double-encoding guard هم‌راستا با fromGlobals (پاریتی تست/production) |
| 7 | overlay کیت زیر کلید اشتباه merge می‌شد | `ConfigLoader` namespace را از **نام فایل** می‌گیرد (`kits.generated.php` → `kits.generated.*`) | نوشتن overlay به‌تفکیک namespace (`config/blog.php` و …) + مسیر theme نسبی (portable) |
| 8 | `KitManager` در محیط بدون سرویس‌ها می‌ترکید | `Container::bound()` برای هر کلاس موجود true است (auto-wiring) → make() با پارامتر غیرقابل‌resolve خطا می‌داد | `optionalService()` با try/catch + بررسی instanceof |

## ۴. تصمیم‌های طراحی مهم

1. **مرز ماژول = فقط Core** — src ماژول‌ها هیچ ارجاعی به `Tavaan\Engine\*` ندارد (به‌جز SDK مجاز `Console\Command` که با `allow` صریح در config/architecture.php و مرز شناسه مستند شد). دسترسی به موتورها از راه: Facadeهای هسته، alias کانتینر (`response.factory`، `rbac.roles`، `notification.message`)، Contracts و رویدادها.
2. **Hexagonal واقعی** — هر ماژول پورت (Contracts) و آداپتر (Infrastructure) دارد؛ `blog_comments` با prefix ماژول blog و پیاده‌سازی `CommentStoreInterface` ماژول comment (بدون Join/FK بین‌ماژولی — DoD §3).
3. **رویدادهای نام‌محور با DTO تایپ‌دار** — کلاس‌های `Domain/Events` هم payload قراردادند (در manifest اعلام‌شده) هم تولیدکنندهٔ آرایهٔ dispatch (`eventName()`+`toPayload()`)؛ listenerها با قرارداد `($eventName, $payload)`.
4. **ArrayNotification** — اعلان آرایه‌محور در موتور notification + binding `notification.message` تا ماژول‌ها بدون کلاس اختصاصیِ موتور، mail/database/sms بفرستند.
5. **JalaliMonth در ماژول content** — بازپیاده‌سازی همان الگوریتم breaks-based موتور jalaali (مرز ماژول اجازهٔ ارجاع نمی‌دهد) با بردارهای تست تأییدشده از موتور مرجع (۱۴۰۲ عادی/۱۴۰۳ کبیسه/round-trip).
6. **Markdown امن by-design** — escape-first (کل ورودی پیش از تبدیل escape می‌شود) + allowlist لینک (http/https/mailto/tel/نسبی) — مکملِ `Security::sanitize` به‌عنوان لایهٔ دوم.
7. **theme = vendor override** — به‌جای مکانیزم جدید، از مکانیزم موجود ViewFinder (`resources/views/vendor/<ns>`) استفاده شد؛ kit:install ویوها/assetها را کپی می‌کند (idempotent، قابل حذف).
8. **overlay پیکربندی فایل‌محور** — preset در `config/<namespace>.php` نوشته می‌شود (سطح app در ConfigLoader) → در productionِ config-frozen هم بدون set() زمان-اجرا کار می‌کند.

## ۵. راستی‌آزمایی دستی (فراتر از تست)

```bash
php bin/tvn kit:install blog --with-demo   # از DB خالی — ۱۶ migration/۴ نقش/دمو
php -S 127.0.0.1:8899 -t public public/index.php
# GET /blog → 200 با theme کیت • /blog/khosh-amadid → 200
# /blog/category/amoozesh، /blog/tag/tavaan → 200
# /blog/archive/1405/8 و /blog/archive/۱۴۰۵/۰۸ → 200 (ارقام فارسی ✓)
# /blog/feed (RSS) و ?format=atom → 200 XML معتبر • /blog/sitemap.xml → 200
# /blog?q=توان → نتایج فارسی • /admin/blog/posts → 302 (auth) ✓
```

## ۶. محیط توسعه (بازتولیدپذیر)

```bash
sudo apt-get install -y php8.2-cli php8.2-sqlite3 php8.2-mbstring php8.2-xml php8.2-gd php8.2-curl php8.2-zip
cp .env.example .env
# توجه: TVN_DB_DATABASE=tavaan در .env.example مسیر نسبی sqlite است؛
# برای توسعه آن را به storage/database/tavaan.sqlite تغییر دهید.
php bin/tvn key:generate
php bin/tvn test          # 1342 سبز
php bin/tvn arch:check && php bin/tvn security:scan && php bin/tvn lint:php
```

## ۷. تغییرات موتورهای موجود (همه افزودنی/رفع باگ — بدون شکستن API)

| موتور | تغییر |
|---|---|
| module | `Kit/*` + دستورات kit:* + رفع برخورد شناسهٔ کوتاه + `moduleDependencies()` + ثبت Provider ماژول‌ها در DiscoverModules (core) |
| rbac | `policies` اعلامی در config + قالب object مجوزها در PermissionSync + aliasهای `rbac.*` |
| notification | `ArrayNotification` + binding `notification.message` |
| view | استقلال پشتهٔ extends در renderInclude (+۲ تست رگرسیون) |
| http | decode مسیر در `Request::create` (پاریتی با fromGlobals) |
| console | پشتیبانی `allow` (allowlist نقطه‌ای FQCN با مرز شناسه) در قوانین namespace — الگویی مشابه `forbidden_functions_allow` ممیزی M0–M8 |

## ۸. وضعیت پس از این گام

- فاز M9: **۱ از ۸ کیت** انجام شد (Blog). الگو/زیرساخت (content/comment/media/core-user + KitManager + theme/preset/roles/demo) برای ۷ کیت بعدی آماده است — هر کیت بعدی عمدتاً ماژول دامنهٔ اختصاصی + kit.json + theme خودش را نیاز دارد.
- تیکت بعدی طبق DOC-121 §7: **TVN-0092 (Kit: News Portal — DOC-092)**.
- بدهی‌های شناسایی‌شده: (۱) PHPStan/CS-Fixer بدون vendor قابل اجرا نیستند (مانند M8)؛ (۲) `wizard` نصب گرافیکی کیت پیاده نشد (در DOC-090 اختیاری است)؛ (۳) `.env.example` مسیر نسبی sqlite دارد (در §6 مستند شد).
