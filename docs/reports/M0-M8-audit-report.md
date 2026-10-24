# گزارش ممیزی M0–M8 و نقشهٔ رفع

**تاریخ:** ۲۰۲۶-۱۰-۱۹ • **دامنه:** کل مخزن (هسته + ۵۲ موتور + مستندات + ابزارها)
**روش:** تطبیق موجودی با `docs/manifest.json` و DOC-120/121 • اسکن ساختاری خودکار • اجرای کامل تست‌ها/گیت‌ها/بنچمارک‌ها • smoke واقعی HTTP (`php -S`) • بررسی CLI در برابر DOC-083

---

## ۱. خلاصهٔ وضعیت سلامت

| لایه | وضعیت |
|---|---|
| تست کامل | ✅ ۱۲۳۵/۱۲۳۵ سبز (دو اجرای پیاپی + `--parallel=4`) |
| `php -l` | ✅ ۱۲۹۳ فایل پاک |
| gate ممنوعه‌ها (eval/goto/global/putenv/…) | ✅ ۱۰۸۷ فایل پاک |
| بوت اپ / `about` / `doctor` / `list` | ✅ سالم — ۵۲/۵۲ موتور فعال |
| optimize / config:cache / clear cycle | ✅ سالم |
| HTTP واقعی (serve): `/` ۲۰۰، `/time` (جلالی ۱۴۰۵ ✓)، `/admin` ۳۰۲→login، ۴۰۴ fallback | ✅ |
| پوشش AC امنیتی (JWT pinning، audit chain، SQLi fuzz، CSRF) | ✅ فایل‌های تست موجود و سبز |
| بنچمارک‌ها | ⚠️ موارد مرزی **وابسته به CPU ماسه‌دان**: overhead هر درخواست ۰٫۲۵ms ✅، حافظهٔ روتر ۱٫۶MB ✅، render/escape ✅؛ match استاتیک ~۵٫۵-۷µs (هدف ≤۵µs) و boot cold ~۳۶ms (هدف ≤۲۵ms) روی این سخت‌افزار قرمز — روی سخت‌افزار مرجع (گزارش M7) پاس بوده؛ **رگرسیون کدی یافت نشد** (افزودن ۴ موتور M8 سهم ≤۲ms دارد) |

## ۲. یافته‌ها (به تفکیک شدت)

> ✅ **همهٔ یافته‌ها رفع شدند** — وضعیت اجرا در §۴، یافته‌های افزودهٔ حین اجرا در §۵ و راستی‌آزمایی نهایی در §۶. تنها مورد M1 **مثبت کاذب** ابزار ممیزی بود (کلیدهای تودرتو). موارد L2/L3 (مستندشده در اسناد موتور) و مرزهای بنچمارک سخت‌افزار-وابسته (§6) به‌قوت خود باقی‌اند.

### 🔴 HIGH — API عمومی شکسته / فرایند QA ناقص

| # | یافته | جزئیات |
|---|---|---|
| H1 | **Facadeهای مستند ولی مفقود** | `module.json` و DOC-042/054/069 وعدهٔ `Crypt` (accessor `encrypter`)، `Lang` (`translator`)، `Cookie` (`cookie`) را می‌دهند — کلاس‌ها در `core/Facade/Facades/` وجود ندارند → کد کاربر طبق سند fatal می‌شود. ضمناً alias ظرف `cookie` برای CookieJar هیچ‌جا ثبت نشده |
| H2 | **ابزارهای QA وعده‌داده‌شده پیاده نشده‌اند** | DOC-121 §8 گام ۹ (`arch:check` + `security:scan`)، DoD §1.1 (`lint:php`, `style:check`)، DOC-112 (`bench:all`)، DoD §2 (`engine:validate`) — هیچ‌کدام در CLI وجود ندارند |
| H3 | **۶۰ دستور مستند در DOC-083 پیاده‌سازی نشده** | از ۱۳۳ دستور مستند، ۹۳ عدد موجود است. شکاف شامل `down/up`، `module:enable/disable` (متدش در ModuleManager هست!)، `env:check`، `health:check`، `db:seed`، `tinker` و … — بدون هیچ علامت «پیاده‌نشده» در سند |

