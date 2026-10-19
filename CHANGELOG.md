# Changelog — تَوان | Tavaan

فرمت: Keep a Changelog — نسخه‌گذاری: SemVer.

## [Unreleased] — M8: ابزار (TVN-0081 — موتور Upgrade) — ✅ فاز M8 کامل شد

### افزوده شد
- **موتور Upgrade (DOC-081E)**: جریان کامل ارتقا — `pre-check → backup اجباری → maintenance(down) → مسیر گام‌به‌گام نسخه‌ها → [schema migrations + data patches + config migrations + cache clear] → verify(health) → up` با **rollback خودکار** از backup در شکست میانی
- **DataPatch + PatchRunner**: patchهای `upgrades/<version>/patch_*.php` (anonymous class) — idempotent با جدول `tvn_upgrades` (اجرای مجدد → skip)؛ version داخل patch باید با دایرکتوری بخواند؛ خطای patch → `upgrade.patch_failed` با دلیل کامل
- **ConfigMigrator (§4)**: rename/remove/default/transform روی `.env` — ویرایش خط‌به‌خط (کامنت‌ها حفظ)، نوشتن اتمیک
- **VersionResolver (§5)**: semver-sort دایرکتوری‌های upgrades، مسیر **گام‌به‌گام** (پرش ممنوع)، downgrade → `upgrade.no_path`، نسخهٔ هدف بدون patch (schema-only) مجاز
- **PreChecker**: PHP min / افزونه‌ها / فضای آزاد دیسک → `upgrade.precheck_failed` با فهرست دلایل
- **CLI (§7)**: `upgrade --to=X.Y.Z [--no-backup --skip-maintenance]`، `upgrade:check [--to]`، `upgrade:rollback [--force]`، `upgrade:status [--to]`، `upgrade:patches`
- `UpgradeRepository` (tvn_upgrades + tvn_upgrade_meta با ساخت خودکار)، `BackupRunnerInterface` + adapter روی موتور Backup (تست‌پذیر با Mock)، maintenance با همان `storage/framework/down` موتور HTTP، `config/upgrade.php` + ترجمه‌های fa/en
- **۲۴ تست** (T-01..T-06): جریان کامل با BackupRunner موک‌شده (Mock موتور Test — dogfooding M8)، rollback خودکار در patch ناموفق، idempotency بین دو Upgrader، pre-check مسدودکننده، maintenance toggle، config migration واقعی روی .env

### نتایج
- ۱۲۳۵ تست / ۸۲۸۹ assertion سبز (دو اجرای پیاپی) • gate ممنوعه‌ها پاک • lint ۱۲۹۳ فایل پاک • **M8: ۵/۵ تیکت انجام‌شده** (TVN-0084 Test ✅ TVN-0077 Scaffold ✅ TVN-0079 Plugin ✅ TVN-0078 Admin ✅ TVN-0081 Upgrade ✅)

## [Unreleased] — M8: ابزار (TVN-0078 — موتور Admin)

### افزوده شد
- **موتور Admin (DOC-078)**: پیشخوان CRUD-generator — `Admin::resource(Model::class, fn(Resource $r) => …)` بدون کد اضافی ۱۰ route عمومی می‌سازد (AC-ADM-01): dashboard + index/create/store/show/edit/update/destroy + single/bulk actions
- **Form با هر ۲۷ نوع فیلد §6** (text…tags، شامل `jalali_date`، `richtext`، `relation`، `repeater`) + قواعد validation خودکار از تعریف فرم (required/nullable خودکار/in از options/regex جلالی/min/max) — validate روی ورودی خام سپس تبدیل (ترتیب درست برای regex جلالی)
- **امنیت (§7)**: همهٔ عملیات پشت `AuthenticateMiddleware` + `Gate` (`<perm>.view/create/update/delete` — AC-ADM-02)؛ whitelist فیلدهای فرم برای Mass Assignment؛ richtext با `HtmlSanitizer` پروفایل `rich_text` (T-04)؛ CSRF از middleware سراسری؛ guest→401/redirect و بدون‌مجوز→403
- **jalali_date دوطرفه (§6.1)**: فرم/نمایش جلالی ↔ DB میلادی UTC (T-07)
- **منوی پویا (§4)**: resourceها + `module.json → navigation` ماژول‌ها + `Admin::menu(...)` — فیلتر مجوز با Gate (T-05)
- **داشبورد (§5)**: `widget()` (هر Renderable — خطای ویجت ایزوله، داشبورد نمی‌شکند) + `chart()` با **SVG خودنوشته بدون JS** (line/bar — صفر‌وابستگی) (T-06)
- **RTL کامل (AC-ADM-03/T-08)**: layout `dir=rtl` `lang=fa` با فونت Vazirmatn+fallback و CSS درون‌خطی (بدون asset خارجی)
- **Theme override (AC-ADM-04)**: `resources/views/vendor/admin/` به‌لطف اولویت ViewFinder خودکار برنده است (+ تست)
- Actions تک/گروهی (`Action::make/bulk` + permission + handler + confirm + style؛ bulk delete پیش‌فرض)، search/filter/sort با whitelist، **pagination قطعی** (default order = کلید اصلی — بدون آن LIMIT/OFFSET در SQLite نامعین است)، slug خودکار از فیلد مبدأ، hash پسورد (خالی = دست‌نخورده)
- Facade `Admin` (accessor `admin`)، `config/admin.php` (prefix/guard/per_page/menu)، ۳۹ تست (Feature درون‌فرایندی کامل + Unit اجزا)

### اصلاح شد
- **🐛 باگ نهفتهٔ موتور View (M2)**: `ViewFactory` singleton بخش‌های `@section` را بین رندرهای متوالی انباشت می‌کرد (`stopSection` = append و بدون reset) → در فرایند مقیم/چنددرخواستی (تست‌ها، queue worker، websocket) محتوای رندر قبلی در رندر بعدی نشت می‌کرد. `beginViewRender()/endViewRender()` در مرز چرخهٔ رندر: reset sections/pushes/slots/extends + بستن بافرهای خروجیِ نشتی‌کرده از رندر ناموفق + ۲۵ تست View بدون رگرسیون سبز

### نتایج
- ۱۲۱۱ تست / ۸۲۱۷ assertion سبز (دو اجرای پیاپی) • gate ممنوعه‌ها پاک • lint ۱۲۷۴ فایل پاک

## [Unreleased] — M8: ابزار (TVN-0079 — موتور Plugin)

### افزوده شد
- **موتور Plugin (DOC-079)**: سیستم افزونهٔ ثالث با manifest (`plugin.json`)، امضای **Ed25519** (sodium) روی payload کانویک (ksort بازگشتی — `PluginVerifier::canonicalPayload` قرارداد مشترک با ابزار امضای فروشنده)، `trusted_vendors` در config
- **plugins.lock**: hash SHA-256 تک‌تک فایل‌ها؛ تشخیص modified/missing/unexpected (`plugin.lock_tampered`)؛ نوشتن اتمیک (tmp+rename+LOCK_EX)
- **PluginScanner (§5.1)**: اسکن توکنی eval/exec/system/passthru/shell_exec/popen/proc_open/assert/create_function/unserialize/file_get_contents/fopen/curl_exec + خانوادهٔ include/require — با رفع مثبت کاذب member-context (`->exec()`/`::system()`/تعریف متد پرچم نمی‌خورد)؛ `vendor/` مستثنی (یکپارچگی با lock hash)
- **PluginSandbox (§5)**: autoload اختصاصی افزونه (append — هسته هرگز shadow نمی‌شود؛ locked_prefixes هسته غیرقابل override)، fallback loader برای مسیرهای خارج از basePath، vendor ایزوله با ترتیب append، registry زمان‌اجرای capabilities (`PluginCapabilities::assertNetwork/assertDatabase`)
- **Hook (§6)**: declare-محور (module.json میزبان + `plugins.allowed_hooks`)؛ پلاگین فقط به hook اعلام‌شده متصل می‌شود؛ fire با ایزولاسیون Throwable (میزبان نمی‌شکند) + timeout/memory همکاری‌کننده (§9) + ثبت violations
- **چرخهٔ عمر (§7)**: `plugin:install/enable/disable/update/uninstall/list/verify/scan` — install = verify→scan→copy→lock→load؛ update با backup در `storage/backups/plugins`؛ `loadEnabled` در بوت با خود‌غیرفعال‌سازی افزونهٔ خراب + رویداد `plugin.load_failed`
- **Facade `Plugin`** (accessor `plugins`) + رویدادهای `plugin.installed/enabled/disabled/updated/uninstalled/load_failed`
- **AC-PLG-09**: بدون امضا وقتی `require_signature=true` → `plugin.signature_missing` و نصب رد می‌شود
- config `plugins.*` (path/enabled/require_signature/trusted_vendors/allow_vendor/allowed_hooks/sandbox/lock_file/hook_timeout_ms/hook_memory_limit/backup_path) + ترجمه‌های fa/en + `.gitignore` برای `plugins/*`
- **۵۸ تست** (امضای واقعی با sodium زمان تست): T-01 امضا معتبر/تامپر/missing/untrusted، T-02 دستکاری+فایل افزوده، T-03 scan+force، T-04 database/shell/network-wildcard/filesystem-traversal رد + runtime assertNetwork، T-05 hook listener + undeclared رد، T-06 install/enable/disable/uninstall/update/loadEnabled، T-07 vendor ایزوله (shadow `Tavaan\Support\Str` خنثی شد)، T-08 وابستگی composer افزونه کار می‌کند

### نتایج
- ۱۱۷۲ تست / ۸۰۲۰ assertion سبز (دو اجرای پیاپی — قطعی) • gate ممنوعه‌ها پاک (۱۰۸۷ فایل) • lint ۱۲۴۹ فایل پاک • smoke کامل CLI: scan→install→list→verify→disable→enable→uninstall --purge

## [Unreleased] — M8: ابزار (TVN-0077 — موتور Scaffold)

### افزوده شد
- **موتور Scaffold (DOC-077)**: StubRenderer خودنوشته (`{{var}}` + `{{#if}}/{{#unless}}/{{#each}}` با پارس خط‌محور و تودرتویی انواع متفاوت — بدون Twig/Handlebars/eval)؛ ۲۵ قالب در `resources/stubs/`؛ ۱۷ Generator + `GeneratorManager` (ثبت تنبل، کلید = `make:<key>`)
- **۱۷ دستور `make:*`** (§2): module/controller/model/migration/request/policy/command/job/event/listener/seeder/factory/test/widget/resource/engine/middleware — با `--module` (فضای‌نام `Modules\<Pascal>`)، `--resource/--api`، `--fields=name,price:integer` (fillable + ستون‌های migration)، `--unit`، `--domain=hexagonal|layered`، `--model/--event`
- **Idempotency/Overwrite (§7)**: فایل موجود → هشدار و رد؛ `--force` → پشتیبان `.bak` + بازنویسی
- **امنیت (§8)**: اعتبارسنجی PascalCase/kebab/snake با regex + sandbox مسیر (`..`/مسیر مطلق → `ScaffoldException` با messageId `scaffold.path_traversal`)
- **`tvn module:validate [name]`**: اعتبارسنجی semantic manifestها (نسخهٔ خالی/چرخه/فایل) — AC-SCF-02
- **`Str::plural`** (افزودنی هسته): قواعد s/x/z/ch/sh→es، بی‌واکه+y→ies، وام‌واژه‌های رایج (person→people…)، حفظ بزرگی حرف اول — مبنای نام جدول (`Product → products`)
- ترجمه‌های `scaffold.*` در `resources/lang/{fa,en}`؛ ۳۳ تست جدید (T-01..T-06 + `php -l` روی همهٔ فایل‌های تولیدشده — AC-SCF-01)