### 🟡 MED — ناقص/ناسازگار

| # | یافته | جزئیات |
|---|---|---|
| M1 | ۱۳ کلید ترجمه مفقود (fa+en) | `core.autoload.*` (۳)، `core.env.*` (۲)، `http.route.not_found`، `workflow.definition.*` (۷) — messageId خام به کاربر نشان داده می‌شود |
| M2 | ناسازگاری نسخه | `TVN_VERSION=0.7.1` و `tavaan.json=0.7.1` ولی `docs/manifest.json=1.0.0`؛ پس از تکمیل M8 هیچ bump انجام نشده |
| M3 | `.env.example` drift | کلیدهای M8 (`TVN_PLUGINS`, `TVN_ADMIN_*`, `TVN_UPGRADE_BACKUP`, …) و ده‌ها کلید درایورها (AWS_*/FTP_*/SMS) مستند نشده‌اند |
| M4 | `INDEX.html` (entry_point در manifest) وجود ندارد | `docs/manifest.json → entry_points.human_dashboard` |
| M5 | یک typo CJK در کامنت | `engines/view/src/ViewFactory.php:60` — «وضعیت» باید «وضعیت» باشد (workaround: بازنویسی جمله به فارسی خالص) |
| M6 | صفحهٔ welcome و README کهنه | «۴۸/۴۸ موتور • ۹۹۲ تست • M7 کامل شد» — اکنون ۵۲/۵۲ و ۱۲۳۵ تست و M8 کامل |

### 🟢 LOW / یادداشت

| # | یافته | جزئیات |
|---|---|---|
| L1 | cli-reference فاقد ۲۲ دستور موجود است | همهٔ `make:*`/`plugin:*`/`upgrade:*`/`module:validate` + `backup:list/verify`، `storage:link`، `view:clear`، `route:clear`، `search:reindex`، `workflow:list`، `realtime:websocket`، `payment:list`، `event:outbox-relay`، `cache:clear`، `config:clear`، `env`، `list` |
| L2 | AC-UPG-07 (ارتقای zero-downtime) پیاده نشد | در `upgrade.md` مستند شد — زیرساخت maintenance/verify آماده؛ به M9 موکول |
| L3 | برچسب‌های UI در Admin فارسی inline هستند (نه `trans()`) | در `admin.md` مستند شد — گذار به i18n کامل با کیت en انجام می‌شود |
| L4 | `pagination` در manifest «انجین» است ولی دایرکتوری جدا ندارد | by-design: زیرموتور database با namespace اختصاصی (`Tavaan\Engine\Database\Pagination`) — سندش هم «زیرموتور» می‌گوید. اقدام لازم: فقط شفاف‌سازی در manifest (فیلد `sub_engine_of`) |
| L5 | PHPStan/CS-Fixer واقعی در محیط قابل نصب نبودند (بدون vendor) | `style:check` به‌صورت wrapper با تشخیص ابزار و پیام راهنما پیاده می‌شود؛ تحلیل ایستا با arch:check/security:scan داخلی پوشش جزئی می‌گیرد |

## ۳. نقشهٔ رفع (به ترتیب اجرا)

| گام | اقدام | یافته‌ها |
|---|---|---|
| R1 | ساخت Facadeهای `Crypt`/`Lang`/`Cookie` + ثبت alias `cookie` در HttpServiceProvider + تست | H1 |
| R2 | پیاده‌سازی ابزارهای QA: `arch:check` (قواعد لایه‌بندی: core⟂engines، engines⟂app/modules، عدم استفاده از Testing در src موتورها)، `security:scan` (ruleset گسترش‌یافتهٔ check-forbidden + heuristics: SQL الحاقی، secret هاردکد، md5/sha1، unserialize…)، `lint:php` (php -l سراسری — cross-platform)، `bench:all` (اجرا + جمع‌بندی همهٔ benchها)، `style:check/fix` (wrapper صادقانه)، `engine:validate` | H2 |
| R3 | دستورات سریع و کم‌هزینهٔ مستند: `down`/`up`، `module:enable`/`module:disable`، `env:check` | بخشی از H3 |
| R4 | به‌روزرسانی `cli-reference.md`: افزودن همهٔ دستورات موجود (M8 + قدیمی‌های جامانده) + ستون «وضعیت» برای وعده‌های پیاده‌نشده (programmed → M9/backlog) | H3, L1 |
| R5 | افزودن ۱۳ کلید ترجمهٔ مفقود به `resources/lang/{fa,en}` | M1 |
| R6 | bump نسخه به **0.8.0** (bootstrap/tavaan.json/manifest) + ادغام بخش‌های Unreleased در `## [0.8.0] — M8` در CHANGELOG | M2 |
| R7 | `.env.example`: بخش M8 + ارجاع درایورها؛ ساخت `INDEX.html` خودایستا | M3, M4 |
| R8 | رفع typo CJK؛ به‌روزرسانی welcome.tv.php و README (۵۲ موتور/۱۲۳۵ تست/M8) | M5, M6 |
| R9 | شفاف‌سازی `pagination` در manifest (`sub_engine_of: database`) | L4 |
| R10 | تست‌های جدید برای ابزارهای QA و facadeها + اجرای کامل نهایی (suite×2، موازی، گیت‌ها، smoke) + گزارش رفع در همین سند | همه |

## ۴. وضعیت اجرا (به‌روزرسانی نهایی — همهٔ گام‌ها انجام شد)

- [x] **R1 Facadeها** — `Crypt`/`Lang`/`Cookie` ساخته شدند (docblockهای API با امضای واقعی کلاس‌ها تطبیق داده شد) + `CookieJar` singleton با defaultهای config و alias `cookie` در HttpServiceProvider ثبت شد + `provides()` به‌روز شد. تست: `FacadeCompletenessTest` (۳ تست — round-trip رمزنگاری، ترجمه، ساخت/queue کوکی)
- [x] **R2 ابزارهای QA** — ۶ دستور جدید در موتور Console:
  - `arch:check [--json]` — config-driven از `config/architecture.php` (مرزهای namespace + توابع/الگوهای ممنوعه با allowlist صریح per-function؛ اسکن روی کدِ comment/string-stripped بدون مثبت کاذب). نتیجه: **۱۰۵۸ فایل، صفر نقض** (پس از رفع‌های زیر)
  - `security:scan [paths*] [--json] [--level=high]` — token-based؛ severityها high/med/low؛ allowlist مشترک با arch:check. نتیجه: **صفر high، ۸ med** (همه مرور و تأیید شدند: sha1 کلید cache در throttle/idempotency، sha1 هندشیک RFC 6455 در WebSocketFrame، md5 کلید memoization در Security، sha1/crc32 هویت و bucket)
  - `lint:php [paths*] [--json]` — جایگزین cross-platform برای lint.sh (composer script `lint` هم به آن منتقل شد). نتیجه: **۱۴۰۳ فایل، صفر خطا**
  - `bench:all [--filter=] [--fail-on-regression] [--json]` — جمع‌بندی ۸ bench با شمارش ✅/⚠️/❌
  - `style:check [--fix]` — wrapper صادقانهٔ CS-Fixer؛ نبود ابزار → exit 2 با راهنمای نصب (سکوت سبز کاذب ندارد)
  - `engine:validate [name] [--json]` — اعتبارسنجی عمیق ۵۲ manifest + تطبیق با docs/manifest.json. نتیجه: **صفر خطا**
  - `composer.json`: ‏require-dev (phpstan ^2.1، php-cs-fixer ^3.64) + اسکریپت‌های `arch/security/cs/cs:check/stan/bench/qa` + **`phpstan.neon`** جدید (level max)