### تغییر یافت
- `TestCommand`: مسیرهای positional چندگانه (`tvn test tests/Unit engines/x/tests`)؛ ابهام‌زدایی filter/مسیر؛ `modules/*/tests` به مسیرهای پیش‌فرض کشف افزوده شد
- `ConsoleKernel`: ثبت `ModuleValidateCommand` در builtins
- `config/providers.php`: ثبت `ScaffoldServiceProvider`

### نتایج
- ۱۱۱۴ تست / ۷۹۱۶ assertion سبز • lint ۱۲۲۲ فایل پاک • gate ممنوعه‌ها پاک (۱۰۶۵ فایل) • smoke کامل §2 روی CLI: همهٔ خروجی‌ها `php -l` پاک و manifestها `module:validate` پاس

## [Unreleased] — M8: ابزار (TVN-0084 — موتور Test کامل شد)

### افزوده شد
- **Mock صفر‌وابستگی بدون eval (DOC-084E §4.1)**: `Mock::of(X)->shouldReceive('m')->with(...)->andReturn(...)->once()->getMock()` — زنجیرهٔ دقیق سند؛ `ProxyGenerator` کلاس Proxy تایپ‌دار را به‌صورت فایل در `sys_get_temp_dir()/tvn-mocks` تولید می‌کند (بازتولید کامل امضا: Union/Intersection/DNF، variadic، by-ref، پیش‌فرض‌های Enum/ثابت/آرایه، `self/static/parent`، nullable صریح مطابق F-15)؛ verify خودکار انتظارات شمارشی در پایان هر تست (`Mock::verifyAll` در `runBare`)؛ کلاس final → رد با پیام راهنما
- **HttpTest درون‌فرایندی (AC-TEST-02)**: trait `MakesHttpRequests` (get/post/put/patch/delete + واریانت‌های Json + `withHeaders` + `actingAs`) با بوت تنبل اپلیکیشن تست؛ session/log به‌صورت حافظه‌ای (CON-11)
- **TestResponse (DOC-084E §8)**: assertOk/Status/Created/Unauthorized/Forbidden/NotFound/Successful، assertJson (زیرمجموعهٔ بازگشتی)، assertJsonStructure (چهار شکل: فهرست کلید، nested، فهرستِ فهرست، wildcard `*`)، assertJsonCount، assertJsonValidationErrors (سازگار با problem+json فریمورک: `context.fields` و `errors`)، assertRedirect/See/DontSee/Cookie/Header — همه با API روان
- **DatabaseTest (AC-TEST-03)**: trait `RefreshDatabase` — `database.default → sqlite_memory` + اجرای Migrator (با retry کوتاه برای قفل migration در حالت موازی)
- **Factory + `Model::factory()`**: کلاس پایهٔ `Factory` (definition/state/count/make/create — immutable با clone؛ unguard موقت)؛ hook افزودنی `Model::setFactoryResolver` (بدون وابستگی معکوس database→testing)؛ قرارداد نام `<NS><Model>Factory` + `public static string $factory` صریح
- **Faker خودنوشتهٔ deterministic (AC-TEST-04)**: PRNG از نوع LCG با seed (پیش‌فرض ثابت → حتی بدون seed قطعی)؛ دادهٔ فارسی (نام/نام‌خانوادگی)، `mobile()` (09xxxxxxxxx)، `nationalCode()` با checksum رسمی، `jalaliDate()` (از موتور i18n)، `email()`/`text()`/`dateTime()`/`numberBetween()`/`randomElement()`/`boolean()`/`word()` و `unique()` (حالت بدون تکرار با سقف ۱۰۰۰ تلاش)
- **Runner ارتقاءیافته (DOC-084E §7)**: `TestSuite` (کشف idempotent با پارس token — گروه‌های ضمنی `unit/feature/app/<engine-slug>` + Attribute تکرارپذیر `#[Group]`)، `TestResult` (قابل ادغام/سریال‌شدن)، تفکیک Failures از Errors، خلاصهٔ هم‌تراز سند (Tests/Passed/Failed/Errors)
- **`tvn test` جدید**: `{paths*}` («tvn test engines/cache»)، `--group=`، `--filter=` (هر دو شکل positional و option)، `--coverage` (pcov/xdebug با تفکیک core/هر موتور؛ در نبود افزونه → هشدار و ادامه طبق §9)، `--parallel=N` (فرزندان subprocess با manifest/JSON و ادغام نتیجه؛ کد خروج و گزارش file:line حفظ می‌شود)
- **AC-TEST-05**: `AssertionFailed` محل وقوع (فایل:خطِ اولین فریم بیرون از کد فریمورک) را ضبط می‌کند → گزارش شکست رنگی با `at path:line`
- **۸۹ تست جدید برای خودِ موتور تست** (FakerTest/MockTest/TestResponseTest/TestSuiteTest/HttpTestingTest/DatabaseTestingTest + fixtures) و نمونهٔ اجراشدنی `examples/testing/test-framework.php`

### اصلاح شد
- **نشت state بین اجراها در ApiTest (throttle)**: تست‌های RateLimit با Clock فریزشدهٔ مطلق (۲۰۲۶-۰۶-۰۱) روی cache پیش‌فرض `file` می‌نوشتند → کلیدهای `throttle:*` در `storage/cache` باقی می‌ماندند و در اجرای بعدی (همان clock ثابت) «زنده» تلقی می‌شدند → 429 زودهنگام و شکست کاذب. `cache.default=array` در setUp (هم‌راستا با تست‌های Idempotency همان فایل) — اجرای مکرر suite اکنون کاملاً قطعی است (DoD §1.2)

### تغییر یافت
- `TestCase::runBare`: چرخهٔ عمر Mock (reset → setUp → test → verifyAll → tearDown → reset)؛ `assertThat` عمومی شد (برای TestResponse)
- `TestCase::__construct` افزوده شد (ثبت یک‌بارهٔ resolver برای `Model::factory()`)
- `Model`: افزودن `setFactoryResolver()/factory()` (additive — الگوی resolverهای موجود)
- `TestCommand`: بازنویسی امضا (سازگار با فراخوانی‌های قبلی)

### نتایج
- ۱۰۸۱ تست / ۷۶۷۸ assertion سبز (۹۹۲ قبلی + ۸۹ جدید) • lint ۱۱۷۸ فایل پاک • gate ممنوعه‌ها پاک • `--parallel=4` کل مجموعه را سبز و با شمار یکسان جمع می‌زند

## [Unreleased] — ممیزی سلامت و یکپارچگی (M1–M4)

### اصلاح شد
- **Router/TrieNode (DOC-040 §12)**: بازنویسی حافظه‌بهینهٔ Route/Trie — segments فشرده (`string|RouteSegment` به‌جای آرایه‌های کلیددار)، methods به‌صورت بیت‌ماسک، پارس تنبل segments، یال‌های پارامتریک `ParamEdge`، هندلرهای برگ `HandlerEntry` (≤۲ مورد inline)، تک‌فرزند static به‌صورت inline، memo نرمال‌سازی کلیدها؛ حافظهٔ ۱۰۰۰ روت از ~۴٫۹MB (اندازهٔ واقعی؛ bench قدیمی ۱۴MB خطا گزارش می‌کرد) به **~۱٫۷MB ≤ ۲MB** کاهش یافت
- **باگ `TrieNode::staticChild`**: فرزند سوم static وقتی map دو عضوی بود در slot تک‌فرزند جا می‌ماند و با lookup جدید پنهان می‌شد (۴۰۴) — invariant «map پر ⟹ single تهی» اکنون در هر دو مسیر برقرار است + ۲ تست رگرسیون
- **باگ `Route::head` نبود**: `RouteRegistrar` متد `head` را forward می‌کرد ولی `Router::head()` وجود نداشت (fatal در زمان ثبت) — افزوده شد
- **باگ latent Enum در ControllerResolver**: `from()` روی enum غیر-backed (unit enum) و رشتهٔ عددی برای enum با backing=int → اکنون فقط `BackedEnum` resolve می‌شود با cast مناسب (سبک Laravel)
- **Contract-First (DOC-013)**: متدهای مورداستفادهٔ کد به قراردادهای موجود افزوده شد — `StoreInterface::add` (مبنای CacheLock)، `QueryBuilderInterface::updateOrInsert/orderByDesc`، `ContainerInterface::beginScope/endScope/currentScope/flushScoped` (+ delegate در `Application`)، `RequestInterface::cookies`، `ResponseInterface::hasHeader`، `UploadedFileInterface::extension`، `EncrypterInterface::encryptJson/decryptJson`، `KernelInterface::commands/resolve`
- **`key:generate`**: تأییدیهٔ بازنویسی فقط وقتی کلید قبلیِ غیرخالی وجود دارد (راه‌اندازی اولیه/CI غیرتعاملی نمی‌شکند) + اصلاح غلط تایپی توضیح («تعداد»→«تولید»)
- **`migrate:rollback --force`**: هم‌تراز با `migrate:fresh/refresh` (اجرای غیرتعاملی/CI)
- **`.env` دیگر مرده نیست**: `TVN_AUTOLOAD_CACHE` با میکرواسکن زودهنگام در `core/bootstrap.php` (مرغ‌و‌تخم classmap↔env) و `TVN_CONFIG_CACHE` از `EnvRepository` خوانده می‌شود (پیش‌تر فقط `$_SERVER` واقعی — مقدار `.env` هرگز اثر نمی‌کرد)
- **`phpstan.neon.dist` مطابق DOC-111 §2**: `bootstrapFiles`/`scanDirectories`/`universalObjectCratesClasses`/الگوی صحیح exclude (`*/tests/*`) — خطاهای `class.notFound` و همهٔ خطاهای کلاس «fatal» (`method/property/staticMethod/offsetAccess/function.notFound`) صفر شد
- **Benchهای صادق‌تر**: router.bench حافظهٔ افزایشی واقعی روت‌ها را می‌سنجد (نه کل فرایند منهای ۲MB ثابت)؛ kernel.bench هدف cold (≤۲۵ms بدون opcache) را از warm (≤۸ms با opcache) تفکیک می‌کند (PERF-BOOT-01/02)؛ render.bench هدف DOC (متن معمولی ۱KB) را از سناریوی hostile (اطلاع‌رسانی) جدا کرد
- **Docblockهای کهنه**: `EarlyErrorManager::isFatal`، `TokenManager::refresh`، `SchemaGrammar::foreignKeySql`، `Session::setFingerprint` (parameter.notFound)
- **ساختار `storage/`** مطابق DOC-040 §7 ایجاد شد (app/cache/framework/logs/backups/temp + `.gitignore` و مجوزهای مستند)

### تغییر یافت
- `CacheLock` با تایپ `StoreInterface` (به‌جای object-shape) — Contract-First
- `ModelQueryBuilder` با `@method`های صریح برای متدهای fluent فورواردشده (تحلیل ایستا/IDE)
- `Ulid`/`Base32`: `(int) bindec(...)` قطعی (حذف offsetAccess float|int)

### نتایج
- ۵۴۶ تست / ۲۷۴۶ assertion سبز • ۶۷۹ فایل lint پاک • gate ممنوعه‌ها سبز • حافظهٔ ۱۰۰۰ روت ~۱٫۷MB ✅ • boot warm ~۷٫۵–۷٫۸ms ✅ / cold ~۱۸–۲۰ms ✅ • PHPStan level max: همهٔ خطاهای کلاس fatal صفر (باقی: بدهی مستندسازی نوعِ الگوی DI)

## [0.7.1] - 2026-10-17 — سازگاری کامل ویندوز (پس از گزارش اجرای `tvn test` روی Windows)