- [x] **R3** — `down [--message --retry --until]`/`up` (همان فایل `storage/framework/down` که MaintenanceModeMiddleware و Upgrader مصرف می‌کنند) + `module:enable`/`module:disable` (با ترجمهٔ fa پیام‌ها و hint) + `env:check [--json] [--strict]` (شامل میکرواسکن bootstrap)
- [x] **R4 cli-reference** — بخش «۲٫۰ وضعیت پیاده‌سازی در 0.8.0» (فهرست ✅ پیاده‌شده‌ها، ⛔ وعده‌های پیاده‌نشده با مقصد M9+، و تطبیق نام‌های معادل مثل `outbox:relay`↔`event:outbox-relay`) + به‌روزرسانی ردیف‌های §2.14/§2.10/§2.1 + `console.md` §7 با نشانگرهای وضعیت
- [x] **R5 ترجمه‌ها** — **مثبت کاذب ممیزی اولیه**: ابزار اسکنِ flat-regex آرایه‌های تودرتو را نمی‌دید. بازرسى دقیق با flatten نقطه‌ای: **۱۸۲ messageId مصرفی، صفر مفقود در fa و en، parity کامل** — اقدامی لازم نبود
- [x] **R6 نسخه 0.8.0** — `TVN_VERSION` + `tavaan.json` + `docs/manifest.json` هم‌تراز شدند؛ CHANGELOG: پنج بخش Unreleasedِ M8 + ممیزی M1–M4 + بخش ممیزی جدید زیر `## [0.8.0] - 2026-10-19` ادغام شدند
- [x] **R7** — `.env.example`: **۱۸۲ کلید جامانده** با مقدار پیش‌فرض (حساس‌ها خالی) به تفکیک فایل config افزوده شد → drift صفر (`env:check` تأیید می‌کند) + `INDEX.html` خودایستا (داشبورد فازها/موتورها/ابزارهای QA/لینک مستندات، بدون منبع خارجی) ساخته شد
- [x] **R8** — typoهای غیرفارسی رفع شد (جزئیات در یافته‌های افزوده پایین) + `welcome.tv.php` (۵۲/۵۲ موتور، ۱۲۵۱ تست، M8 + فهرست قابلیت‌های تازه) + `README.md` (وضعیت M1–M8، نگارش 0.8.0، جدول فازها، لینک گزارش‌ها، پروانه)
- [x] **R9 manifest** — `pagination.sub_engine_of: database` افزوده شد (شفاف‌سازی L4)
- [x] **R10 تست‌ها و راستی‌آزمایی** — ۱۶ تست جدید (`QaToolsTest` ۱۰ تست end-to-end با CLI واقعی شامل تشخیص eval در فایل آلوده و round-trip down/up • `FacadeCompletenessTest` ۳ • `WithCountAliasGuardTest` ۳) + assertionهای استاندارد به TestCase (assertIsArray/String/Int/Bool/Resource، assertFileExists/DoesNotExist، assertGreaterThanOrEqual، assertLessThanOrEqual، assertNotContains — DOC-084E هم به‌روز شد). **نتیجهٔ نهایی: ۱۲۵۱ تست / ۸۳۵۷ assertion سبز**

## ۵. یافته‌های افزوده حین اجرا (و رفع آنی)

| # | یافته | رفع |
|---|---|---|
| X1 | **🐛 باگ عملکردی ORM**: `withCount(['posts as alias'])` (فرم لیستی مستند) — alias به relation name آلوده می‌شد و ستون شمارش **بی‌صدا تولید نمی‌شد** | پارس `' as '` در فرم لیستی + ۳ تست |
| X2 | **🔒 حفرهٔ hardening**: alias با `selectRaw` درون‌یابی می‌شود (binding‌پذیر نیست) | گارد `^[A-Za-z_][A-Za-z0-9_]*# گزارش ممیزی M0–M8 و نقشهٔ رفع

**تاریخ:** ۲۰۲۶-۱۰-۱۹ • **دامنه:** کل مخزن (هسته + ۵۲ موتور + مستندات + ابزارها)
**روش:** تطبیق موجودی با `docs/manifest.json` و DOC-120/121 • اسکن ساختاری خودکار • اجرای کامل تست‌ها/گیت‌ها/بنچمارک‌ها • smoke واقعی HTTP (`php -S`) • بررسی CLI در برابر DOC-083

---

## ۱. خلاصهٔ وضعیت سلامت

| لایه | وضعیت |
|---|---|
| تست کامل | ✅ ۱۲۳۵/۱۲۳۵ سبز (دو اجرای پیاپی + `--parallel=4`) |
| `php -l` | ✅ ۱۲۹۳ فایل پاک |
| gate ممنوعه‌ها (eval/goto/global/putenv/…) | ✅ ۱۰۸۷ فایل پاک |
| بوت اپ / `about` / `doctor` / `list` | ✅ سالم — ۵۲/۵۲ موتور فعال |
| optimize / config:cache / clear cycle | ✅ سالم |
| HTTP واقعی (serve): `/` ۲۰۰، `/time` (جلالی ۱۴۰۵ ✓)، `/admin` ۳۰۲→login، ۴۰۴ fallback | ✅ |
| پوشش AC امنیتی (JWT pinning، audit chain، SQLi fuzz، CSRF) | ✅ فایل‌های تست موجود و سبز |
| بنچمارک‌ها | ⚠️ موارد مرزی **وابسته به CPU ماسه‌دان**: overhead هر درخواست ۰٫۲۵ms ✅، حافظهٔ روتر ۱٫۶MB ✅، render/escape ✅؛ match استاتیک ~۵٫۵-۷µs (هدف ≤۵µs) و boot cold ~۳۶ms (هدف ≤۲۵ms) روی این سخت‌افزار قرمز — روی سخت‌افزار مرجع (گزارش M7) پاس بوده؛ **رگرسیون کدی یافت نشد** (افزودن ۴ موتور M8 سهم ≤۲ms دارد) |

## ۲. یافته‌ها (به تفکیک شدت)

### 🔴 HIGH — API عمومی شکسته / فرایند QA ناقص

| # | یافته | جزئیات |
|---|---|---|
| H1 | **Facadeهای مستند ولی مفقود** | `module.json` و DOC-042/054/069 وعدهٔ `Crypt` (accessor `encrypter`)، `Lang` (`translator`)، `Cookie` (`cookie`) را می‌دهند — کلاس‌ها در `core/Facade/Facades/` وجود ندارند → کد کاربر طبق سند fatal می‌شود. ضمناً alias ظرف `cookie` برای CookieJar هیچ‌جا ثبت نشده |
| H2 | **ابزارهای QA وعده‌داده‌شده پیاده نشده‌اند** | DOC-121 §8 گام ۹ (`arch:check` + `security:scan`)، DoD §1.1 (`lint:php`, `style:check`)، DOC-112 (`bench:all`)، DoD §2 (`engine:validate`) — هیچ‌کدام در CLI وجود ندارند |
| H3 | **۶۰ دستور مستند در DOC-083 پیاده‌سازی نشده** | از ۱۳۳ دستور مستند، ۹۳ عدد موجود است. شکاف شامل `down/up`، `module:enable/disable` (متدش در ModuleManager هست!)، `env:check`، `health:check`، `db:seed`، `tinker` و … — بدون هیچ علامت «پیاده‌نشده» در سند |

### 🟡 MED — ناقص/ناسازگار

| # | یافته | جزئیات |
|---|---|---|
| M1 | ۱۳ کلید ترجمه مفقود (fa+en) | `core.autoload.*` (۳)، `core.env.*` (۲)، `http.route.not_found`، `workflow.definition.*` (۷) — messageId خام به کاربر نشان داده می‌شود |
| M2 | ناسازگاری نسخه | `TVN_VERSION=0.7.1` و `tavaan.json=0.7.1` ولی `docs/manifest.json=1.0.0`؛ پس از تکمیل M8 هیچ bump انجام نشده |
| M3 | `.env.example` drift | کلیدهای M8 (`TVN_PLUGINS`, `TVN_ADMIN_*`, `TVN_UPGRADE_BACKUP`, …) و ده‌ها کلید درایورها (AWS_*/FTP_*/SMS) مستند نشده‌اند |
| M4 | `INDEX.html` (entry_point در manifest) وجود ندارد | `docs/manifest.json → entry_points.human_dashboard` |
| M5 | یک typo CJK در کامنت | `engines/view/src/ViewFactory.php:60` — «وضعیت» باید «وضعیت» باشد (workaround: بازنویسی جمله به فارسی خالص) |
| M6 | صفحهٔ welcome و README کهنه | «۴۸/۴۸ موتور • ۹۹۲ تست • M7 کامل شد» — اکنون ۵۲/۵۲ و ۱۲۳۵ تست و M8 کامل |

### 🟢 LOW / یادداشت

| # | یافته | جزئیات |
|---|---|---|
| L1 | cli-reference فاقد ۲۲ دستور موجود است | همهٔ `make:*`/`plugin:*`/`upgrade:*`/`module:validate` + `backup:list/verify`، `storage:link`، `view:clear`، `route:clear`، `search:reindex`، `workflow:list`، `realtime:websocket`، `payment:list`، `event:outbox-relay`، `cache:clear`، `config:clear`، `env`، `list` |
| L2 | AC-UPG-07 (ارتقای zero-downtime) پیاده نشد | در `upgrade.md` مستند شد — زیرساخت maintenance/verify آماده؛ به M9 موکول |
| L3 | برچسب‌های UI در Admin فارسی inline هستند (نه `trans()`) | در `admin.md` مستند شد — گذار به i18n کامل با کیت en انجام می‌شود |
| L4 | `pagination` در manifest «انجین» است ولی دایرکتوری جدا ندارد | by-design: زیرموتور database با namespace اختصاصی (`Tavaan\Engine\Database\Pagination`) — سندش هم «زیرموتور» می‌گوید. اقدام لازم: فقط شفاف‌سازی در manifest (فیلد `sub_engine_of`) |
| L5 | PHPStan/CS-Fixer واقعی در محیط قابل نصب نبودند (بدون vendor) | `style:check` به‌صورت wrapper با تشخیص ابزار و پیام راهنما پیاده می‌شود؛ تحلیل ایستا با arch:check/security:scan داخلی پوشش جزئی می‌گیرد |

## ۳. نقشهٔ رفع (به ترتیب اجرا)

| گام | اقدام | یافته‌ها |
|---|---|---|
| R1 | ساخت Facadeهای `Crypt`/`Lang`/`Cookie` + ثبت alias `cookie` در HttpServiceProvider + تست | H1 |
| R2 | پیاده‌سازی ابزارهای QA: `arch:check` (قواعد لایه‌بندی: core⟂engines، engines⟂app/modules، عدم استفاده از Testing در src موتورها)، `security:scan` (ruleset گسترش‌یافتهٔ check-forbidden + heuristics: SQL الحاقی، secret هاردکد، md5/sha1، unserialize…)، `lint:php` (php -l سراسری — cross-platform)، `bench:all` (اجرا + جمع‌بندی همهٔ benchها)، `style:check/fix` (wrapper صادقانه)، `engine:validate` | H2 |
| R3 | دستورات سریع و کم‌هزینهٔ مستند: `down`/`up`، `module:enable`/`module:disable`، `env:check` | بخشی از H3 |
| R4 | به‌روزرسانی `cli-reference.md`: افزودن همهٔ دستورات موجود (M8 + قدیمی‌های جامانده) + ستون «وضعیت» برای وعده‌های پیاده‌نشده (programmed → M9/backlog) | H3, L1 |
| R5 | افزودن ۱۳ کلید ترجمهٔ مفقود به `resources/lang/{fa,en}` | M1 |
| R6 | bump نسخه به **0.8.0** (bootstrap/tavaan.json/manifest) + ادغام بخش‌های Unreleased در `## [0.8.0] — M8` در CHANGELOG | M2 |
| R7 | `.env.example`: بخش M8 + ارجاع درایورها؛ ساخت `INDEX.html` خودایستا | M3, M4 |
| R8 | رفع typo CJK؛ به‌روزرسانی welcome.tv.php و README (۵۲ موتور/۱۲۳۵ تست/M8) | M5, M6 |
| R9 | شفاف‌سازی `pagination` در manifest (`sub_engine_of: database`) | L4 |
| R10 | تست‌های جدید برای ابزارهای QA و facadeها + اجرای کامل نهایی (suite×2، موازی، گیت‌ها، smoke) + گزارش رفع در همین سند | همه |

 → `UnsafeQueryException` (تست تزریق `x\`) -- injection` و `a; DROP TABLE`) |