### اصلاح شد
- **🔧 `core/Support/PathGuard` (جدید)**: ابزار مشترک containment مسیر — تشخیص مسیر مطلق ویندوز (`C:\…`/UNC)، نرمال‌سازی separator + case (NTFS case-insensitive)، مقایسه با **separator انتهایی** (🔒 رفع باگ نهفتهٔ prefix: base `/app` دیگر `/application` را شامل نمی‌شد) و probe «نزدیک‌ترین جدِّ موجود» برای مسیرهای وجود‌نداشته
- **🔧 api:docs**: محافظ مسیر خروجی روی ویندوز شکست می‌خورد (مقایسهٔ `/` با خروجی backslashِ `realpath`) → PathGuard + تشخیص درست مسیر مطلق
- **🔧 ResponseFactory::assertSafePath**: containment **پیش از** وجودسنجی — مسیر بیرونیِ وجود‌نداشته (مثل `/etc/passwd` در ویندوز) حالا `PathTraversalException` می‌دهد نه `FileNotFoundException` (رفتار امنیتی درست‌تر + انتظار تست)؛ `ReportExporter` هم برای یکدستی به PathGuard منتقل شد
- **🔧 Backup**: `collectFiles` مسیر `C:\…` را نسبی فرض و به base الحاق می‌کرد → **صفر فایل** در آرشیو و restore ناقص؛ تشخیص مطلق cross-platform + `safeAbsolute` ویندوزی + `isExcluded` با relative درست برای مسیرهای خارج از base
- **🔧 Tenancy (Strategy B)**: charset محافظ مسیر sqlite (`^[A-Za-z0-9_/.-]+# Changelog — تَوان | Tavaan

فرمت: Keep a Changelog — نسخه‌گذاری: SemVer.

## [Unreleased] — ممیزی سلامت و یکپارچگی (M1–M4)

### اصلاح شد
- **Router/TrieNode (DOC-040 §12)**: بازنویسی حافظه‌بهینهٔ Route/Trie — segments فشرده (`string|RouteSegment` به‌جای آرایه‌های کلیددار)، methods به‌صورت بیت‌ماسک، پارس تنبل segments، یال‌های پارامتریک `ParamEdge`، هندلرهای برگ `HandlerEntry` (≤۲ مورد inline)، تک‌فرزند static به‌صورت inline، memo نرمال‌سازی کلیدها؛ حافظهٔ ۱۰۰۰ روت از ~۴٫۹MB (اندازهٔ واقعی؛ bench قدیمی ۱۴MB خطا گزارش می‌کرد) به **~۱٫۷MB ≤ ۲MB** کاهش یافت
- **باگ `TrieNode::staticChild`**: فرزند سوم static وقتی map دو عضوی بود در slot تک‌فرزند جا می‌ماند و با lookup جدید پنهان می‌شد (۴۰۴) — invariant «map پر ⟹ single تهی» اکنون در هر دو مسیر برقرار است + ۲ تست رگرسیون
- **باگ `Route::head` نبود**: `RouteRegistrar` متد `head` را forward می‌کرد ولی `Router::head()` وجود نداشت (fatal در زمان ثبت) — افزوده شد
- **باگ latent Enum در ControllerResolver**: `from()` روی enum غیر-backed (unit enum) و رشتهٔ عددی برای enum با backing=int → اکنون فقط `BackedEnum` resolve می‌شود با cast مناسب (سبک Laravel)
- **Contract-First (DOC-013)**: متدهای مورداستفادهٔ کد به قراردادهای موجود افزوده شد — `StoreInterface::add` (مبنای CacheLock)، `QueryBuilderInterface::updateOrInsert/orderByDesc`، `ContainerInterface::beginScope/endScope/currentScope/flushScoped` (+ delegate در `Application`)، `RequestInterface::cookies`، `ResponseInterface::hasHeader`، `UploadedFileInterface::extension`، `EncrypterInterface::encryptJson/decryptJson`، `KernelInterface::commands/resolve`
- **`key:generate`**: تأییدیهٔ بازنویسی فقط وقتی کلید قبلیِ غیرخالی وجود دارد (راه‌اندازی اولیه/CI غیرتعاملی نمی‌شکند) + اصلاح غلط تایپی توضیح («تعداد»→«تولید»)
- **`migrate:rollback --force`**: هم‌تراز با `migrate:fresh/refresh` (اجرای غیرتعاملی/CI)
- **`.env` دیگر مرده نیست**: `TVN_AUTOLOAD_CACHE` با میکرواسکن زودهنگام در `core/bootstrap.php` (مرغ‌و‌تخم classmap↔env) و `TVN_CONFIG_CACHE` از `EnvRepository` خوانده می‌شود (پیش‌تر فقط `$_SERVER` واقعی — مقدار `.env` هرگز اثر نمی‌کرد)
- **`phpstan.neon.dist` مطابق DOC-111 §2**: `bootstrapFiles`/`scanDirectories`/`universalObjectCratesClasses`/الگوی صحیح exclude (`*/tests/*`) — خطاهای `class.notFound` و همهٔ خطاهای کلاس «fatal» (`method/property/staticMethod/offsetAccess/function.notFound`) صفر شد
- **Benchهای صادق‌تر**: router.bench حافظهٔ افزایشی واقعی روت‌ها را می‌سنجد (نه کل فرایند منهای ۲MB ثابت)؛ kernel.bench هدف cold (≤۲۵ms بدون opcache) را از warm (≤۸ms با opcache) تفکیک می‌کند (PERF-BOOT-01/02)؛ render.bench هدف DOC (متن معمولی ۱KB) را از سناریوی hostile (اطلاع‌رسانی) جدا کرد
- **Docblockهای کهنه**: `EarlyErrorManager::isFatal`، `TokenManager::refresh`، `SchemaGrammar::foreignKeySql`، `Session::setFingerprint` (parameter.notFound)
- **ساختار `storage/`** مطابق DOC-040 §7 ایجاد شد (app/cache/framework/logs/backups/temp + `.gitignore` و مجوزهای مستند)

### تغییر یافت
- `CacheLock` با تایپ `StoreInterface` (به‌جای object-shape) — Contract-First
- `ModelQueryBuilder` با `@method`های صریح برای متدهای fluent فورواردشده (تحلیل ایستا/IDE)
- `Ulid`/`Base32`: `(int) bindec(...)` قطعی (حذف offsetAccess float|int)

### نتایج
- ۵۴۶ تست / ۲۷۴۶ assertion سبز • ۶۷۹ فایل lint پاک • gate ممنوعه‌ها سبز • حافظهٔ ۱۰۰۰ روت ~۱٫۷MB ✅ • boot warm ~۷٫۵–۷٫۸ms ✅ / cold ~۱۸–۲۰ms ✅ • PHPStan level max: همهٔ خطاهای کلاس fatal صفر (باقی: بدهی مستندسازی نوعِ الگوی DI)

) backslash و حرف درایو را رد می‌کرد → `tenancy.unsafe_identifier` در ویندوز؛ charset ویندوزی فقط روی ویندوز فعال می‌شود (`..` همچنان همه‌جا رد است)
- **🔧 MediaTest fixture**: base64 «کوچک‌ترین JPEG» با libjpeg سخت‌گیر (gd ویندوز **و** دیبیان) ناسازگار بود («Bogus Huffman table definition» — باگ latent از M6)؛ fixture اکنون با خودِ gd در زمان اجرا تولید می‌شود → **MediaTest برای اولین بار در این محیط‌ها سبز**
- **🔧 تست‌های capability-based**: ‏`stream_socket_pair` در ویندوز پشتیبانی نمی‌شود (۳ تست Mail + ۱ تست WebSocket — guard موجود فقط `function_exists` بود که در ویندوز true است)؛ symlink بدون ادمین/Developer Mode ممکن نیست (۲ تست Filesystem — با probe عملی)؛ visibility مبتنی بر POSIX perms در NTFS بی‌معنی است (۱ تست) — همه با skip مستند به قرارداد early-return مخزن
- **🔧 ConsoleTest**: انتظار `PHP_EOL` به‌جای `\n` ثابت (خروجی کنسول در ویندوز `\r\n` است)
- **TVN_VERSION در `core/bootstrap.php`**: از `0.6.0` به `0.7.1` همگام شد (پیش‌تر `tvn serve`/about نسخهٔ کهنه نشان می‌دادند)
- **🔧 Quality Gate (`tools/scripts/check-forbidden.php`)**: سه اصلاح — (۱) رد قالب‌های `.tv.php` از اسکن strict_types (template منبع PHP مستقل نیست؛ مانند `resources/views` که بیرون از اسکن است)، (۲) رفع **مثبت کاذب** `F-EXTRACT`: الگو فقط تابع زبانی `extract()` را می‌گیرد نه متدِ هم‌نام (`ZipReader::extract` / `$zip->extract(…)`) — با lookbehind برای `->` و `function `؛ کشف extract برهنه در تست تأیید شد، (۳) معافیت مستند `F-SHELL` برای `ScheduleManager` — `shell_exec` آنجا پیاده‌سازی قابلیت مستند `Schedule::exec()` است (DOC-062). نتیجه: gate روی کل مخزن **سبز** (۱۰۰۷ فایل) — پیش‌تر از M5 به‌طور خاموش قرمز بود

### مستندات
- گزارش کامل ریشه‌یابی: `docs/reports/windows-compatibility-fixes.md` (۱۵ شکست → علت → اصلاح)

### نتایج
- Linux: **۹۹۲ تست / ۶۷۰۴ assertion سبز** (دو اجرای متوالی) • انتظار ویندوز: ۱۵ شکست → **صفر** (۹ skip مستندِ محدودیت OS + ۶ اصلاح کد/fixture) • smoke: ‏`api:docs` روی مسیر داخلی سبز و `../evil.yaml` رد

## [0.7.0] - 2026-10-17 — فاز M7: دامنه

### افزوده شد
- **TVN-0071 Workflow (BPM)**: موتور گردش‌کار خودنوشته — **DSL تعریف فرایند** (`ProcessBuilder`: start/state/exclusiveGateway/parallelGateway/timerEvent/subProcess + زنجیرهٔ fluent guard/action/permission/assignee/form) و **JSON/Schema** (DOC-071 §3.1 — guard رشته‌ای `can:<permission>`، تایمر ISO-8601) با validate کامل (missing_start/duplicate/unknown_state/orphan/subprocess_missing)؛ **Execution Engine** مطابق §4: `fire()` با ترتیب guard → authorize (RBAC) → actions → حرکت Token → History → Event، **🔒 اتمیک** (snapshot/restore — شکست = بدون اثر)؛ **Token-based Fork/Join** (دروازهٔ موازی: fork با یک توکن per خروجی، join با شمارش ورودی‌ها و توکن منتظر؛ 🔒 گذار خروجی دروازه فقط توسط موتور)؛ **دروازهٔ انحصاری** (اولین guard موفق به ترتیب تعریف)؛ **Human Task** (`tvn_process_tasks` با assignee `role:`/`user:`، inbox نقش‌آگاه از RBAC واقعی، claim با 🔒 fail-closed بررسی نقش، complete با transition صریح/`input[transition]`/تک‌خروجی، delegate)؛ **Timer** (پارس انسانی `3 days` و ISO `P3D`، ثبت خودکار با ورود به state و لغو با ترک آن، `TimerProcessor` مقاوم + `workflow:timers`)؛ **Sub-Process** (فرزند با پیوند `_parent`، پیش‌برد خودکار والد با تکمیل فرزند — 🔒 بدون rollback فرزند)؛ **History** کامل با user_id؛ **نسخه‌بندی تعاریف** (version++ و instanceهای جاری روی نسخهٔ خود)؛ **Visualizer** با خروجی **Graphviz DOT و Mermaid** (AC-WF-11)؛ **`refresh()` + همگام‌سازی خودکار پیش از هر تغییر** (دستهٔ کهنه پس از Task/Timer → not_running درست، نه noToken)؛ 🔒 محافظ DoS (`max_active_instances`)، 🔒 mask متغیرهای حساس، 🔒 کلیدهای رزرو `_tokens/…` غیرقابل‌نوشتن از بیرون؛ Store دوگانهٔ **database/array**؛ ۴ جدول (`tvn_workflows`/`tvn_process_instances`/`tvn_process_tasks`/`tvn_process_history` — CHAR(26) ULID)؛ رویدادها `workflow.started/transitioned/completed/canceled/task.created` (شیء + رشته)؛ CLI `workflow:timers`/`workflow:list --diagram`؛ Facade `Workflow`
- **TVN-0072 Report**: گزارش‌ساز با **Dataset چندشکل** (Closure lazy / QueryBuilder / SQL خام / آرایه)؛ **Field** (string/number/money/date/bool + `derive` محاسبه‌شده + aggregate) و **Filter** (text→LIKE با escape wildcard / select با 🔒 allowlist مقدارها از جمله `Enum::cases()` / daterange با نیم‌روز خودکار / number / bool) — 🔒 **فقط فیلدهای اعلام‌شده** فیلتر/orderByپذیرند (allowlist + regex شناسه)؛ **groupBy + subtotal** و **Pivot** (DOC-072 §6 — ستون از فیلد یا Closure، تجمیع sum/avg/count/min/max با **Decimal** بدون خطای float — ADR-0013)؛ **تاریخ جلالی** (موتور i18n/Jalali)؛ خروجی **CSV** با 🔒 BOM + delimiter «;» + **streaming تکه‌ای** (سربار حافظه ~0 در بنچ ۱۰۰k ردیف) + 🔒 **ضد CSV Injection** (prefix `'` برای `=+-@`)؛ **xlsx کاملاً خودنوشته** (OOXML: ContentTypes/rels/workbook/styles با سربرگ Bold+freeze pane/sharedStrings/sheet — ZIP با `ZipWriterPure` موتور compat بدون ext-zip؛ AC-RPT-10 در تست با ZipArchive تأیید)؛ **HTML** با قالب `report::table` (namespace موتور روی ViewFactory + fallback escaped — 🔒 ۰ XSS در تست)؛ **JSON**؛ **PDF اختیاری** (بدون driver → `report.pdf_not_configured`؛ driver=html → آمادهٔ چاپ)؛ 🔒 **sandbox مسیر export** (فقط داخل base path) + 🔒 `max_rows` با flag بریدگی؛ Facade `Report`
- **TVN-0073 Payment**: انتزاع درگاه‌های پرداخت با قرارداد **`core/Contracts/Payment/GatewayInterface`** (purchase/verify/refund/inquire/name)؛ **۱۰ درگاه**: زرین‌پال v4 (کدهای 100/101، 🔒 تومان نه ریال، sandbox)، آیدی‌پی (X-API-KEY/X-SANDBOX + 🔒 **IPN با امضای HMAC-SHA256**)، پی‌پینگ (Bearer)، سامان/SEP (GetToken→Redirect→Verify با RefNum)، به‌پرداخت ملت (SOAP/XML خودنوشته بدون ext-soap: PayRequest→PayVerification→SaleConfirm با idempotency کد 43)، پارسیان (SalesPaymentRequest/ConfirmPayment/PaymentReversal)، سداد و ملی (payload امضاشده HMAC)، استرایپ (Checkout Sessions)، **کیف پول داخلی** (`WalletStoreInterface` + ConfigWalletStore — 🔒 کسر موجودی فقط در verify)؛ **`PaymentManager`**: جریان کامل purchase→callback→verify→refund با **جدول `tvn_payment_transactions`** (ULID، 🔒 `uk_idem` یکتا)؛ 🔒 **امنیت مطابق §7**: verify **همیشه با مبلغ محلی Transaction** (Amount tampering در تست رد شد)، **عدم اعتماد به `Status` بازگشتی** (callback جعلی با Status=OK ولی verify ناموفق → failed)، **Idempotency** (replay خرید pending → همان authority بدون تماس با درگاه؛ تراکنش موفق → `payment.already_paid`؛ callback دوباره → `alreadyProcessed` بدون verify/رویداد مضاعف)، **PAN فقط mask** (`603799****1234` — در DB و پاسخ)، اعتبارنامه فقط از ENV (`gateway_not_configured` پیش از هر تماس شبکه)، SSRF guard ارثی از http-client؛ **درگاه سفارشی فقط با `extend()` یا config `class`** (AC-PAY-10 — در تست با FakeGateway تأیید شد)؛ routeهای `ANY /payment/callback` و `POST /payment/notify/{gateway}` (۴۰۳ برای امضای نامعتبر)؛ رویدادها `payment.initiated/succeeded/failed/refunded`؛ `payment:list` (PAN mask)؛ Facade `Payment`
- **قرارداد جدید (Contract-First)**: `core/Contracts/Payment/GatewayInterface`
- **زبان**: پیام‌های fa+en برای workflow/report/payment
- **نمونه‌های اجراشدنی**: `examples/workflow/order-approval.php` (DSL+Task+History+DOT)، `examples/report/sales-report.php` (filter/group/pivot→CSV+xlsx)، `examples/payment/wallet-checkout.php` (چرخهٔ کامل کیف پول)
- **بنچمارک**: `engines/report/benchmarks/report.bench.php` (اهداف DOC-072 §9 — همه سبز: html 10k ≈ 102ms ≤ 2s، xlsx 10k ≈ 105ms ≤ 3s، pivot ≈ 56ms ≤ 1s، streaming 100k سربار ≈ 0MB ≤ 5MB) و `engines/workflow/benchmarks/workflow.bench.php` (fire ≈ 7.6µs، start ≈ 13µs، build 30-state ≈ 1.2ms)
- متغیرهای M7 در `.env.example` (workflow/report/payment + اعتبارنامهٔ ۱۰ درگاه)

### نتایج
- **۹۸۵ تست / ۶۶۷۸ assertion سبز** (۸۵ تست جدید M7: ۳۷ workflow + ۲۳ report + ۲۵ payment) • lint کامل پاک (۱۱۴۴ فایل) • **۴۸/۴۸ موتور فعال** • `tvn migrate` با ۲ migration جدید سبز • smoke زنده: `workflow:timers`/`workflow:list`/`payment:list`/`migrate:status` • بنچ‌ها با اختلاف زیاد سبز • ~۹٬۳۰۰ خط src (۸۵ فایل) + ~۲٬۱۰۰ خط تست — **صفر وابستگی بیرونی** (xlsx/SOAP/BPM همگی خودنوشته)

## [0.6.0] - 2026-10-16 — فاز M6: سرویس‌ها

### افزوده شد
- **TVN-0067 Observability**: `MetricsRegistry` با **Counter/Gauge/Histogram/Summary** (برچسب‌دار، write-through روی cache با کلید `tvn:metrics:snapshot`، `timer()`)، **Prometheus text format خودنوشته** (HELP/TYPE، bucketهای تجمعی `le`/`+Inf`، quantileها، escape برچسب‌ها، sanitize نام) + endpoint ‏`GET /metrics`؛ **Tracing خودنوشته**: `Tracer`/`Span` با والد خودکار و پشته، **Sampling قطعی** بر پایه trace-id (`RateSampler` — همهٔ spanهای یک trace هم‌سرنوشت)، `withSpan` (ثبت exception + status + پایان قطعی)، `NonRecordingSpan`، **W3C TraceContext propagator** (پارس/تزریق `traceparent` با رد idهای تمام‌صفر)؛ **۴ Exporter**: Array (تست)، Log (ساختاریافته با 🔒 mask کلیدهای حساس)، **OTLP/HTTP JSON** (بدون curl — stream context، ساختار resourceSpans→scopeSpans→spans با AnyValue تایپ‌شده)، **Jaeger UDP با Thrift Compact Writer کاملاً خودنوشته** (zigzag varint، field delta، emitBatch — round-trip در تست با reader کمکی)؛ **Health Checks** (`Health::add/live/ready` + نرمال‌سازی bool/Up/Down/array/CheckResult/Throwable + responseTime، routeهای `GET /health/live|ready` با 200/503، بررسی‌های پیش‌فرض database/cache/queue/disk)؛ **Debug Bar** فقط-dev (🔒 guard سه‌گانه `app.debug && env≠production && config`، خروجی کاملاً escape ضد XSS، تزریق پیش از `</body>`، داده: timings/queries/memory/events/route/middleware/config-masked) + `Stopwatch`؛ `MetricsMiddleware` سرتاسری (http_requests_total + duration با labelهای route/method/status و 🔒 cardinality محدود)؛ Facadeهای `Metrics` و `Trace`
- **TVN-0052 Search**: `SearchManager` چند‌درایوره؛ **Native Inverted Index خودنوشتهٔ فارسی‌آگاه** — **نرمال‌سازی کامل** (ی/ي، ک/ك، ة/ه، آ/أ/إ، حذف اعراب و تحریر، حذف نیم‌فاصله، ارقام فارسی/عربی→لاتین)، **stemming** پسوندهای فارسی (دو فرم ی/ي — ترتیب بلندترین-اول با محافظ طول ریشه)، ایست‌واژه‌ها، **BM25** (idf × tf با k1=1.2) + **boost فیلد** (`title^3`)، **Facet** (🔒 فقط فیلدهای filterable)، **Sort** (🔒 فقط sortable)، صفحه‌بندی، **Highlight با escape-then-mark** (🔒 ضد XSS — تگ‌های سفارشی pair-sanitize)، **Suggest** (prefix + contains + mb-Levenshtein≤1 با display-map فرم خام) و «آیا منظور شما…» هنگام نتیجهٔ خالی، **پایستگی اتمیک JSON** (tmp+rename)؛ درایور **SQLite FTS5** (متن نرمال‌شده + payload JSON، `bm25()`، UNINDEXED برای filter/sort/facet، prefix-query برای suggest)؛ درایورهای **MySQL FULLTEXT** (ngram اختیاری، MATCH…AGAINST، ON DUPLICATE KEY) و **PostgreSQL** (to_tsvector/tsquery + GIN + ts_rank)؛ آداپترهای HTTP **Meilisearch/Typesense** (transport تزریقی)؛ 🔒 محافظ DoS (طول کوئری/تعداد توکن/فاصلهٔ Levenshtein)؛ `Searchable` trait + **ایندکس خودکار از رویدادهای `model.saved`/`model.deleted`**؛ `search:reindex`؛ قرارداد `SearchDriverInterface`/`SearchManagerInterface`؛ Facade `Search`
- **TVN-0044 API**: **`JsonResource`** با `when/whenLoaded/whenHas/whenCounted/mergeWhen/whenPivotLoaded` (+ closureهای **lazy** — شرط false هرگز اجرا نمی‌شود)، `MissingValue`/`MergeValue` (هر دو Traversable برای سبک spread)، resolve بازگشتی (Arrayable/JsonSerializable/Paginator/Model)، wrap قابل‌غیرفعال (`$wrap`)، `AnonymousResourceCollection` با **meta/links صفحه‌بندی** در سطح بالا، `response()`؛ بازگشت Resource از controller خودکار JSON می‌شود (بدون تغییر http)؛ **Versioning** سه‌استراتژی (url/header `application/vnd.tavaan.v{n}+json`/query + پارامتر صریح `api.version:2`) با 🔒 رد نگارش پشتیبانی‌نشده (RFC 9457) و هدرهای **Deprecation/Sunset** برای نگارش منسوخ + `X-Api-Version`؛ **IdempotencyMiddleware** (`Idempotency-Key` → replay پاسخ cache‌شده با `X-Idempotent-Replay`، 🔒 قفل in-progress ضد race با 409، فقط status<500 ذخیره می‌شود، کلید hash‌شده)؛ **ThrottleMiddleware** (`throttle:api` نام‌دار از config یا `throttle:60,1`، پنجرهٔ ثابت اتمیک با add/increment، هویت user→API key→IP، هدرهای X-RateLimit-Limit/Remaining/Reset + Retry-After، 🕐 Clock-aware)؛ **OpenAPI 3.1 Generator خودنوشته** از route+attribute (`#[Api]`/`#[ApiResponse]`/`#[ApiParam]`) + **requestBody از FormRequest rules** (required/type/format/min/max/enum) + پارامتر مسیر با نوع از `whereNumber/whereUuid` + security از middleware auth (bearerAuth) + **YAML emitter داخلی** (کلید/رشتهٔ امن، quote رزروها، `{}` برای map خالی، لیستِ map)؛ `api:docs` (stdout یا فایل با 🔒 محافظ مسیر داخل پروژه)؛ نمونهٔ `routes/api.php` + `app/src/Http/Resources/DemoItemResource`
- **TVN-0082 Tenancy**: **سه استراتژی** — A: `TenantScope` (Global Scope؛ 🔒 بدون context → `TenantNotResolvedException`، `allowGlobalTenant` برای مدل سراسری) + `TenantObserver` (پرکردن خودکار tenant_id؛ 🔒 fail بدون context — TEN-SEC-06) + trait `BelongsToTenant` (🔒 `tenant_id` غیر mass-assignable — TEN-SEC-02)؛ B: `DatabaseTenantManager` (اتصال پویا `tenant` از قالب مرکزی + settings، `setDefaultConnection` با **بازگردانی قطعی**، provisioning: sqlite فایل / MySQL `CREATE DATABASE` / PG با 🔒 محافظ شناسه)؛ C: `SchemaTenantManager` (`SET search_path` مخصوص PG)؛ **زنجیرهٔ Resolver** (Domain subdomain+lookup / Header / Path `/t/{id}` / Query فقط-dev / Token از claim JWT با 🔒 رد exp گذشته / Default — هر ورودی regex-sanitize)؛ `IdentifyTenantMiddleware` (+`terminate` → 🔒 TEN-SEC-05 پاک‌سازی)؛ `TenantContext` (run تودرتو با بازگردانی در finally، `withoutCurrentScope` با **مجوز gate + رویداد Audit**)؛ `TenantManager` (CRUD مرکزی با 🔒 اتصال ثبت‌شده در ساخت — جابه‌جایی default آن را نمی‌شکند، memo، domain lowercase، رویداد `TenantCreated`)؛ **QuotaGuard** پنجره‌دار (minute/hour/day/month/total روی cache با Clock testable + رویداد `TenantQuotaExceeded`)؛ **جداسازی cache** با `TenantScopeStore` (driver «tenant» → `Cache::store('tenant')`، کلید `tvn:t{id}:` — 🔒 TEN-SEC-03)؛ `TenantConfigRepository` (overlay settingها با کلید literal+dot)؛ `TenantProvisioner` (create+migrate+seed، `migrateAll` با rate limit و جمع‌آوری خطا، delete=archive→force drop)؛ trait صف `RestoresTenantContext` (capture id در dispatch → بازیابی در worker)؛ `db:tenant-guard` (اسکن Query Log — heuristic گزارش کوئری بدون tenant_id)؛ CLI: `tenant:create/list/migrate/delete/stats`؛ migration جدول `tvn_tenants`؛ قراردادها `TenantInterface`/`TenantResolverInterface`؛ Facade `Tenant`
- **TVN-0075 FeatureFlag**: `FeatureManager` با زنجیرهٔ resolve (runtime `define` → database `tvn_features` با cache نسخه‌دار → config)، قواعد **role/permission/user_id/tenant/percentage/env/ip**، 🔒 **percentage پایدار** (`crc32(seed|feature)` — همان کاربر همیشه همان bucket؛ نام flag هم در hash تا bucketهای دو flag مستقل بمانند)، **Kill Switch** فوری (`kill/revive` روی cache — حتی value() را default می‌کند)، **A/B experiment** پایدار با پخش یکنواخت، `value()` برای پرچم‌های غیربولی، `Feature::for($user)` scoped، درایور database با persist دو‌طرفه و `flushCache`، 🔒 کلید cache با version (ضد poisoning و مصونیت از کش کهنه)؛ migration `tvn_features`؛ Facade `Feature`
- **TVN-0045 Realtime**: **SSE بدون هیچ ext** — `SseStreamer` (StreamedResponse با هدرهای `text/event-stream`/`no-cache`/🔒`X-Accel-Buffering: no`، `retry:`، قالب `event:/id:/data:` چندخطی، heartbeat `: keep-alive`، 🔒 sanitize نام رویداد ضد تزریق خط، حالت bounded برای تست) + route `GET /events/stream?channels=` (🔒 کانال خصوصی → 403)؛ **WebSocket RFC 6455 خودنوشته** — `WebSocketFrame` (encode/decode incremental، سه فرم طول 7/16/64 بیتی، 🔒 mask اجباری client، opcodeهای text/binary/close/ping/pong/continuation، 🔒 سقف payload 16MB، acceptKey رسمی) + `WebSocketServer` (stream_socket_server بدون ext، handshake با 🔒 **بررسی Origin**، max_clients با 503، پروتکل subscribe/unsubscribe/ping با 🔒 **توکن HMAC برای کانال خصوصی**، fan-out با cursor مستقل هر client، fragmentation، close handshake، قابل تست با stream_socket_pair)؛ **Brokerها**: Array (cap)، **Database** (polling با مکان‌نما + prune با TTL هم‌قالب `Clock::isoNow`)، **Redis** (بازاستفاده از `RedisClientInterface` موتور queue — seq سراسری INCR + RPUSH/LTRIM/LRANGE، fallback خودکار به database اگر ping شکست)؛ `ChannelRegistry` ({param}/wildcard/normalize `private.`→`private:`)؛ **احراز کانال** `POST /broadcasting/auth` (fail-closed برای ثبت‌نشده، توکن sign/verify با exp)؛ `PresenceManager` (join/heartbeat/leave/members با TTL و هرس خودکار)؛ **قلاب `ShouldBroadcast` در EventDispatcher** (best-effort + لاگ شکست)؛ `routes/channels.php`؛ `realtime:websocket`؛ قراردادها `ShouldBroadcast`/`BrokerInterface`؛ Facadeهای `Broadcast` و `Realtime`
- **قراردادهای جدید (Contract-First)**: `core/Contracts/Observability` (MetricsInterface/SpanInterface/SpanExporterInterface/StatusCode)، `core/Contracts/Search`، `core/Contracts/Tenancy`، `core/Contracts/Realtime`
- **زبان**: پیام‌های fa+en برای observability/search/api/tenancy/feature_flag/realtime
- **نمونه‌های DX**: `routes/api.php` (versioning+throttle+idempotent+Resource)، `routes/channels.php` (الگوهای احراز)، متغیرهای M6 در `.env.example`
- **🔒 استثنا برای `api/*` و `broadcasting/auth` در CSRF** (config/session) — لایهٔ API با token/bearer محافظت می‌شود (قرارداد رایج api-group)

### تغییر یافت
- **EventDispatcher**: قلاب `ShouldBroadcast` پس از اجرای listenerها (بدون شکست dispatch — best-effort) + `startRecording/dispatchLog` برای Debug Bar (بدون سربار وقتی غیرفعال)
- **HttpKernel**: getter ‏`resolvedMiddleware()` (دادهٔ پنل Debug Bar)
- **QueryBuilder/Connection**: بدون تغییر ساختاری — اتصال `driver()` مبنای بررسی سازگاری درایور search شد

### نتایج
- **۹۰۱ تست / ۶۳۷۱ assertion سبز** (۱۹۷ تست جدید M6؛ دو اجرای متوالی پایدار) • lint کامل پاک • **۴۵/۴۵ موتور فعال** • smoke زنده: `api:docs` (YAML/JSON معتبر)، Resource collection `{"data":…}`، throttle headers، **Idempotency replay یکسان**، `/metrics` Prometheus تجمعی بین درخواست‌ها، `/health/live|ready`، SSE stream، `tenant:create/list/stats` با quota، `migrate` (۳ جدول جدید)، broadcast/auth=403 برای ناشناس • WebSocket acceptKey سبز روی **بردار رسمی RFC 6455** • Jaeger Thrift round-trip • fuzzing نشت cross-tenant (۱۰۰ راند) صفر

## [0.5.1] - 2026-10-16 — M5 Hotfix: محافظ بازگشت (رفع «Maximum call stack size exceeded»)

### اصلاح شد
- **🔴 Queue/Serializer — بازگشت بدون محافظ**: ارجاع دوری شیء (`$job->ref = $job`)، دور آرایه‌ای با ارجاع (`$arr['self'] = &$arr`)، تودرتویی عمیق‌تر از ۶۴ سطح، و زنجیرهٔ دوری (`$job->chain([$job])` یا متقابل a→b→a) باعث خطای **کشنده و غیرقابل‌گیری** (OOM / «Maximum call stack size exceeded») در worker و مسیر dispatch می‌شد. اکنون: تشخیص دور مسیر-محور با `SplObjectStorage` (گراف لوزیِ مجاز دچار مثبت کاذب نمی‌شود)، سقف عمق `MAX_DEPTH=64` در هر دو سمت `prepare`/`restore` (payload دستکاری‌شده نیز stack را نمی‌شکند)، ردیابی اعضای زنجیره با `spl_object_id`، و `JsonException` خامِ encode/decode در `QueueException` کنترل‌شده پیچیده می‌شود (`queue.unserializable` / `queue.invalid_payload`)
- **🔴 Schedule — خودبازگشتی `schedule:run`**: زمان‌بندی‌کردن خودِ `schedule:run` (مثلاً در `routes/console.php`) در حالت درون‌فرآیندی → بازگشت بی‌پایان («Maximum call stack size exceeded») و در حالت background → انفجار فرآیند (هر اجرا زایش `schedule:run` جدید) می‌داد؛ اکنون رویداد با پیام واضح `failed` می‌شود و هرگز اجرا نمی‌گردد

### افزوده شد
- ۹ تست رگرسیون (۸ Queue + ۱ Schedule) + fixture `CycleJob` — مجموعهٔ کامل: **۷۰۴ تست / ۳۴۲۰ assertion سبز**

## [0.5.0] - 2026-10-16 — فاز M5: ارتباطی و زیرساخت

### افزوده شد
- **TVN-0061 Queue**: `QueueManager` با **۵ درایور** (sync/database/redis/file/null)، **Worker مقاوم** (DOC-061 §4: retry با backoff نمایی/فهرستی، timeout با pcntl_alarm + fallback cooperative، graceful shutdown با SIGTERM/SIGINT، recycle حافظه، سیگنال `queue:restart`)، **Pop اتمیک** با `FOR UPDATE SKIP LOCKED` (دو worker موازی هرگز یک job را دوبار پردازش نمی‌کنند — AC-QUEUE-13) + بازیابی reservedهای گیرکرده (retry_after)، **Batch** (finally callbacks، allowFailures، cancel) + **Chain** (توقف در شکست)، Delayed Jobs، **Rate Limiting** روی cache، Failed Jobs + **DLQ** اختیاری، 🔒 **Serialization فقط JSON + Allowlist** (هرگز `unserialize` باز؛ مدل‌های ORM فقط «کلاس+کلید» با `SerializesModels`)، رویدادهای ShouldQueue با **roundtrip کامل** (`dispatchNow` ضد requeue)، درایور redis با **کلاینت RESP خودنوشته** روی stream (بدون ext-redis) + Lua scripts اتمیک، `RedisClientInterface` قابل Mock؛ CLI: `queue:work` `queue:listen` (subprocess) `queue:restart` `queue:retry` `queue:failed` `queue:flush` `queue:prune-failed` `queue:monitor`؛ جداول tvn_jobs/tvn_failed_jobs/tvn_job_batches + migration؛ Facade `Queue`
- **TVN-0062 Schedule**: **Cron Expression Parser خودنوشته** (۵ فیلد + list/range/step + `7`=یکشنبه + 🔒 OR کلاسیک DOM/DOW — منطبق بر بردارهای مرجع)، Fluent API کامل (everyMinute…yearly/cron/at/weeklyOn/monthlyOn/quarterly)، **Overlap Prevention** با mutex اتمیک (`cache->add`)، **onOneServer** (قفل دقیقه‌ای — فقط اولین سرور)، timezone per-event + پیش‌فرض manager، between/unlessBetween (شبانه‌گذر)، when/skip، runInBackground، evenInMaintenanceMode، onSuccess/onFailure، output(path)، `nextRunDates` (preview)،اجرای چهار نوع وظیفه (command درون‌فرآیندی با ضبط خروجی/call/job→queue/exec)، `schedule:run` (یک entry در crontab) + `schedule:list`، `routes/console.php`؛ Facade `Schedule`
- **TVN-0063 Filesystem**: `FilesystemManager` چند-disk با **۴ درایور** (local/s3/ftp/memory)، 🔒 **Path Sandbox** (رد `..`/null-byte + realpath ضد **Symlink Escape**)، 🔒 **Atomic Write** (tmp+rename+LOCK_EX)، **S3 SigV4 خودنوشته بدون SDK** (سبز روی **AWS reference vectors** — GET/PUT Object examples)، S3-compatible (Minio/ArvanCloud) با endpoint/path-style، presigned `temporaryUrl` (S3) و HMAC-signed (local با کلید اپ)، list با continuation-token + delimiter، visibility (chmod)، readStream/writeStream با **حافظهٔ ثابت**، putFile (rename تصادفی آپلود)، download (Response attachment)، `storage:link`؛ Facade `Storage`
- **TVN-0064 HttpClient**: `Factory` fluent (get/post/put/patch/delete/head + withHeaders/withToken/asJson/asForm/acceptJson/withQueryParameters/withCookies/attach-multipart/timeout/retry)، **Retry با backoff نمایی+jitter** (فقط retryable: timeout/5xx/429 با احترام به Retry-After)، **Circuit Breaker** روی cache (۵ خطا/۶۰s → open ۳۰s → half-open)، 🔒 **SSRF Guard** (امن از `security` + `InternalSsrfGuard` fallback: رد private/loopback/link-local/**cloud metadata**/port غیرمعمول + fail-closed DNS) با **re-check در هر redirect hop**، max-redirects، **Pool موازی** (curl_multi با redirect/SSRF حین multi + fallback ترتیبی)، **`Http::fake()`** با pattern/sequence/closure + assertSent/assertNotSent/assertSentCount، Transport: curl → **stream fallback** (Compat::driver('http'))، max_size ضد DoS؛ Facade `Http`
- **TVN-0065 Mail**: **SMTP client خالص خودنوشته** با `stream_socket_client` (بدون ext) — STARTTLS با `stream_socket_enable_crypto`، SSL، AUTH LOGIN، multi-line responses، 🔒 Dot-stuffing (RFC 5321)، timeout اجباری، socket-factory/crypto-enabler تزریقی (تست‌پذیر با socket pair)، **MIME Builder** (multipart/mixed→alternative→related، RFC 2047 فارسی، Bcc هرگز در هدر نشت نمی‌کند)، **۶ transport** (smtp/sendmail/mail/log/array/ses با SigV4 service=ses)، `Mailable` (build/subject/view/textView/html/attach/attachData/embed-inline/replyTo)، `Mail::to()->cc()->send()`، **`Mail::queue/later`** (job صف‌روند با پیوست base64 JSON-safe)، `Mail::fake()` + assertSent، 🔒 محافظ **Header Injection** (رد CR/LF/NUL در subject/آدرس/هدر + `filter_var` EMAIL)؛ Facade `Mail`
- **TVN-0066 Notification**: `NotificationSender` با **۸ کانال** (mail/sms/database/broadcast/push/webhook/telegram/bale/eitaa)، `Notification` پایه (via + to{Channel})، **۵ درگاه پیامک ایرانی** (کاوه‌نگار/ملی‌پیامک/قاصدک/SMS.ir/فراز) روی HttpClient + LogSmsGateway، **In-App** (tvn_notifications + allFor/unreadCount/markAsRead/markAllAsRead)، `AnonymousNotifiable` (`Notification::route('mail', …)->notify(…)`)، trait `RoutesNotifications` (`$user->notify()` + routeهای پیش‌فرض email/mobile/chat_id)، **queue** (SendQueuedNotification)، 🔒 **Rate Limit per-user-per-hour ضد SMS Bombing**، 🔒 mask شماره در لاگ (0912***4567 با نرمال‌سازی +98)، شکست یک کانال بقیه را متوقف نمی‌کند، کانال سفارشی با extend، `Notification::fake()` + assertSentTo/assertNotSentTo/assertSentCount، Webhook با SSRF guard، Telegram/Bale/Eitaa با Bot API مشترک + inline keyboard؛ migration + Facade `Notification`
- **TVN-0074 Media**: `MediaManager` با **جریان آپلود امن ۷ مرحله‌ای** (DOC-074 §2): size/extension allowlist → 🔒 **MIME واقعی با MagicBytes** (compat؛ هرگز content-type کاربر) → 🔒 **تطبیق extension↔MIME** (رد `.php` با image/*) → 🔒 **polyglot scan** (رد `<?php` جاسازی‌شده) → 🔒 **Image Bomb** (سقف ابعاد از هدر — بدون decode) → **rename تصادفی** (نام اصلی هرگز در path نیست) → ثبت در کتابخانه + **dedup با SHA-256**، `Image` fluent (resize/crop/thumbnail/watermark/convert-webp-avif/rotate/flip/save/exif/dimensions) با **درایور auto→imagick→gd→none** و `EngineCapabilityException` (بدون درایور: فقط ذخیره + metadata همیشه کار می‌کند)، **variants** (thumb/medium/large/webp)، EXIF **سانیتیزه‌شده** (🔒 بدون GPS)، re-encode ضد polyglot (در صورت درایور)؛ جدول tvn_media + migration؛ Facade `Media`
- **TVN-0068 Backup**: `BackupManager` — **DB Dump خالص بدون mysqldump** (schema DDL از sqlite_master/SHOW CREATE + داده **chunked با cursor → حافظهٔ ثابت**، batchهای ۱۰۰ تایی، escape با PDO::quote)، **ZipWriterPure** (compat) + **ZipReader خودنوشته** (EOCD→CD→Local Headers، Store/Deflate، 🔒 CRC-32 verify)، 🔒 **رمزنگاری AES-256-GCM با کلید جدا از APP_KEY** (TVN_BACKUP_KEY؛ AAD=tavaan-backup؛ fallback به crypt engine)، **Manifest** (id/type/size/sha256/contents/versions)، **verify** (hash+decrypt test — تشخیص دستکاری)، **restore** (🔒 force اجباری + maintenance mode خودکار + بازگردانی DB/فایل‌ها به مسیر اصلی با بررسی traversal + clear cache)، **prune/applyRetention** (daily/weekly/monthly)، 🔒 جمع‌آوری فایل بدون دنبال‌کردن symlink؛ CLI: `backup:run` `backup:list` `backup:verify` `backup:restore` `backup:prune`؛ disk اختصاصی `backups`؛ Facade `Backup`
- **قراردادهای جدید (Contract-First)**: `core/Contracts/Queue` (QueueInterface/JobInterface/ShouldQueue/FailedJobProviderInterface/BatchRepositoryInterface)، `core/Contracts/Filesystem/FilesystemInterface`، `core/Contracts/Mail/TransportInterface`، `core/Contracts/Notification/Notifiable`
- **زبان**: پیام‌های fa+en برای queue/schedule/filesystem(توسعه)/http_client/mail/notification/media/backup
- **`routes/console.php`**: محل ثبت رویدادهای زمان‌بندی (DOC-062 §2) — بارگذاری در boot موتور Schedule

### تغییر یافت
- **QueryBuilder**: `forUpdateSkipLocked()`/`lockForUpdateSkipLocked()` + grammar سه درایور (MySQL 8+/PG 9.5+؛ SQLite خالی — تراکنش خودش اتمیک است) — پایهٔ Pop اتمیک صف (DOC-061 §6)
- **EventDispatcher**: `dispatchNow()` — dispatch همزمان بدون بررسی marker صف‌روند (بدون آن، اجرای رویداد ShouldQueue در Worker حلقهٔ requeue می‌ساخت)؛ حلقهٔ Listenerها به `fireListeners` refactored شد
- **LogManager::default()**: کانال پیش‌فرض **پویا** از config خوانده می‌شود (پیش‌تر در ساخت fix می‌شد — تغییر `log.default` در زمان اجرا بی‌اثر بود)
- **Filesystem config**: disk `backups` افزوده شد

### نتایج
- ۶۹۵ تست / ۳۴۰۶ assertion سبز • ۸۷۷ فایل lint پاک • **۳۹/۳۹ موتور فعال** • S3 SigV4 سبز روی AWS reference vectors • SMTP dialogue کامل با socket-pair scripted (STARTTLS/AUTH/DATA) • Pop اتمیک: دو worker موازی بدون پردازش دوباره • M5 کامل: Queue/Schedule/Filesystem/HttpClient/Mail/Notification/Media/Backup

## [0.4.0] - 2026-10-16 — فاز M4: امنیت و هویت

### افزوده شد
- **TVN-0055 Hash**: `HashManager` با **Argon2id** (64MB/t=4/threads=1) پیش‌فرض + bcrypt fallback (cost≥10 اجباری)، `needsRehash` برای ارتقای تدریجی پارامترها، `info`، Facade `Hash`
- **TVN-0060 Cache**: **۶ درایور** (array/file با flock+atomic-rename/database با UNIQUE-atomic/redis/apcu/memcached) با **fallback خودکار به file بدون crash** وقتی extension نیست، `CacheRepository` با **Stampede Protection** (lock+double-check+serve-stale+retry محدود)، **Tagging** با الگوریتم namespace (flush تگ = identifier جدید)، `CacheLock` اتمیک owner-based (release فقط توسط مالک)، TTL با Clock (تست‌پذیر)، 🔒 payload فقط JSON (ضد Object Injection — حتی فایل tamper شده = miss)، سقف max_key_length/max_value_size، `cache:clear`
- **TVN-0056 Auth**: `AuthManager` چند-Guard (web/api/admin)، `SessionGuard` با **Session Hardening** (regenerate ضد fixation، fingerprint UA+Accept-Language، چرخش توکن CSRF در login، throttle+lockout، پیام یکسان ضد User-Enumeration)، `TokenGuard` (Bearer JWT)، **`JwtEncoder` خودنوشته** با **Algorithm Pinning** (رد `alg=none` و confusion RS256→HS256؛ exp/nbf/iss/aud/jti + leeway)، `TokenManager` با **Refresh Rotation + Reuse Detection** (استفادهٔ مجدد → revoke کل family + رویداد `auth.token.reuse_detected`)، **OTP** یک‌بارمصرف (کد CSPRNG، فقط هش در cache، TTL، rate limit، masked target)، **TOTP RFC 6238** (همهٔ test vectors سبز) + `TwoFactorManager` (secret رمزنگاری‌شده، confirm، کدهای بازیابی هش‌شدهٔ یک‌بارمصرف)، `DeviceManager` (record/list/revoke با fingerprint)، `PasswordBroker` (توکن یک‌بارمصرف هش‌شده، انقضا، ضد enumeration)، `register` با هش خودکار، `logoutOtherDevices` با تأیید رمز، `DatabaseUserProvider` (model ORM یا GenericUser + 🔒 dummy-verify ضد timing leak + **rehash خودکار**)، `AuthenticateMiddleware` (JSON→401 / وب→redirect login)، جداول password_resets/personal_access_tokens/user_devices/two_factor_secrets + migration
- **TVN-0057 RBAC**: `Gate` (define/policy/before/after/allows/denies/check/any/authorize→403/forUser + نگاشت متدهای Policy)، `PermissionResolver` با **الگوریتم مؤثر** (نقش‌ها + سلسله‌مراتب با max_depth و محافظ چرخه + **wildcard** `shop.*` + **delegation** موقت) و کش (invalidate در تغییر)، `RoleManager` (create/assign/remove با انقضا و سقف مدت، grant/revoke permission، delegate/revokeDelegation)، **super-admin bypass** پیکربندی‌پذیر، `PermissionSync` از manifest ماژول‌ها (`rbac:sync`)، `HasRoles` trait (hasRole/hasAnyRole/hasPermission/permissions/can/cannot/assignRole/isSuperAdmin)، **`OwnershipScope`** (Row-Level — ناشناس → `0=1` deny-by-default؛ نشت = ۰)، ۵ جدول tvn_* + migration
- **TVN-0059 Security**: `CsrfManager` (token/field/rotate/verify با hash_equals + **Double-Submit Cookie** رمزنگاری‌شده)، `CsrfVerifyMiddleware` config-driven (except با wildcard + متدهای safe)، `RateLimiter` با **۳ الگوریتم** (Sliding-Log دقیق/Fixed Window/Token Bucket) روی cache + `ThrottleMiddleware` (**۴۲۹ با Retry-After و X-RateLimit-\***), `SecurityHeadersMiddleware` (CSP/HSTS فقط-HTTPS/nosniff/DENY/Referrer/Permissions/COOP/CORP + حذف X-Powered-By و Server)، **`HtmlSanitizer` خالص** (allowlist تگ/attribute/پروتکل، حذف on\* و javascript: حتی entity-encoded، حذف script/iframe/svg با محتوا، escape متن stray، strict escape_all)، **`SsrfGuard`** (۱۲+ CIDR مسدود شامل cloud metadata 169.254.169.254، hostnameهای محلی، IPv6 براکت‌دار، userinfo trick، بررسی **همهٔ** IPهای resolve شده ضد DNS rebinding، allowlist دامنه، **resolvePinned** برای اتصال مستقیم)، `InputNormalizer` (NFKC + حذف bidi/Trojan-Source/zero-width با **حفظ ZWNJ فارسی** + ارقام عربی/فارسی→لاتین + ی/ک + null byte)، `Security::sign/validateSignature` (سازگار با signed-URL روتر، expires)، Facade `Security`
- **TVN-0058 Audit**: `Auditor` با **زنجیرهٔ هش HMAC** (`hash[i] = HMAC(key, hash[i-1] | payload)` — payload کانونیکال)، GENESIS hash، `verify()` با cursor (حافظهٔ ثابت — ۱۰۰۰۰ رکورد سریع)، تشخیص دستکاری **و حذف** رکورد، کلید audit جدا از APP_KEY (config یا HKDF fallback)، `exclude_fields` → `[REDACTED]` پیش از hash (PII نشت نمی‌کند)، `AuditObserver` خودکار مدل‌ها (created/updated/deleted با diff دقیق از `previousOriginal`)، Attribute `#[Observed(fields:…)]`، export (خودش ممیزی می‌شود)/prune/find، CLI `audit:verify` `audit:export` `audit:prune` `audit:show`، جدول tvn_audit_logs + migration
- **Outbox (وعدهٔ M1 — ADR-0009)**: `DatabaseOutbox` روی `tvn_outbox`/`tvn_inbox` — store درون تراکنش کسب‌وکار (**rollback → ۰ رویداد**، AC-EVT-05)، claim اتمیک با owner token (**دو worker → هر رویداد یک‌بار**، AC-EVT-07)، backoff نمایی + max_attempts + failed، `releaseStale` (crash recovery)، `event:outbox-relay`
- **TVN-0022 (تکمیل نهایی)**: درایور `redis` نشست روی موتور cache (`CacheHandler` — JSON-only + TTL)
- **Facades**: `Hash` `Cache` `Auth` `Gate` `Security` `Audit` • **مثال**: `examples/auth/login-rbac.php` (خروجی کلیدی M4 — register→login→RBAC→Gate→JWT→reuse-detection→TOTP→audit tamper-detect)

### تغییر یافت
- `Crypt` اکنون رسماً `EncrypterInterface` را پیاده می‌کند (contract-first)
- `GenericUser` غیرfinal (گسترش با trait HasRoles)
- `Model`: `changes`/`previousOriginal` برای diff دقیق Observer/Audit (پس از syncOriginal هم معتبر)
- `Request`: accessor محافظت‌شدهٔ `postBag()` (برای FormRequest::merge)

### نتایج
- ۵۴۴ تست / ۲۷۳۶ assertion سبز • TOTP ۱۰۰٪ RFC 6238 • JWT alg-confusion/none رد • reuse detection + family revoke • hash-chain tamper/delete تشخیص • SSRF metadata/rebinding رد • ۶۷۶ فایل lint پاک • gate ممنوعه‌ها سبز • ۳۱/۳۱ موتور فعال

## [0.3.0] - 2026-10-16 — فاز M3: داده

### افزوده شد
- **TVN-0030 Database**: `ConnectionManager` چنداتصال (default/tenant)، `Connection` با **Lazy PDO + Reconnect خودکار** («gone away»)، Prepared Statement ۱۰۰٪ (`EMULATE_PREPARES=false` + binding تایپ‌دار null/bool/int/DateTime)، `QueryBuilder` کامل (select/join/nested-where/whereIn+subquery/whereBetween/whereExists/whereRaw/group/having/order/limit/lock/union/CTE/aggregate)، `Grammar` سه درایور (MySQL/PostgreSQL/SQLite — wrap با prefix، allowlist operator/direction)، upsert و insertOrIgnore در هر ۳ گویش، `TransactionManager` با **Savepoint تودرتو + Deadlock retry** (backoff 50/150/450ms+jitter)، `QueryLog` (slow-query + **N+1 detection** با نرمال‌سازی SQL + mask bindings)، Read/Write Splitting + sticky، chunk/chunkById/lazy/cursor (حافظهٔ ثابت)، `Ulid` (ADR-0006) در `core/Support`
- **TVN-0031 Schema**: `Blueprint` با ۳۵+ نوع ستون (ulid/uuid/enum/json/money-decimal/timestamps(precision)/softDeletes/morphs/foreignId/rememberToken)، `ColumnDefinition` سیال + freeze، `ForeignKeyDefinition` (onDelete/onUpdate با allowlist)، DDL سه درایور (MySQL inline KEY + engine/charset، PG jsonb/serial/GIN-fulltext/check-enum، SQLite numeric/check/autoincrement)، ALTER (add/drop/rename column + index؛ FK-add در SQLite با skip امن)، introspect/hasTable/hasColumn/getColumnType/getTables، dropAllTables (migrate:fresh)
- **TVN-0032 Migration**: `Migrator` با **FileLock اتمیک** (flock + TTL ضد lock مرده)، batch numbering، rollback ترتیب معکوس، dry-run، status، fresh با **guard در production** (بدون force → رد)، مسیرهای خودکار (database/ + engines/* + modules/* + addPath ماژول‌ها)، `MigrationRepository` (tvn_migrations با prefix-aware)، کشف ماژول مالک؛ CLI: `migrate` `migrate:rollback` `migrate:status` `migrate:fresh` `migrate:refresh` `migrate:install`
- **TVN-0033 ORM**: `Model`Active Record — keyType ulid/uuid/bigint با **تولید کلید خودکار**، fillable/guarded + `MassAssignmentException` در حالت strict، Castهای داخلی (int/float/bool/array/json/datetime/immutable_datetime/date/timestamp/decimal/**money:CUR** (ADR-0013)/​**encrypted**/**hashed**/enum/custom CastInterface)، accessor/mutator (`getXAttribute`/`setXAttribute`)، dirty tracking (getChanges/getOriginal/isDirty)، timestamps خودکار، **Soft Delete** (withTrashed/onlyTrashed/restore/forceDelete/trashed)، **۱۰ نوع رابطه** (HasOne/HasMany/BelongsTo/BelongsToMany با attach/detach/sync/toggle+withTimestamps/HasManyThrough/HasOneThrough/MorphOne/MorphMany/MorphTo/MorphToMany) + `MorphMap`، **Eager Loading با الگوریتم DOC-050 §4.1** (nested + constraint + با exactly ۲ کوئری — N+1=۰)، withCount (correlated subquery)، lazy loading، Observer (همهٔ رویدادها با لغو via false)، `dispatchesEvents` → Domain Event، Global/Local Scope، replicate/refresh/touch/load، toArray/toJson با hidden/visible/appends، serialize پول/enum/datetime؛ الگوی **Repository + Domain Object** (بدون extends Model — قرارداد `RepositoryInterface`)
- **TVN-0034 Pagination**: `Paginator` (page-based + meta/links استاندارد DOC-053 §5 + links() HTML RTL + clamp max_per_page=۱۰۰)، `CursorPaginator` (multi-column cursor با base64url + tie-breaker یکتا)، `keysetPaginate` (afterId)
- **TVN-0035 Validation**: **۹۲ قاعدهٔ داخلی** (فراتر از ۸۵ قاعدهٔ DOC-051): presence/type/size/format/compare/set/file/database(unique/exists/exists_multiple/unique_composite)/security(honeypot/no_html/safe_url/password/strong_password/not_compromised با blacklist constant-time)/math-money(decimal/money/percent)/advanced(prohibited/distinct/exclude_if/nested/each) + **قواعد ایرانی 🇮🇷**: iran_mobile (با نرمال‌سازی +98/98/ارقام فارسی)، iran_national_code (checksum وزن ۱۰-i)، iran_sheba (ISO 7064 mod-97)، iran_card_number (Luhn)، iran_postal_code، iran_company_id، jalali_date (کبیسه breaks-based)، persian_digits؛ wildcard expansion (`items.*.qty`)، **ReDoS guard** (طول الگو + رد nested quantifier)، allowlist شناسه در قواعد DB، MessageBag کامل، پیام‌های fa+en با variant اندازه‌ای و placeholder، FormRequest (چرخهٔ authorize→prepare→validate→422/403 + resolve خودکار در ControllerResolver)، **Attribute-based DTO validation** (`#[Required, Email, Between(18,120)]` + `Validator::forDto`)، Rule objectها (Rule::in/unique/exists) و extend
- **TVN-0022 (تکمیل)**: درایور `database` نشست — `DatabaseHandler` (JSON-only + upsert اتمیک + GC) + migration جدول `sessions`
- **Contracts (DOC-014)**: `Database\` (Connection/QueryBuilder/Grammar/TransactionManager/SchemaBuilder/Migration/Paginator)، `ORM\` (Model/Cast/Scope/Observer/Repository)، `Validation\` (Validator/Rule)، `Cache\` (Store/Lock/LockProvider/Repository)، `Auth\` (Authenticatable/Guard/StatefulGuard/UserProvider)، `Authorization\` (Gate/Response)، `Encryption\HasherInterface`
- **Facades**: `DB` `Schema` `Validator` • **مثال**: `examples/crud/blog-crud.php` (خروجی کلیدی M3 — CRUD سرتاسری با Schema/ORM/Validation/Pagination/Transaction روی SQLite)
- **بنچمارک**: `engines/orm/benchmarks/hydrate.bench.php`

### تغییر یافت
- `Request` غیرfinal شد (گسترش فقط توسط `FormRequest` — DOC-051 §5) + `fromRequestInto`/`postBag` محافظت‌شده
- `ControllerResolver`: resolve خودکار FormRequest (authorize+validate) در method injection
- `SessionManager`: درایور database فعال شد (redis در M4)

### نتایج
- ۴۴۵ تست / ۲۱۸۹ assertion سبز • Hydrate ۱۰۰۰ رکورد **۱٫۳ms** (هدف ≤۲۵ms) • حافظهٔ ۱۰۰۰ مدل ۶MB (هدف ≤۸MB) • eager loading دقیقاً ۲ کوئری (N+1=۰) • SQL Injection fuzzing: ۲۰۰ payload / ۷۲۲ assertion سبز • ۵۷۴ فایل lint پاک • gate ممنوعه‌ها سبز

## [0.2.0] - 2026-10-15 — فاز M2: وب

### افزوده شد
- **TVN-0020 HTTP**: `Request`/`Response` کاملاً immutable با Bagها، `Request::capture/create`، پارس JSON امن (سقف حجم/عمق)، تشخیص Request Smuggling (CL+TE→400)، محافظ Header Injection، `HttpKernel` (scope per-request + terminate با flushScoped)، `ResponsePreparer` (ETag/xxh128، If-None-Match→304، gzip/deflate، Content-Length)، `ResponseFactory` (json/view/file/download/stream/streamDownload/problem RFC 9457)، `Redirector` (با محافظ Open Redirect)، `StreamedResponse`، `UploadedFile` (mime با magic bytes + move امن)، `RequestStack`
- **Middleware** (۱۱ عدد): TrustProxies (CIDR v4/v6 خالص، پیش‌فرض بسته)، MaintenanceMode (503+Retry-After)، SecurityHeaders (CSP/HSTS/nosniff/… + حذف X-Powered-By و Server)، RequestSizeLimit (413)، SetLocale (session→query→Accept-Language negotiation)، TraceContext (W3C traceparent)، EncryptCookies، StartSession، ShareErrorsFromSession، CsrfVerify (419)، Cors
- **TVN-0021 Router**: Compiled Trie (static→param→wildcard با specificity قطعی)، `Route` fluent (name/middleware/where*/defaults/domain)، inline regex و binding-field در uri، `RouteRegistrar` + nested group، `resource`/`apiResource`، fallback (GET-only مثل Laravel)، domain routing با `{tenant}`/`*`، `UrlGenerator` (route/url/asset/current/previous + Signed URL با expires و HMAC)، `ControllerResolver` (تزریق Request/route params با cast به int/enum + container)، Route Cache (`route:cache` با reject Closure)، ReDoS guard دقیق، تطبیق Unicode (اسلاگ فارسی)، 405 با Allow header
- **TVN-0022 Session** (نسبی): `Session` (get/put/flash/reflash/keep/oldInput/regenerate/invalidate/token/previousUrl)، درایورهای `file` (flock+atomic+GC lottery+0600) و `cookie` (stateless رمزنگاری‌شده) و `array`، Session Hardening: ID با CSPRNG، رد ID نامعتبر (fixation)، idle/absolute timeout، fingerprint (IP+UA)، JSON-only serialization (ضد Object Injection)، کوکی HttpOnly+SameSite=Lax+Secure(prod)؛ **CSRF-ready** با توکن و hash_equals
- **TVN-0023 Cookie**: `Cookie` VO (پیش‌فرض‌های امن)، `CookieJar` (make/forever/queue/forget)، `EncryptCookiesMiddleware` (decrypt ورودی/encrypt خروجی؛ tamper → null بی‌صدا)
- **TVN-0024 View**: موتور `.tv.php` — `TemplateCompiler` شش‌pass با **پارسر دستی directive** (بدون regex بازگشتی → بدون JIT-stack exhaustion؛ ایمیل/`@@`/`@{{}}` امن)، auto-escape همهٔ `{{ }}` (ENT_QUOTES|ENT_HTML5 + fast-path ۰٫۲µs)، `{!! !!}` raw، `Escaper` (html/attr/js JSON_HEX/url/css) + `SafeString`، layout (@extends/@section/@yield تودرتو)، @push/@stack، @include/If/When/First، **Component** (`<x-alert :prop="$x">` + `<x-slot>` + `@props` + `ComponentAttributeBag`)، @csrf/@method/@json/@trans/@choice/@can/@verbatim/@php، directive سفارشی، `ViewFinder` با override سلسله‌مراتبی و namespace ماژول‌ها، کش کامپایل با hash(path|mtime|version)، محافظ Path Traversal و max-depth، `view:cache` با verify
- **TVN-0025 I18n**: `Translator` (dot-key با namespace `shop::`، placeholder، fallback chain fa_IR→fa→en، missing-key log)، `choice()` با فرم‌های `{0}|{1}|[2,*]` و index-based، `PluralRules` (fa/en/ar/ru)، Accept-Language negotiation، `PersianFormatter` (ارقام/جداکنندهٔ فارسی، minor-units پول)، helpers: `trans()/__()/trans_number()/jalali()/is_rtl()`، lang files هسته در fa+en (۱۹ دامنه پیام)
- **TVN-0026 Jalali**: پورت **breaks-based jalaali-js** (دقیق‌تر از فرمول ۲۸۲۰‌سالهٔ مستند — اصلاحیه در DOC-070 ثبت شد)، Julian Day bridge، `JalaliDate` (immutable: parse با ارقام فارسی، format مثل date() با ارقام فارسی/لاتین، add/sub/diff/start-endOfMonth/dayOfYear/weekOfYear)، نام ماه‌ها (ایرانی/افغان) و روزها، Hijri табular؛ **۱۰۰٪ تطبیق با intl (۸۰۰۰ نمونه) + ۱۰۰۰۰ roundtrip + همهٔ vectors**
- **TVN-0027 CORS**: preflight OPTIONS→204، reflect origin، `Vary: Origin`، wildcard `*.example.com`، خطای پیکربندی برای `*`+credentials
- **TVN-0054 Crypt (زودهنگام — پیش‌نیاز cookie/session)**: AES-256-GCM با قالب `v{n}:iv:ct:tag`، `KeyRing` با versioning/rotation، HKDF (با fallback RFC 5869)، HMAC + verify ثابت‌زمان، AAD binding، `key:generate`
- **Console**: `serve` (php -S با router script خارج از public)، `route:list/cache/clear/match`، `view:cache/clear`، discovery خودکار دستورات موتورها
- **Entry/دمو**: `public/index.php` کامل (handle→send→terminate)، `bin/server-router.php`، `routes/web.php` نمونه (welcome/hello یونیکد/time جلالی/session counter/xss-test/fallback 404)، layout+views فارسی RTL، بنچمارک‌های router/kernel/view

### تغییر یافت
- `ResponseInterface`: تفکیک `header()` (getter) از `withHeader()` (setter) + `send(): void` (اصلاحیهٔ DOC-014 — الگوی sendAndTerminate)
- `Router::fallback` فقط GET/HEAD (حفظ ۴۰۵ خالص — مانند Laravel)
- `ExceptionHandler`: انتشار هدرهای HttpException (Allow) روی پاسخ؛ CliRenderer با status واقعی
- gate کیفیت: `proc_open` آرایه‌ای به‌جای shell_exec برای lint ویوها؛ استخراج کلیدهای `__` قبل از extract در رندر ویو

### نتایج
- ۳۰۲ تست / ۷۷۹ assertion سبز • سربار درخواست ۰٫۱ms • route match ۲٫۴µs/۱۰۰۰ روت • compile ویو ۰٫۴۳ms • ۴۱۱ فایل lint پاک • gate ممنوعه‌ها سبز

## [0.1.0] - 2026-10-15 — فاز M1: هستهٔ صفر‌وابستگی

### افزوده شد
- **TVN-0001** Autoloader خودنوشته: PSR-4 + Wildcard (`engines/*/src`) + ClassMap authoritative + امنیت path-traversal + `core/bootstrap.php` (DOC-027)
- **TVN-0002** Container: Auto-Wiring با ReflectionCache (dump/load)، Contextual Binding، Singleton/Scoped/Factory/Transient، Tagging تنبل، Extender، تشخیص چرخه با مسیر، Union/DNF قطعی، Method Injection (`call`)، LazyProxy بدون eval، ScopeManager
- **TVN-0003** Config: EnvLoader بدون putenv، EnvRepository (اولویت $_SERVER→.env→default)، Repository با dot-notation + LRU + freeze + masking، ConfigLoader با اولویت engine<module<app، ConfigCacher اتمیک، SchemaValidator
- **TVN-0004** Event: EventDispatcher با Priority قطعی، Wildcard Segment-Trie، Subscriber، Hierarchy (interface/parent)، Stoppable، Allowlist امنیتی، Fake/Assert برای تست (Outbox در M3)
- **TVN-0005** Pipeline: الگوی Onion عمومی، پارامتر (`auth:web,admin`)، alias، terminate، max_depth
- **TVN-0006** Error: ExceptionHandler (report/render/map/renderable/dontReport/level)، ErrorHandler (E_* → ErrorException)، Deprecation→CI failure، LogMasker، RefCodeGenerator (Crockford base32)، Renderers: JSON RFC 9457 / HTML امن / CLI / Debug غنی
- **TVN-0007** Log: LogManager چندکاناله (daily/json/audit/query/queue/security/deprecations/stderr/stack/null/array)، LogRecord readonly، Processorها (mask/trace/identity/memory/runtime/backtrace/environment)، Buffer، چرخش روزانه با retention، sanitization علیه Log Injection
- **TVN-0008** Compat: Compat (report/driver/require/forceFallbacks)، Utf8، **Decimal بدون bcmath** (دقت ۱۸ رقم — تطبیق با Python Decimal)، ZipWriterPure، MagicBytesDetector، jsonValidate fallback
- **TVN-0009** Optimizer: Cacherها (config/autoload)، Optimizer با زمان‌بندی، atomic write
- **TVN-0010** Module: ManifestParser با schema validation، ModuleRegistry (کشف engines+modules)، TopologicalSorter (Kahn قطعی)، ModuleManager (enable/disable با پایداری وضعیت)
- **TVN-0011** Console: SignatureParser، ArgvInput، Output رنگی (NO_COLOR)، Command (ask/confirm/choice/table)، ConsoleKernel با discovery، `bin/tvn` + ۱۳ دستور داخلی
- **Test Engine (حداقلی)**: TestCase + Assertionها + expectException + TestRunner بومی (`tvn test`)
- Application/ApplicationBuilder + ۸ Bootstrapper (idempotent) + Facadeها (App/Config/Event/Log/Module) + ServiceProvider
- قراردادها: Container/Config/Events/Log/Pipeline/Console/Support در `core/Contracts/`
- سلسله‌مراتب استثناها (DOC-013) با کدهای TCR/TCF/TEV/TKR/...
- ابزار کیفیت: `tools/scripts/lint.sh`، `check-forbidden.php` (gate موارد ممنوعه)، بنچمارک container و autoload
- ساختار پروژه مطابق DOC-080 + `tavaan.json` + `.env.example` + manifest موتورهای ۱۱گانه

### نتایج
- ۱۵۵ تست / ۳۶۲ assertion سبز • بوت ~۷٫۷ms • classmap ۹۰٫۶٪ سریع‌تر • صفر eval/putenv