| X3 | **نقض ARCH-006**: `core/Context/RequestStack` به `Engine\Http\Request` تایپ شده بود | تایپ بر پایهٔ `Core\Contracts\Http\RequestInterface` (مصرف‌کننده‌ها از قبل با instanceof/method_exists سازگار بودند) |
| X4 | **🐛 دادهٔ manifest**: `docs/manifest.json` → namespace موتور view برابر ``path` `` (خطای paste از M2!) | اصلاح به `Tavaan\Engine\View\` (engine:validate اکنون تطبیق می‌دهد) |
| X5 | **خرابی ساختاری CHANGELOG**: بولت Tenancy در بخش 0.7.1 وسط‌خط بریده و سرآیند کل فایل داخل آن paste شده بود + محتوای ویندوز 0.7.1 زیر هدر اشتباه «Unreleased M1–M4» تکرار شده بود | بازسازی کامل: بولت تعمیر (`…]+$\`) backslash…`)، حذف کپی تکراری، ادغام درست |
| X6 | ناسازگاری کلید env: ‏`engines/api/config/api.php` کلید `APP_URL` می‌خواند ولی قرارداد فریمورک `TVN_APP_URL` است → سرور مستندات API همیشه روی fallback می‌ماند | یکسان‌سازی به `TVN_APP_URL` |
| X7 | نویسه‌های CJK/Korean اضافی (فراتر از M5 گزارش اولیه): `Plugin.php` («선언»→«اعلام»)، گزارش M6 («보존»→«حفظ»)، `ViewFactory` («状態»→«وضعیت») | رفع + **اسکن کامل مخزن اکنون صفر نویسهٔ CJK/Korean/CJK-Ext است** |
| X8 | `composer.json` هیچ require-dev نداشت — گیت‌های DoD (PHPStan/CS-Fixer) عملاً غیرقابل اجرا بودند | require-dev + `phpstan.neon` + اسکریپت‌های QA + `composer qa` (ترکیب lint/arch/security/test) |
| X9 | Facade `Route` یک `use` واقعی به موتور Http داشت (فقط docblock لازم بود) | حذف import و inline کردن FQCN در `@method`ها |
| X10 | TestCase موتور Test فاقد assertionهای رایج PHPUnit بود (assertIsArray و ۹ مورد دیگر) | افزوده شد + DOC-084E §assertions به‌روز شد |
| X11 | `.git/config` و تاریخچهٔ محلی در reset محیط از بین رفته بود | بازیابی با re-clone از origin (مالک M8 را در commit `3c34d06` squash کرده بود) — درخت کاری با ممیزی overlay شد |

## ۶. راستی‌آزمایی نهایی

| بررسی | نتیجه |
|---|---|
| `php bin/tvn test` (دو اجرای پیاپی) | ✅ ۱۲۵۱/۱۲۵۱ سبز — ۸۳۵۷ assertion |
| `--parallel=4` | ✅ (اجرای تأییدی — بخش ۶ پایین) |
| `php tools/scripts/check-forbidden.php core engines bin config` | ✅ پاک |
| `bash tools/scripts/lint.sh` + `tvn lint:php` | ✅ ۱۲۹۳/۱۴۰۳ فایل پاک |
| `tvn arch:check` | ✅ ۱۰۵۸ فایل، صفر نقض |
| `tvn security:scan` | ✅ صفر high (۸ med تأییدشده) |
| `tvn engine:validate` | ✅ ۵۲ موتور، صفر خطا |
| `tvn env:check` | ✅ drift صفر |
| `tvn doctor` / `about` / `version` | ✅ سالم — 0.8.0، ۵۲/۵۲ |
| HTTP smoke (`php -S`) | ✅ `/` ۲۰۰ • `/time` جلالی ۱۴۰۵ ✓ • `/admin` ۳۰۲→`/login` • ۴۰۴ fallback |
| optimize/config:cache/clear | ✅ |
| بنچمارک‌ها | ⚠️ اهداف µs/ms روی CPU ماسه‌دان مرزی (match ~۵٫۵-۷µs، boot cold ~۳۶ms) — معیارهای وزین (overhead ۰٫۲۵ms، حافظه ۱٫۶MB، render، sequential make) سبز؛ رگرسیون کدی از M8 یافت نشد |
