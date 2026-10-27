# DOC-135 — یکپارچه‌سازی محیط توسعه و Git — دفتر کل تاریخچهٔ کامل 🔧

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-135 |
| نقش | یکپارچه‌سازی محیط توسعه و Git (گردش‌کار، hookها، قراردادها) + **دفتر کل جمع‌آوری‌شدهٔ تاریخچهٔ همهٔ نسخه‌ها و توضیحات آنها** |
| مرتبط | `CHANGELOG.md` (مرجع معتبر تاریخچه)، `CONTRIBUTING.md`، DOC-113 (DoD)، DOC-015 (استاندارد مستندسازی)، DOC-134 (هاست اشتراکی)، DOC-122 (بدهی فنی)، DOC-004 (quickstart) |
| سطح | زیرساخت (DevEx) — عملیاتی |
| وضعیت | ✅ اجراشده — مخزن `main` با خط پایهٔ `0d13b6a` و تگ‌های `v0.18.0` / `v0.19.0` |
| ابزار | `tools/scripts/git-setup.sh`، `tools/git-hooks/pre-commit`، `tools/git-hooks/commit-msg` |
| محیط اندازه‌گیری | git 2.39.5 / PHP 8.2.35 / Debian 12 — زمان‌های §2.4 واقعی و اندازه‌گیری‌شده‌اند |

---

## ۱. هدف و وضعیت فعلی (صادقانه)

### ۱٫۱ هدف

این سند دو کار انجام می‌دهد:

1. **یکپارچه‌سازی محیط توسعه و Git** — از راه‌اندازی مخزن و هویت تا hookها،
   قرارداد پیام کامیت، مدل شاخه، نسخه‌گذاری/تگ و چک‌لیست انتشار (§2 و §3).
2. **جمع‌آوری تمامی کامیت‌ها/نسخه‌ها و توضیحات آنها در یک دفتر کل** (§4) —
   تاریخچهٔ کامل ۲۱ نگارش از `0.1.0` (۲۰۲۶-۱۰-۱۵) تا `0.19.0` (۲۰۲۶-۱۰-۲۷)
   با توصیف وفادار هر نسخه و معیارهای سنجش آن.

### ۱٫۲ وضعیت فعلی — خط پایهٔ بازسازی‌شده

تا پیش از این نگارش، **مخزن git در محیط کاری حاضر وجود نداشت**؛ توسعه بر پایهٔ
`CHANGELOG.md` به‌عنوان دفتر تاریخچه پیش رفته بود. در ۲۰۲۶-۱۰-۲۷ مخزن با
یک **کامیت خط پایه (baseline)** از وضعیت کامل نگارش `0.18.0` مقداردهی اولیه شد:

```text
0d13b6a7247d8198faaeabb74f2cb220b9a038f2  (main, tag: v0.18.0)
پایه‌گذاری مخزن Git — وضعیت کامل نگارش 0.18.0 (خط پایهٔ بازسازی‌شده)
```

- **۲٬۸۶۳ فایل track‌شده** — `.env`، فایل SQLite زمان‌اجرا (`tavaan`) و
  محتوای `storage/**` (جز فایل‌های `.gitignore` ساختاری) مطابق سیاست §3.2 خارج‌اند.
- مرجع معتبر جزئیات تاریخچه: `CHANGELOG.md` (۵۱۸ خط، همهٔ بولت‌های هر نگارش).
  §4 همین سند = دفتر کل جمع‌آوری‌شده با خلاصهٔ وفادار هر نسخه.

### ۱٫۳ چرا تاریخچهٔ مصنوعی بازسازی نشد؟

بازسازی ۲۰ کامیت از روی CHANGELOG (هر نسخه یک کامیت) تنها با **درخت یکسان**
(وضعیت فعلی کد) ممکن بود؛ یعنی `git show v0.5.0` کدِ `0.18.0` را نشان می‌داد —
تاریخچه‌ای **غیرواقعی و گمراه‌کننده** (diffها، زمان‌ها و نویسندگان ساختگی).
تصمیم (هم‌راستا با اصل «مستندسازی صادقانه» DOC-015): یک خط پایهٔ صادقانه +
دفتر کل مستند. هر کامیتِ **از این پس** واقعی، atomic و با قرارداد §3.4 است.

---

## ۲. یکپارچه‌سازی محیط توسعه

### ۲٫۱ پیش‌نیازها

| پیش‌نیاز | حداقل | وضعیت در تَوان |
|---|---|---|
| PHP | **۸٫۲٫۰** (ADR-0003) | توصیه: 8.2.x یا 8.3.x؛ افزونه‌های اختیاری (intl/redis/gd/…) با **fallback خودکار** — `tvn doctor` سلامت را می‌سنجد (DOC-134 §8) |
| git | ۲٫۳۰+ | برای `init -b` و hookهای `core.hooksPath` |
| composer | **نیست** | سیاست صفر‌وابستگی؛ `composer.json` فقط require-dev اختیاری (phpstan/php-cs-fixer) برای گیت‌های سبک‌ِ تحلیل ایستا دارد |
| پایگاه‌داده | SQLite (توسعه) | MySQL/PostgreSQL برای تولید؛ فایل SQLite توسعه = `tavaan` در ریشه (خارج از git) |

### ۲٫۲ گام‌های راه‌اندازی (محیط توسعهٔ تازه)

```bash
git clone <repo-url> tavaan && cd tavaan
bash tools/scripts/git-setup.sh            # ۱) یکپارچه‌سازی git: هویت + hooks (idempotent)
cp .env.example .env                       # ۲) محیط
php bin/tvn key:generate                   # ۳) کلید اپ
php bin/tvn migrate --seed                 # ۴) پایگاه‌داده (SQLite توسعه)
php bin/tvn kit:install blog --with-demo   # ۵) کیت دلخواه (۸ کیت موجود)
php bin/tvn doctor                         # ۶) راستی‌آزمایی سلامت محیط
php bin/tvn serve                          # ۷) سرور توسعه → http://127.0.0.1:8000
```

جزئیات کامل: DOC-004 (quickstart). مسیرهای production: DOC-133 (استقرار) و
DOC-134 (هاست اشتراکی + پروفایل‌های `examples/hosting/php-*.ini`).

### ۲٫۳ فایل‌های کلیدی محیط و وضعیت git آنها

| فایل/مسیر | نقش | در git؟ | دلیل |
|---|---|---|---|
| `.env` | رازها و پیکربندی محیط | ❌ | secret — هرگز commit نمی‌شود (`.env.example` الگوست) |
| `tavaan` (ریشه) | فایل SQLite زمان‌اجرا | ❌ | دادهٔ محلی/دمو — بازتولیدپذیر با `migrate --seed` |
| `storage/**` | لاگ/کش/نشست/آپلود/backup | ❌ (جز `.gitignore`های ساختاری) | زمان‌اجرا |
| `config/{kits,blog,…}.php` + `resources/views/vendor/*` | overlayهای `kit:install` | ❌ | بازتولیدپذیر با دستور نصب کیت (در پروژهٔ اپلیکیشنی واقعی: قابل commit — یادداشت در `.gitignore`) |
| `plugins/*` | افزونه‌های نصب‌شدهٔ ثالث | ❌ (جز `.gitkeep`) | با `plugin:install` + lock/امضا مدیریت می‌شود |
| `tools/git-hooks/*`، `tools/scripts/*` | hookها و ابزار کیفیت | ✅ | بخشی از قرارداد تیمی |
| `docs/**`، `CHANGELOG.md`، `tavaan.json` | مستندات و manifest پروژه | ✅ | مرجع تاریخچه و پیکربندی |

### ۲٫۴ گیت‌های کیفیت (زمان‌های اندازه‌گیری‌شده در محیط §متادیتا)

| # | فرمان | پوشش | زمان | نقش |
|---|---|---|---|---|
| 1 | `bash tools/scripts/lint.sh` | ۱٬۳۴۳ فایل PHP (core/engines/tests/config/bin/public/tools) | ~۳۷s | `php -l` سراسری |
| 2 | `php bin/tvn lint:php` | ۲٬۲۲۴ فایل (همهٔ مسیرهای PHP شامل modules/kits) | ~۳۵s | lint کامل cross-platform |
| 3 | `php tools/scripts/check-forbidden.php` | ۱٬۱۵۲ فایل | ~۰٫۴s | gate موارد ممنوعه (eval/putenv/… — DOC-010) + strict_types |
| 4 | `php bin/tvn arch:check` | ۱٬۵۷۳ فایل | ~۵٫۷s | مرزهای معماری (`config/architecture.php` — ARCH-003 و…) |
| 5 | `php bin/tvn security:scan --level=high` | ۱٬۷۴۹ فایل | ~۰٫۹s | اسکن امنیتی token-based |
| 6 | `php bin/tvn engine:validate` | ۵۲ موتور | ~۰٫۲s | اعتبارسنجی عمیق manifest موتورها |
| 7 | `php bin/tvn module:validate` | ۶۴ manifest | ~۰٫۱s | اعتبارسنجی ماژول‌ها/کیت‌ها |
| 8 | `php bin/tvn env:check` | ۲۵۳ کلید | ~۰٫۱s | drift کلیدهای env در برابر `.env.example` |
| 9 | `php bin/tvn test` | **۱٬۷۰۴ تست / ۱۱٬۱۱۵ assertion** | ~۱۱۱s | مجموعهٔ کامل (۱۱ skip مستندِ محیطی — DOC-084E §7.1) |

**قاعده (DOC-113 DoD):** پیش از هر کامیتِ merge‌شونده، گیت‌های ۳/۴/۹ الزامی‌اند
(hook §3.6 به‌صورت خودکار ۱-فایل‌های‌stage‌شده/۳/۴ را اجرا می‌کند)؛ پیش از هر
انتشار، **هر ۹ گیت** + smoke کیت‌ها (`tools/scripts/smoke-all-kits.php`).

### ۲٫۵ تحلیل ایستا و ویرایشگر

- `phpstan.neon` — level max (با `composer install` اختیاری قابل اجرا؛ خطاهای کلاس fatal = صفر).
- `.php-cs-fixer.dist.php` + `tvn style:check [--fix]` — wrapper صادقانه (در نبود ابزار، exit 2 با راهنما نه سکوت سبز).
- `.editorconfig` — یکدستی indent/charset/EOL بین ویرایشگرها.
- `INDEX.html` — داشبورد انسانی خودایستای مستندات؛ `docs/manifest.json` — فهرست ماشین‌خوان ۱۲۷ سند با sha256.

---

## ۳. یکپارچه‌سازی Git

### ۳٫۱ مقداردهی اولیه و هویت — `tools/scripts/git-setup.sh`

اسکریپت **idempotent** (هر بار قابل اجرا) این کارها را می‌کند:

1. `git init -b main` (فقط اگر مخزن نیست)؛
2. تنظیم هویت **محلی** (`user.name`/`user.email` — بدون دستبرد به config سراسری):
   `bash tools/scripts/git-setup.sh "نام شما" you@example.com`؛
3. فعال‌سازی hookها: `git config core.hooksPath tools/git-hooks`؛
4. راستی‌آزمایی اینکه `.env`/`tavaan` هرگز track نشده‌اند (خطای سخت در صورت نشت)؛
5. گزارش آخرین کامیت/تگ + راهنمای گیت‌ها.

> ⚠️ **محدودیت محیط sandbox:** فایل `.git/config` در snapshot محیط کار حفظ
> نمی‌شود؛ بنابراین هویت محلی و `core.hooksPath` ممکن است بین نشست‌ها از دست
> بروند (خودِ کامیت‌ها/تگ‌ها/objects پایدارند). راه‌حل: اجرای مجدد
> `git-setup.sh` در آغاز هر نشست — دقیقاً به همین دلیل idempotent است.

### ۳٫۲ سیاست `.gitignore` (چه چیزی بیرون است و چرا)

| الگو | چرا بیرون است |
|---|---|
| `.env` و `.env.local` | رازها (APP_KEY، اعتبارنامهٔ درگاه‌ها) — الگو: `.env.example` |
| `/tavaan` | فایل SQLite زمان‌اجرا (`TVN_DB_DATABASE=tavaan` — مسیر نسبی) |
| `/storage/**` (جز `.gitignore`های ساختاری) | لاگ/کش/نشست/آپلود/backup — زمان‌اجرا و ماشین‌محور |
| `/plugins/*` | افزونهٔ نصب‌شده با `plugin:install` (lock + امضای Ed25519 — موتور plugin) |
| `/public/uploads/*`، `/public/assets/*` | آپلود کاربر و build artifact |
| `config/{kits,blog,comment,news,shop,forum,lms,crm,erp,bpms}.php` | overlayهای **بازتولیدپذیر** `kit:install` — در پروژهٔ اپلیکیشنی واقعی می‌توان commit کرد (یادداشت در `.gitignore`) |
| `resources/views/vendor/{blog,…}/` | ویوهای کپی‌شدهٔ پوستهٔ کیت — بازتولیدپذیر |
| `/vendor/`، `/node_modules/`، `composer.lock` | سیاست صفر‌وابستگی — vendor فقط dev-tool اختیاری است |
| `.phpunit.result.cache`، `.php-cs-fixer.cache`، IDE/OS | نویز |

`.gitattributes`: `* text=auto` — نرمال‌سازی LF (سازگاری Linux/macOS/Windows — نسخهٔ 0.7.1).

### ۳٫۳ مدل شاخه

| شاخه | قاعده |
|---|---|
| `main` | همیشه سبز — هر کامیت باید گیت‌های §2.4 (حداقل ۳/۴/۹) را پاس کند؛ force-push ممنوع |
| `feature/TVN-xxxx-<slug>` | هر تیکت بک‌لاگ یک شاخه (ارجاع به `docs/08-roadmap/agent-backlog.md`) |
| `hotfix/<slug>` | رفع فوری؛ پس از merge، `patch` نگارش (0.0.z) + CHANGELOG |
| `release/x.y` | فقط برای آماده‌سازی انتشار بزرگ (اختیاری — در عمل تا 0.19.0 روی main) |

Merge: `--no-ff` برای featureها (حفظ مرز کار) یا rebase خطی به انتخاب نگهدارنده؛
هر دو با پیام‌های مطابق §3.4.

### ۳٫۴ قرارداد پیام کامیت (فارسی ساختاریافته)

الگو — هم‌خانواده با قالب `CHANGELOG.md`:

```text
<موضوع: خلاصهٔ تغییر در یک خط، ≤۱۰۰ نویسه — با شناسهٔ تیکت در صورت وجود>

افزوده شد:
- <مورد جدید — با ارجاع DOC/AC/TVN>
تغییر یافت:
- <تغییر رفتار/امضا/پیکربندی + دلیل>
رفع شد:
- <باگ + شناسه (BUG-*) + ریشهٔ مشکل>
مستندات:
- <DOC-ها/گزارش‌های به‌روزشده>
نتایج:
- <اعداد سنجش: تست‌ها/assertionها/بنچ‌ها/گیت‌ها>
```

قواعد:

1. **فارسی** — زبان رسمی مخزن (نام‌های فنی/کلاس‌ها انگلیسی درون متن فارسی).
2. بخش‌ها اختیاری‌اند ولی **دروغ ممنوع**: فقط بخش‌های واقعاً رخ‌داده.
3. شناسه‌ها ارجاع‌پذیر باشند: `TVN-xxxx` (تیکت)، `DOC-xxx` (سند)، `AC-*` (معیار پذیرش)، `BUG-*`/`TECH-*` (رجیستری DOC-122).
4. اعداد **سنجش واقعی** (نه ادعا) — هم‌راستا با DOC-113.
5. کامیت atomic: یک واحد کار منسجم (یک تیکت/یک رفع/یک سند)؛ نسخه‌بندی + CHANGELOG در همان کامیتِ انتشار.
6. hook `commit-msg` این قرارداد را **نرم** بررسی می‌کند (فقط پیام خالی = رد سخت).

نمونهٔ واقعی (خط پایهٔ این مخزن): «پایه‌گذاری مخزن Git — وضعیت کامل نگارش
0.18.0 (خط پایهٔ بازسازی‌شده)» با بخش‌های افزوده شد/یادداشت — `git log -1 v0.18.0`.

### ۳٫۵ نسخه‌گذاری و تگ‌ها

- **SemVer** (`0.x.y` تا پیش از 1.0؛ پس از آن `x.y.z`) — منبع حقیقت نگارش:
  `core/bootstrap.php` (ثابت `TVN_VERSION`) + `tavaan.json` + خط وضعیت `README.md`
  + `docs/manifest.json` (`framework.version`) — **هر چهار با هم** در کامیت انتشار.
- هر نگارش = یک ورودی `## [x.y.z] - YYYY-MM-DD — <عنوان>` در بالای `CHANGELOG.md`
  (Keep a Changelog) با همان بخش‌های §3.4.
- تگ **annotated**: `git tag -a vX.Y.Z -m "<عنوان نگارش>"` — همان شماره در تگ و CHANGELOG.
- هر سند تازه/ویرایش‌شده در `docs/**`: ثبت/به‌روزرسانی در `docs/manifest.json`
  (doc_id یکتا، `sha256` = ۱۶ نویسهٔ نخست hash فایل، `totals.documents`) + سطر شاخص `docs/README.md`.

### ۳٫۶ Hookها (`tools/git-hooks/`)

| hook | رفتار | هزینه |
|---|---|---|
| `pre-commit` | ۱) `php -l` **فقط فایل‌های stage‌شده** ۲) `check-forbidden.php` سراسری ۳) `tvn arch:check` سراسری — هرکدام شکست ⇒ کامیت رد | ~۶–۷s |
| `pre-commit` با `TVN_HOOK_FULL=1` | + `lint.sh` سراسری + `tvn test` کامل | ~۲٫۵ دقیقه |
| `commit-msg` | رد سخت: پیام خالی • هشدار: موضوع >۱۰۰ نویسه یا نبود بخش‌های ساختاریافتهٔ §3.4 • معاف: Merge/Revert/fixup!/squash! | <۰٫۱s |

نصب (خودکار توسط `git-setup.sh`): `git config core.hooksPath tools/git-hooks`.
دورزدن آگاهانه: `git commit --no-verify` — فقط در موارد اضطراری و با ذکر دلیل.

### ۳٫۷ فرایند انتشار (چک‌لیست)

1. هر ۹ گیت §2.4 سبز (خروجی واقعی پیوست PR/کامیت).
2. Bump نگارش در **چهار محل** (§3.5) — یکسان.
3. ورودی CHANGELOG (تاریخ امروز، عنوان، بخش‌های ساختاریافته، اعداد سنجش).
4. ثبت اسناد تازه در `docs/manifest.json` (sha256 بازشمارش) + `docs/README.md` + `totals`.
5. `git add -A && git commit` (hookها اجرا می‌شوند) با پیام §3.4.
6. `git tag -a vX.Y.Z -m "…"`.
7. Smoke: `php tools/scripts/smoke-all-kits.php` (در صورت تغییر کیت‌ها) + `tvn serve` و بررسی `/`، `/time`، `/blog`.

### ۳٫۸ محدودیت‌های محیطی (صادقانه)

- **snapshot محیط:** `.git/config` حفظ نمی‌شود (§3.1) — `git-setup.sh` را در هر نشست اجرا کنید.
- **تاریخ کامیت‌ها:** از این پس واقعی (زمان محیط)؛ تاریخ انتشارهای پیشین فقط در
  CHANGELOG/§4 معتبر است (خط پایه diff تاریخی ندارد).
- محیط sandbox ریشه‌دار است؛ `opcache.preload` و برخی سناریوهای php-fpm واقعی
  قابل آزمایش نیستند (DOC-134 §4.4) — بی‌ارتباط با git ولی برای «محیط توسعه» مربوط.

---

## ۴. دفتر کل تمامی نسخه‌ها (تاریخچهٔ جمع‌آوری‌شدهٔ کامیت‌ها)

> هر بند = یک نگارش (واحد کامیت انتشار) با **خلاصهٔ وفادار** توضیحات آن از
> `CHANGELOG.md`؛ جزئیات کامل (همهٔ بولت‌ها) در همان فایل، بخش `## [x.y.z]`.
> ستون «کامیت» فقط از `v0.18.0` به بعد hash واقعی git دارد (پیش از آن: تاریخچهٔ
> git در این محیط موجود نبود — §1.2).

### ۴٫۰ جدول خلاصه

| نگارش | تاریخ | فاز / عنوان | تست‌ها | assertionها | کامیت/تگ |
|---|---|---|---|---|---|
| 0.1.0 | 2026-10-15 | M1 — هستهٔ صفر‌وابستگی | ۱۵۵ | ۳۶۲ | تاریخ (CHANGELOG) |
| 0.2.0 | 2026-10-15 | M2 — وب | ۳۰۲ | ۷۷۹ | تاریخ |
| 0.3.0 | 2026-10-16 | M3 — داده | ۴۴۵ | ۲٬۱۸۹ | تاریخ |
| 0.4.0 | 2026-10-16 | M4 — امنیت و هویت | ۵۴۴ | ۲٬۷۳۶ | تاریخ |
| 0.5.0 | 2026-10-16 | M5 — ارتباطی و زیرساخت | ۶۹۵ | ۳٬۴۰۶ | تاریخ |
| 0.5.1 | 2026-10-16 | Hotfix M5 — محافظ بازگشت | ۷۰۴ | ۳٬۴۲۰ | تاریخ |
| 0.6.0 | 2026-10-16 | M6 — سرویس‌ها | ۹۰۱ | ۶٬۳۷۱ | تاریخ |
| 0.7.0 | 2026-10-17 | M7 — دامنه | ۹۸۵ | ۶٬۶۷۸ | تاریخ |
| 0.7.1 | 2026-10-17 | سازگاری کامل ویندوز | ۹۹۲ | ۶٬۷۰۴ | تاریخ |
| 0.8.0 | 2026-10-19 | M8 — ابزار + ممیزی جامع M0–M8 | ۱٬۲۵۱ | ~۸٬۳xx | تاریخ |
| 0.9.0 | 2026-10-24 | M9 گام ۱ — زیرساخت کیت + وبلاگ | ۱٬۳۴۲ | ۸٬۷۴۹ | تاریخ |
| 0.10.0 | 2026-10-24 | M9 گام ۲ — خبرگزاری | ۱٬۳۶۹ | ۸٬۹۲۸ | تاریخ |
| 0.11.0 | 2026-10-24 | M9 گام ۳ — فروشگاه | ۱٬۴۲۵ | — | تاریخ |
| 0.12.0 | 2026-10-24 | M9 گام ۴ — انجمن | ۱٬۴۶۸ | ۹٬۴۹۱ | تاریخ |
| 0.13.0 | 2026-10-25 | M9 گام ۵ — LMS | ۱٬۵۰۸ | ۹٬۸۱۴ | تاریخ |
| 0.14.0 | 2026-10-25 | M9 گام ۶ — CRM | ۱٬۵۳۴ | ۱۰٬۱۴۵ | تاریخ |
| 0.15.0 | 2026-10-25 | M9 گام ۷ — ERP | ۱٬۵۸۳ | ۱۰٬۴۲۴ | تاریخ |
| 0.16.0 | 2026-10-26 | M9 گام ۸ — BPMS (پایان M9) | ۱٬۶۷۰ | ۱۰٬۹۵۶ | تاریخ |
| 0.17.0 | 2026-10-26 | رفع نقدهای 1.0 + مستندسازی تیکت‌محور | ۱٬۷۰۴ | ۱۱٬۱۱۵ | تاریخ |
| 0.18.0 | 2026-10-27 | هاست اشتراکی + php.ini سه‌سطحی | ۱٬۷۰۴ | ۱۱٬۱۱۵ | **`0d13b6a` / tag v0.18.0** |
| 0.19.0 | 2026-10-27 | یکپارچه‌سازی git + همین سند | ۱٬۷۰۴ | ۱۱٬۱۱۵ | **`d7ae7da` / tag v0.19.0** |

---

### v0.1.0 — 2026-10-15 — فاز M1: هستهٔ صفر‌وابستگی

- **افزوده شد:** ۱۱ موتور پایه — TVN-0001 Autoloader (PSR-4 + Wildcard + ClassMap + امنیت path-traversal) • TVN-0002 Container (Auto-Wiring، Contextual Binding، LazyProxy بدون eval، تشخیص چرخه) • TVN-0003 Config (EnvLoader بدون putenv، dot-notation، freeze، SchemaValidator) • TVN-0004 Event (Priority قطعی، Wildcard Segment-Trie، Allowlist) • TVN-0005 Pipeline (Onion، `auth:web,admin`) • TVN-0006 Error (RFC 9457، RefCode، LogMasker) • TVN-0007 Log (۱۱ کانال، Processorها، ضد Log Injection) • TVN-0008 Compat (Decimal بدون bcmath، ZipWriterPure، Utf8) • TVN-0009 Optimizer • TVN-0010 Module (TopologicalSorter، enable/disable) • TVN-0011 Console (`bin/tvn` + ۱۳ دستور) + موتور Test حداقلی + Application/۸ Bootstrapper/Facadeها + قراردادها (`core/Contracts/`) + سلسله‌مراتب استثناها (DOC-013) + ابزار کیفیت (lint.sh/check-forbidden) + ساختار DOC-080.
- **نتایج:** ۱۵۵ تست / ۳۶۲ assertion سبز • بوت ~۷٫۷ms • classmap ۹۰٫۶٪ سریع‌تر • صفر eval/putenv.

### v0.2.0 — 2026-10-15 — فاز M2: وب

- **افزوده شد:** TVN-0020 HTTP (Request/Response immutable، Kernel، ETag/304، gzip، ضد Request Smuggling، UploadedFile با magic bytes، ۱۱ middleware شامل SecurityHeaders/CSRF/CORS) • TVN-0021 Router (Compiled Trie، resource/apiResource، domain routing، Signed URL، Route Cache، ReDoS guard، Unicode/فارسی) • TVN-0022 Session (file/cookie/array، hardening کامل: fixation/timeout/fingerprint/JSON-only) • TVN-0023 Cookie (رمزنگاری‌شده، tamper→null) • TVN-0024 View (`.tv.php`، کامپایلر شش‌pass دستی، auto-escape، Component `<x-…>`، layout/@push، view:cache) • TVN-0025 I18n (namespace، choice، PluralRules fa/en/ar/ru، PersianFormatter) • TVN-0026 Jalali (jalaali-js breaks-based — ۱۰۰٪ تطبیق با intl روی ۸۰۰۰ نمونه) • TVN-0027 CORS • TVN-0054 Crypt زودهنگام (AES-256-GCM، KeyRing، HKDF) + `serve`/`route:*`/`view:*` + `public/index.php` + routes/viewهای دمو RTL.
- **تغییر یافت:** تفکیک `header()`/`withHeader()` در ResponseInterface • fallback فقط GET/HEAD.
- **نتایج:** ۳۰۲ تست / ۷۷۹ assertion • سربار درخواست ۰٫۱ms • route match ۲٫۴µs/۱۰۰۰ روت • compile ویو ۰٫۴۳ms.

### v0.3.0 — 2026-10-16 — فاز M3: داده

- **افزوده شد:** TVN-0030 Database (Lazy PDO + reconnect، prepared ۱۰۰٪، QueryBuilder کامل با CTE/union، Grammar سه درایور، Savepoint + Deadlock retry، QueryLog با N+1 detection، Read/Write splitting، chunk/lazy/cursor، Ulid) • TVN-0031 Schema (۳۵+ نوع ستون، DDL سه درایور، ALTER، introspect) • TVN-0032 Migration (FileLock اتمیک، batch، rollback، fresh با guard production، مسیرهای خودکار ماژول‌ها) • TVN-0033 ORM (Active Record + casts پول/encrypted/hashed، Soft Delete، **۱۰ نوع رابطه**، Eager Loading DOC-050 با دقیقاً ۲ کوئری، withCount، Observer، Scope، الگوی Repository) • TVN-0034 Pagination (page/cursor/keyset) • TVN-0035 Validation (**۹۲ قاعده** + قواعد ایرانی 🇮🇷: mobile/national_code/sheba/card/postal/jalali، ReDoS guard، FormRequest، DTO attribute-based) • تکمیل درایور database نشست + Facadeهای DB/Schema/Validator + `examples/crud/blog-crud.php`.
- **نتایج:** ۴۴۵ تست / ۲٬۱۸۹ assertion • Hydrate ۱۰۰۰ رکورد ۱٫۳ms • حافظهٔ ۱۰۰۰ مدل ۶MB • SQL Injection fuzzing: ۲۰۰ payload سبز.

### v0.4.0 — 2026-10-16 — فاز M4: امنیت و هویت

- **افزوده شد:** TVN-0055 Hash (Argon2id پیش‌فرض + bcrypt، needsRehash) • TVN-0060 Cache (۶ درایور با fallback بدون crash، Stampede Protection، Tagging، CacheLock اتمیک، payload فقط JSON) • TVN-0056 Auth (چند-Guard، Session Hardening، **JwtEncoder خودنوشته با Algorithm Pinning**، Refresh Rotation + **Reuse Detection**، OTP، **TOTP RFC 6238** (همهٔ vectors)، DeviceManager، PasswordBroker، dummy-verify ضد timing) • TVN-0057 RBAC (Gate/Policy، سلسله‌مراتب نقش + wildcard + delegation، PermissionSync از manifest، **OwnershipScope** row-level با deny-by-default) • TVN-0059 Security (CSRF double-submit، RateLimiter سه الگوریتم، SecurityHeaders، **HtmlSanitizer خالص**، **SsrfGuard** (۱۲+ CIDR + DNS rebinding)، InputNormalizer با حفظ ZWNJ) • TVN-0058 Audit (**زنجیرهٔ هش HMAC**، تشخیص دستکاری و حذف، exclude_fields→REDACTED، AuditObserver) • **Outbox** (ADR-0009: claim اتمیک، releaseStale، `event:outbox-relay`) + درایور redis نشست + `examples/auth/login-rbac.php`.
- **نتایج:** ۵۴۴ تست / ۲٬۷۳۶ assertion • JWT alg-confusion رد • hash-chain tamper/delete تشخیص • SSRF metadata/rebinding رد • ۳۱/۳۱ موتور فعال.

### v0.5.0 — 2026-10-16 — فاز M5: ارتباطی و زیرساخت

- **افزوده شد:** TVN-0061 Queue (۵ درایور، Worker مقاوم با pcntl+fallback، **Pop اتمیک FOR UPDATE SKIP LOCKED**، Batch/Chain، Rate Limit، DLQ، Serialization فقط JSON+Allowlist، **کلاینت RESP خودنوشته** بدون ext-redis، ۸ دستور CLI) • TVN-0062 Schedule (**Cron Parser خودنوشته**، Overlap mutex، onOneServer، timezone per-event، شبانه‌گذر، runInBackground، `schedule:run/list`) • TVN-0063 Filesystem (۴ درایور، **Path Sandbox ضد Symlink Escape**، Atomic Write، **S3 SigV4 خودنوشته سبز روی AWS reference vectors**، presigned URL، stream با حافظهٔ ثابت) • TVN-0064 HttpClient (fluent، Retry+jitter، **Circuit Breaker**، **SSRF Guard با re-check هر hop**، Pool موازی، `Http::fake()`) • TVN-0065 Mail (**SMTP client خالص** با STARTTLS/AUTH/Dot-stuffing، MIME RFC 2047 فارسی، ۶ transport شامل SES SigV4، Mailable، queue، fake، ضد Header Injection) • TVN-0066 Notification (**۸ کانال**: mail/sms/database/broadcast/push/webhook/telegram/bale/eitaa، **۵ درگاه SMS ایرانی**، In-App، **Rate Limit ضد SMS Bombing**، mask شماره) • TVN-0074 Media (جریان آپلود امن **۷ مرحله‌ای**: MagicBytes/polyglot/Image Bomb/rename/dedup-SHA256، Image fluent با درایور auto→imagick→gd→none، variants، EXIF بدون GPS) • TVN-0068 Backup (**DB Dump خالص chunked با حافظهٔ ثابت**، ZipReader خودنوشته با CRC-32، **AES-256-GCM با کلید جدا**، verify/restore با force+maintenance، retention).
- **تغییر یافت:** `forUpdateSkipLocked` سه درایور • `dispatchNow` (ضد حلقهٔ requeue) • کانال پیش‌فرض Log پویا.
- **نتایج:** ۶۹۵ تست / ۳٬۴۰۶ assertion • ۳۹/۳۹ موتور فعال • SMTP dialogue کامل با socket-pair • Pop اتمیک: دو worker موازی بدون پردازش دوباره.

### v0.5.1 — 2026-10-16 — Hotfix M5: محافظ بازگشت

- **رفع شد:** 🔴 Queue/Serializer — ارجاع دوری/دور آرایه‌ای/تودرتویی >۶۴ سطح ⇒ «Maximum call stack size exceeded» کشنده → تشخیص دور مسیر-محور با SplObjectStorage + سقف MAX_DEPTH=64 دو سمته + JsonException→QueueException کنترل‌شده (`queue.unserializable`). 🔴 Schedule — خودبازگشتی `schedule:run` (درون‌فرآیندی: بازگشت بی‌پایان؛ background: انفجار فرآیند) → رویداد failed با پیام واضح.
- **افزوده شد:** ۹ تست رگرسیون + fixture `CycleJob`.
- **نتایج:** ۷۰۴ تست / ۳٬۴۲۰ assertion سبز.

### v0.6.0 — 2026-10-16 — فاز M6: سرویس‌ها

- **افزوده شد:** TVN-0067 Observability (Metrics چهارنوع + **Prometheus text خودنوشته** + `/metrics`، **Tracing خودنوشته** با Sampling قطعی + **W3C TraceContext**، ۴ Exporter شامل **OTLP/HTTP** و **Jaeger UDP با Thrift Compact کاملاً خودنوشته**، Health live/ready، **Debug Bar** با guard سه‌گانه و escape کامل) • TVN-0052 Search (**Native Inverted Index فارسی‌آگاه**: نرمال‌سازی ی/ک/ارقام/نیم‌فاصله، stemming، **BM25** + boost، Facet/Sort امن، Highlight با escape-then-mark، Suggest + «آیا منظور شما…»؛ درایورهای SQLite FTS5/MySQL FULLTEXT/PG tsvector؛ آداپتر Meili/Typesense؛ ایندکس خودکار از رویدادها) • TVN-0044 API (**JsonResource** با when/whenLoaded lazy، Versioning سه‌استراتژی + Deprecation/Sunset، **IdempotencyMiddleware** با قفل ضد race، Throttle، **OpenAPI 3.1 Generator خودنوشته** + YAML emitter داخلی + `api:docs`) • TVN-0082 Tenancy (**سه استراتژی**: Global Scope/Database-per-tenant/Schema، زنجیرهٔ Resolver شش‌عضوی، TenantContext با run تودرتو، **QuotaGuard**، جداسازی cache، Provisioner، `RestoresTenantContext` برای صف، `db:tenant-guard`) • TVN-0075 FeatureFlag (role/permission/tenant/**percentage پایدار crc32**، **Kill Switch**، A/B experiment، کلید cache نسخه‌دار) • TVN-0045 Realtime (**SSE بدون ext** با sanitize ضد تزریق خط + **WebSocket RFC 6455 خودنوشته** (mask اجباری، Origin check، توکن HMAC کانال خصوصی)، Brokerهای Array/Database/Redis، Presence، `ShouldBroadcast`).
- **نتایج:** ۹۰۱ تست / ۶٬۳۷۱ assertion • ۴۵/۴۵ موتور فعال • WebSocket سبز روی بردار رسمی RFC 6455 • fuzzing نشت cross-tenant (۱۰۰ راند) = صفر.

### v0.7.0 — 2026-10-17 — فاز M7: دامنه

- **افزوده شد:** TVN-0071 Workflow/BPM (**DSL خودنوشته** + JSON/Schema با validate کامل، Execution اتمیک snapshot/restore، **Token-based Fork/Join**، Human Task با claim fail-closed، Timer انسانی/ISO، Sub-Process، History، نسخه‌بندی تعاریف، **Visualizer Graphviz DOT + Mermaid**، محافظ DoS/mask) • TVN-0072 Report (Dataset چندشکل، Field/Filter با allowlist امن، groupBy/subtotal/**Pivot با Decimal** (ADR-0013)، تاریخ جلالی، **CSV streaming 100k با سربار حافظهٔ ~0 + ضد Injection**، **xlsx کاملاً خودنوشته** (OOXML+ZipWriterPure بدون ext-zip)، HTML/JSON/PDF اختیاری، sandbox مسیر + max_rows) • TVN-0073 Payment (**۱۰ درگاه**: زرین‌پال/آیدی‌پی (IPN با HMAC)/پی‌پینگ/سامان/به‌پرداخت ملت (**SOAP/XML خودنوشته بدون ext-soap**)/پارسیان/سداد/ملی/استرایپ/**کیف پول داخلی**، verify **همیشه با مبلغ محلی**، عدم اعتماد به Status بازگشتی، **Idempotency** کامل، PAN فقط mask، درگاه سفارشی فقط extend()).
- **نتایج:** ۹۸۵ تست / ۶٬۶۷۸ assertion • ۴۸/۴۸ موتور فعال • ~۹٬۳۰۰ خط src + ~۲٬۱۰۰ خط تست • بنچ‌ها سبز (xlsx 10k ≈ 105ms) • صفر وابستگی بیرونی.

### v0.7.1 — 2026-10-17 — سازگاری کامل ویندوز

- **رفع شد (پس از گزارش اجرای `tvn test` روی Windows — ۱۵ شکست → صفر):** `PathGuard` مشترک جدید (مسیر مطلق ویندوز/UNC، NTFS case-insensitive، **باگ prefix** `/app`↛`/application`) • api:docs/ResponseFactory/ReportExporter روی PathGuard (containment پیش از وجودسنجی — `/etc/passwd` ⇒ PathTraversalException) • Backup `collectFiles` (آرشیو خالی روی `C:\…`) • Tenancy charset ویندوزی • fixture MediaTest (تولید JPEG با gd در زمان اجرا — باگ latent از M6) • تست‌های capability-based (stream_socket_pair/symlink/POSIX perms — skip مستند) • ConsoleTest (PHP_EOL) • همگام‌سازی TVN_VERSION • **check-forbidden.php**: رد `.tv.php`ها، رفع مثبت کاذب F-EXTRACT (متد `->extract`)، معافیت مستند F-SHELL برای ScheduleManager — gate پس از M5 برای نخستین بار **سبز** (۱۰۰۷ فایل).
- **مستندات:** `docs/reports/windows-compatibility-fixes.md` (ریشه‌یابی ۱۵ مورد).
- **نتایج:** Linux: ۹۹۲ تست / ۶٬۷۰۴ assertion • ویندوز: ۹ skip مستند + ۶ اصلاح کد ⇒ صفر شکست.

### v0.8.0 — 2026-10-19 — فاز M8: ابزار + ممیزی جامع M0–M8

- **ممیزی جامع (نقشه: `docs/reports/M0-M8-audit-report.md`) — افزوده شد:** Facadeهای مفقود (Crypt/Lang/Cookie — یافتهٔ H1) • **ابزارهای QA (یافتهٔ H2):** `arch:check` (config-driven)، `security:scan` (token-based ضد false-positive)، `lint:php` سراسری، `bench:all` با `--fail-on-regression`، `style:check` صادقانه، `engine:validate`، `env:check` • دستورات جامانده (یافتهٔ H3): `down/up`، `module:enable/disable` • composer.json (phpstan/php-cs-fixer + phpstan.neon level max) • `INDEX.html` داشبورد • assertionهای PHPUnit-compat در TestCase.
- **موتورهای M8 — افزوده شد:** TVN-0081 **Upgrade** (pre-check→backup اجباری→maintenance→مسیر گام‌به‌گام نسخه‌ها→verify→up با **rollback خودکار**، DataPatch idempotent، ConfigMigrator روی .env، VersionResolver semver) • TVN-0078 **Admin** (CRUD-generator با ۱۰ route، **۲۷ نوع فیلد** + validation خودکار، Gate/whitelist/sanitize، jalali_date دوطرفه، منوی پویا، ویجت/chart **SVG بدون JS**، RTL کامل، Theme override) • TVN-0079 **Plugin** (manifest + **امضای Ed25519** + plugins.lock با SHA-256 per-file، PluginScanner توکنی، Sandbox با autoload append-only و capabilities runtime، Hook declare-محور با ایزولاسیون/timeout، چرخهٔ عمر کامل ۸ دستور) • TVN-0077 **Scaffold** (StubRenderer خودنوشته بدون eval، ۲۵ قالب، **۱۷ دستور make:*** با fields/module/domain، idempotency + sandbox مسیر، `Str::plural`) • TVN-0084 **Test کامل** (**Mock بدون eval** با ProxyGenerator تایپ‌دار (Union/DNF/variadic/by-ref)، MakesHttpRequests درون‌فرایندی، TestResponse کامل، RefreshDatabase، Factory + **Faker خودنوشتهٔ deterministic با دادهٔ فارسی**، `tvn test` جدید با paths/--group/--filter/--coverage/**--parallel**).
- **رفع شد:** 🐛 withCount alias (بی‌صدا هیچ ستونی تولید نمی‌کرد) + گارد امنیتی شناسه • 🐛 ARCH-006 در RequestStack • 🐛 دادهٔ manifest موتور view (namespace = path!) • 🧹 نویسه‌های CJK → **صفر CJK در کل مخزن** • 🐛 نشت state بین اجراها در ApiTest (throttle با clock ثابت) • 🐛 **انباشت @section بین رندرهای متوالی ViewFactory** (نشت در فرآیند مقیم) با begin/endViewRender • ترمیم ساختاری CHANGELOG.
- **نتایج:** ۱٬۲۳۵ تست پایه + ۱۶ تست QA سبز • arch:check پاک (۱۰۵۸ فایل) • security:scan: صفر high • engine:validate: ۵۲ موتور • ترجمه‌ها: صفر مفقود fa/en • M8: ۵/۵ تیکت.

### v0.9.0 — 2026-10-24 — فاز M9 گام ۱: زیرساخت کیت‌ها + کیت وبلاگ ✍️

- **افزوده شد:** **زیرساخت کیت** (DOC-090 §8): KitManifest/KitManager + `kit:list`/`kit:install` (زنجیرهٔ ۸ گامی idempotent) + `docs/schemas/kit.v1.json` • ثبت Provider ماژول‌ها در بوت (حلقهٔ مفقودهٔ DOC-022) • **۵ ماژول HMVC**: `tavaan.content` (Slug فارسی‌اول، Markdown escape-first، Feed/Sitemap)، `tavaan.comment` (پورت/آداپتر، moderation، thread تو‌در‌تو)، `tavaan.media` (پورت Media)، `tavaan.core-user` (جدول users + Authenticatable)، `tavaan.blog` (پیاده‌سازی کامل DOC-091: Post/Category/Tag، ۵ UseCase، PostPolicy، آرشیو جلالی، RSS/Atom، جست‌وجوی فارسی رویدادمحور، پنل admin) • کیت `tavaan.kit.blog` (۳ preset + theme RTL + roles + demo) • موتور notification: ArrayNotification (بدون کلاس موتور — ARCH-003) • rbac: policies اعلامی config + قالب object مجوزها • کلید `allow` در architecture.php (SDK مجاز: `Console\Command`).
- **رفع شد:** 🐛 بحرانی view: `@include` درون `@section` کل خروجی را از بین می‌برد • 🐛 module: چرخهٔ کاذب هم‌نام + `requires.modules` در مرتب‌سازی • 🐛 http: decode مسیر percent-encoded در `Request::create` • 🐛 rbac: Gate::policy و capture اتصال DB پیش از سوییچ تست.
- **نتایج:** ۱٬۳۴۲ تست / ۸٬۷۴۹ assertion (۹۱ تست جدید: E2E-01..05 وبلاگ، ۸ بردار XSS، فید/sitemap) • TVN-0091 → done.

### v0.10.0 — 2026-10-24 — فاز M9 گام ۲: کیت خبرگزاری 📰

- **افزوده شد:** ماژول `tavaan.news` (DOC-092 کامل): workflow انتشار پنج‌وضعیتی با ردیابی editor • خبر فوری (بنر + JSON + Broadcast) • زمان‌بندی انتشار (`news:publish-scheduled` روی schedule) • **کش tag-based** (TTLهای ۶۰/۳۰۰/۱۰s + invalidate) + ETag/304 • **AMP** • **JSON-LD NewsArticle** + OG + sitemap • دستهٔ تو‌در‌تو • گالری از ULIDهای media • related • آرشیو جلالی • RSS/Atom • نظرات با moderation • ArticlePolicy • پنل admin + کیت (۳ preset + theme خبری + ۵ نقش + demo).
- **رفع شد:** bind سراسری CommentStoreInterface حذف (هم‌زیستی blog+news) • گارد ثبت schedule در حالت testing.
- **نتایج:** ۱٬۳۶۹ تست / ۸٬۹۲۸ assertion (۲۷ جدید: E2E-01..06 + ماتریس ۴۰۳) • TVN-0092 → done.

### v0.11.0 — 2026-10-24 — فاز M9 گام ۳: کیت فروشگاه 🛒

- **افزوده شد:** ماژول `tavaan.shop` (DOC-093 — بزرگ‌ترین کیت M9): کاتالوگ (variant/دستهٔ تو‌در‌تو/ویژگی پویا) • سبد مهمان+کاربر • **PlaceOrder در تراکنش** (refresh قیمت از منبع معتبر→کوپن→مالیات/ارسال→reserve) • **موجودی race-safe** با UPDATE اتمیک شرطی + حرکت‌نامه + `shop.stock.low` • workflow شش‌وضعیتی سفارش (ship/deliver/cancel/refund — لغو=بازگشت موجودی+refund خودکار) • کوپن درصدی/ثابت با سقف/ظرفیت • PricingService • **پورت پرداخت ماژولی** (Sandbox قطعی + Engine واقعی) • نقد و بررسی با moderation • جست‌وجوی فارسی • کش tag-based • API کامل §7 • پنل admin + کیت (۳ preset + ۵ نقش + کوپن WELCOME1405).
- **رفع شد:** تست فیلتر شمارش روت‌های api (فرض ضمنی «تنها محصولات نمونه» با ماژول shop نقض شده بود).
- **نتایج:** ۱٬۴۲۵ تست (۵۶ جدید: E2E-01..08 شامل همزمانی آخرین موجودی) • TVN-0093 → done.

### v0.12.0 — 2026-10-24 — فاز M9 گام ۴: کیت انجمن 💬

- **افزوده شد:** ماژول `tavaan.forum` (DOC-094 کامل): تالار تو‌در‌تو با شمارنده‌های همگام • Markdown دو‌لایه • **رأی ±۱ toggle** + **Reputation/Karma** (delta = f(new)−f(old)) • **پاسخ برگزیدهٔ Q&A** • نقل‌قول (prefill + JSON) • **@mention** فارسی/لاتین با اعلان • اشتراک موضوع • **صف Moderation با flag** (auto_hide_threshold) • ban با انقضای خودکار • **ضداسپم سه‌لایه** (Throttle + FloodControl پنجرهٔ لغزان + honeypot + SpamFilter) • جست‌وجوی فارسی رویدادمحور • **Realtime/SSE بدون ext** (کانال thread) • پروفایل عمومی karma • پنل admin (قفل/سنجاق/جابجایی/ban) + کیت.
- **رفع شد:** search `NativeDriver::suggest` — کلید int پس از json round-trip ⇒ TypeError (کشف توسط E2E-05 انجمن).
- **نتایج:** ۱٬۴۶۸ تست / ۹٬۴۹۱ assertion (۴۲ جدید: E2E-01..07) • TVN-0094 → done.

### v0.13.0 — 2026-10-25 — فاز M9 گام ۵: کیت آموزش آنلاین (LMS) 🎓

- **افزوده شد:** ماژول `tavaan.lms` (DOC-095 کامل): کاتالوگ دوره (ویدیو/متن/آزمون/پیوست + **پیش‌نیاز با محافظ چرخه**) • ثبت‌نام رایگان/پولی (verify با **مبلغ محلی**، idempotent؛ پورت PaymentGateway با دو آداپتر) • پیشرفت درس/دوره • آزمون (max_attempts/time_limit، نمره‌دهی خودکار با **AnswerNormalizer فارسی‌آگاه** (ارقام/ی‌ک/نیم‌فاصله/`|`)، دستی در صف grading) • **گواهی با Workflow** (AC-LMS-08: serial **Crockford base32 با رقم کنترل** — tamper بدون DB رد، verify عمومی، ابطال) • Q&A روی پورت comment • اعلان‌ها • Markdown sanitize + allowlist ویدیو • گزارش پیشرفت + CSV • پنل admin + `lms:recompute-progress` + کیت.
- **رفع شد:** `DatabaseBroker::prune` با DateTime مستقیم → **نقض «Clock منبع یگانه زمان»** + flake ~۲٪ → Clock::utcNow • `user_agent` خالی http-client از .env → fallback.
- **نتایج:** ۱٬۵۰۸ تست / ۹٬۸۱۴ assertion (۴۰ جدید: E2E-01..07) • TVN-0095 → done.

### v0.14.0 — 2026-10-25 — فاز M9 گام ۶: کیت CRM 🤝

- **افزوده شد:** ماژول `tavaan.crm` (DOC-096 کامل): مخاطب با **کدملی رمزنگاری‌شده at-rest** (AES-GCM+AAD، fail-safe، mask) • سرنخ با **Lead Scoring خودکار طبق الگوریتم مستند §3.2** • **Sales Pipeline با Workflow** (نمونه per opportunity + ماتریس stage + **forecast وزنی int**) • فعالیت‌ها پلی‌مورف • **تیکت با SLA** (2/4/8/24h، pending=توقف، **escalation خودکار idempotent** روی schedule) • **پرتال عمومی پشتیبانی** • کمپین ایمیل/پیامک (audience_query → personalize → **idempotent در cron**) • **API یکپارچگی §7** • **sync رویدادی از shop** (بدون ارجاع کلاسی) • ۷ گزارش + CSV ضد injection • ۲۵ مجوز + ۴ Policy مالکیت + کیت.
- **نتایج:** ۱٬۵۳۴ تست / ۱۰٬۱۴۵ assertion (۲۶ جدید: E2E-01..06 + ماتریس نقش + XSS) • TVN-0096 → done.

### v0.15.0 — 2026-10-25 — فاز M9 گام ۷: کیت ERP 🏭

- **افزوده شد:** ماژول `tavaan.erp` (DOC-097 — پیچیده‌ترین کیت M9؛ hexagonal با **۶ بافتهٔ مرزی** با ارتباط فقط Outbox طبق ADR-0009): **Accounting** (chart درختی، سند دوطرفه با **قانون طلایی SUM(debit)===SUM(credit) int minor-unit**، posted immutable + سند معکوس، دورهٔ مالی با قفل، **بستن حساب** → ترازنامهٔ متوازن) • **Inventory** (حرکات append-only، **بهایابی میانگین موزون**) • **Procurement** (PO → **تأیید سلسله‌مراتبی بر اساس مبلغ** با ApprovalRouter + workflow واقعی، رسید/فاکتور/پرداخت) • **Sales** (حواله با COGS snapshot → فاکتور → دریافت) • **Production** (**BOM نسخه‌دار** با رد چرخه، work order با **بهای تمام‌شده**) • **HR** (national_id رمزنگاری‌شده، Payroll با مالیات آستانه‌دار → سند) • **Outbox** با claim اتمیک + ۶ listener حسابداری بدون نشت • **Audit**: ۲۲ رویداد مالی در hash chain + tamper-detection • ۸ گزارش (تراز آزمایشی/سود و زیان/ترازنامه/…) + CSV • ۲۴ جدول + ۲۸ مجوز + کیت.
- **رفع شد:** 🔴 **SessionGuard (باگ نهفتهٔ HTTP)**: نشست به clone درخواست بسته می‌شد ولی RequestStack اصلی را نگه می‌داشت ⇒ **احراز هویت نشست‌محور روی HTTP واقعی هرگز کار نمی‌کرد** (تست‌های actingAs این مسیر را رد نمی‌کردند) → fallback به نشست scope-شده + تست رگرسیون روی kernel واقعی • `Request::create` هدر HTTP_COOKIE را پارس نمی‌کرد.
- **تغییر یافت:** allow صریح `Audit\AuditEvent` در SDK ماژول‌ها (config/architecture.php).
- **نتایج:** ۱٬۵۸۳ تست / ۱۰٬۴۲۴ assertion (۴۹ جدید: E2E-01..08 + جاروی قانون طلایی روی همهٔ اسناد) • TVN-0097 → done.

### v0.16.0 — 2026-10-26 — فاز M9 گام ۸: کیت BPMS 🔄 — پایان M9: هر ۸ کیت سبز

- **افزوده شد:** ماژول `tavaan.bpms` (DOC-098 کامل): **فرایندساز** (draft→publish با ModelValidator کامل→activate/retire + **نسخه‌بندی** + نمودار **SVG سرور-ساید**/Mermaid) • **موتور اجرا** عنصر-محور با **توکن** (userTask با assignee initiator/user/role/group، serviceTask، exclusiveGateway، **parallelGateway fork/join با شمارش ورودی**، **claim اتمیک**، delegate، suspend/resume/terminate، محافظ‌های DoS، همه در DB::transaction) • **Expression Evaluator امن** (AC-BPMS-12): lexer + recursive-descent + eval روی AST **بدون eval()** — ممنوعیت تابع/`$`/backtick/`;` با خطای قطعی، دو لایه (publish + runtime) • **فرم‌ساز پویا** (۱۰ نوع فیلد → compile به قواعد validation + normalize با **حذف کلیدهای خارج از schema**) • Service Tasks با ActionRegistry ثبت‌شده (هرگز از ورودی کاربر) • **Timer/Escalation** (ISO-8601، idempotent، `bpms:check-timers`) • **پایش** (bottleneck/SLA/workload + CSV ضد injection + Realtime/SSE بدون نشت متغیر) • WorkflowBridge (لایهٔ تاریخچه/visualization) • ۶ رویداد + Audit با hash chain (allow صریح SDK) • پنل ۸ ویو + وب ۶ ویو + کیت + `smoke-bpms.php` با نشست کوکی‌دار (۲۸ check).
- **نتایج:** ۱٬۶۷۰ تست / ۱۰٬۹۵۶ assertion (۸۷ جدید: ۱۵ تست ExpressionEvaluator شامل تزریق `system()`/backtick + E2E-01..10) • **M9: ۸ از ۸ — جمع ۶۳/۶۳ تیکت** • TVN-0098 → done.

### v0.17.0 — 2026-10-26 — رفع نقدهای 1.0 + مستندسازی تیکت‌محور + آماده‌سازی انتشار 🩺

- **افزوده شد:** پریزیمیتیو **«skip مستند»** در موتور test (`TestSkipped` + `TestCase::skip(reason)` — DOC-084E §7.1) • `migrate:rollback --path=` + `Migrator::rollback(steps, paths)` (TECH-TOOLING-01) • `QueueCommandsTest` (۱۳ تست in-process؛ فرمان‌های صف ۲–۴٪ → ۷۰–۱۰۰٪، موتور queue → ۷۹٫۱٪) • **`tools/scripts/generate-ticket-docs.php`** → **README برای هر ۵۲ موتور** + **TICKET.md برای ۷۳ ساختار** (الزام مغفول DoD §2) • **DOC-122 رجیستری بدهی فنی** (۱۵ خطای رفع‌شده + ۱۷ بدهی باز) • **DOC-133 راهنمای استقرار و Hardening** (nginx/php-fpm/OPcache+preload/defense-in-depth) • **ADR-0017 process-per-request** (هدف RPS≥۳۰۰۰ به 1.x موکول — پاسخ به نقد §1.3).
- **تغییر یافت:** تیکت شبح TVN-0043 از داشبورد حذف (۶۳→۶۲ — BUG-DOC-01) • «۴۸ موتور» → ۵۲ در README (BUG-DOC-02) • DOC-049/DOC-084E به‌روز • manifest: DOC-122/DOC-133/ADR-0017 → ۱۲۵ سند.
- **رفع شد:** خطای کاذب QaToolsTest در فشار حافظه (`proc_open(): Fork failed`) → skip مستند (BUG-QA-01) • `migrate:rollback` با مسیر سفارشی (BUG-TOOL-01).
- **نتایج:** ۱٬۷۰۴ تست / ۱۱٬۱۱۵ assertion • همهٔ گیت‌ها سبز.

### v0.18.0 — 2026-10-27 — آماده‌سازی هاست اشتراکی + پیکربندی سه‌سطحی php.ini 🏠

- **افزوده شد:** **DOC-134 راهنمای هاست اشتراکی** (پاسخ «هاست اشتراکی چیست» + شبیه‌سازی و **اندازه‌گیری واقعی** منابع + سه سطح php.ini هم‌تراز نمونه‌های وردپرس/لاراول/سیمفونی) • **۴ پروفایل `examples/hosting/php-{minimum,ideal,heavy,restricted}.ini`** (۱۲۸/۲۵۶/۵۱۲M + بدترین حالت disable_functions) با کامنت‌های evidence-based • **`examples/hosting/preload.php`** (OPcache Preload کل classmap ~۱٬۵۸۰ فایل؛ تست: preloaded=1580 failed=0) • **۶ ابزار اندازه‌گیری** (`shared-host-bench`/`bench-server`/`shared-host-measure`/`worker-rss`/`memory-ceiling`/`boot-profile`).
- **یافته‌های اندازه‌گیری‌شده:** حافظهٔ هر درخواست ۴–۶MB heap (۲۰× حاشیه زیر ۱۲۸M) • RSS ورکر گرم ۳۷–۴۶MB (۱۰–۱۲ ورکر در ۱GB) • **p50 با OPcache: ۲٫۵–۳٫۵ms سبک / ۶–۹ms با DB** (در برابر ۸–۱۶ms بدون آن) • سقف گزارش: ۵۰k→۱۲۶MB، ۱۰۰k→۲۵۱MB، ۱۵۰k→۲۸۵MB؛ **OOM زیر ۱۲۸M مدیریت‌شده** توسط ErrorHandler (کرش خام نه) • throughput ~۶۰–۸۳ RPS/ورکر با **۰ خطا** • سازگاری disable_functions: **۱۰۰٪ مسیر وب** • `opcache.save_comments=1` **حیاتی** (DI تَوان docblock-محور).
- **تغییر یافت:** manifest → ۱۲۶ سند • DOC-133/README ارجاع متقابل • نگارش → 0.18.0.
- **کامیت/تگ:** خط پایهٔ `0d13b6a` (tag v0.18.0) — نخستین کامیت واقعی git این مخزن.

### v0.19.0 — 2026-10-27 — یکپارچه‌سازی محیط توسعه و Git + دفتر کل تاریخچه 🔧 (همین نگارش)

- **افزوده شد:** **DOC-135 (همین سند)** — یکپارچه‌سازی محیط توسعه و Git + دفتر کل جمع‌آوری‌شدهٔ همهٔ نسخه‌ها • **`tools/git-hooks/pre-commit`** (lint فایل‌های stage‌شده + check-forbidden + arch:check ≈ ۶–۷s؛ حالت FULL اختیاری) • **`tools/git-hooks/commit-msg`** (راستی‌آزمایی نرم قرارداد §3.4) • **`tools/scripts/git-setup.sh`** (راه‌اندازی idempotent: init/هویت/hooksPath/راستی‌آزمایی نشت) • **مخزن git**: init + خط پایهٔ `0d13b6a` + تگ‌های v0.18.0/v0.19.0.
- **تغییر یافت:** نگارش ۰٫۱۸٫۰ → ۰٫۱۹٫۰ (چهار محل §3.5) • `docs/manifest.json` (ثبت DOC-135 → **۱۲۷ سند**) • `docs/README.md` (سطر شاخص) • `CONTRIBUTING.md` (ارجاع به DOC-135).
- **رفع شد:** کهنگی `framework.version` در `docs/manifest.json` (۰٫۱۷٫۰ مانده بود → هم‌تراز شد) • 🧹 تایپوی سیریلیک در بخش ۰٫۲٫۰ CHANGELOG («Hijri табular» → «Hijri tabular» — الگوی اسکن نویز غیرفارسی ۰٫۸٫۰).
- **نتایج:** همهٔ گیت‌های §2.4 سبز (lint:php ۲٬۲۲۴ فایل • arch:check ۱٬۵۷۳ • security:scan ۰ high • engine:validate ۵۲ • module:validate ۶۴ • env:check drift=0 • test ۱٬۷۰۴/۱۱٬۱۱۵) — خروجی واقعی در کامیت انتشار.
- **کامیت/تگ:** ‏`d7ae7da` (tag `v0.19.0`) — hash کامل: `git rev-list -n 1 v0.19.0`.

---

## ۵. وضعیت فعلی مخزن (در زمان نگارش)

```text
$ git log --oneline --decorate        # در زمان تگ v0.19.0
d7ae7da (tag: v0.19.0) افزوده شد: DOC-135 — یکپارچه‌سازی محیط توسعه و Git + دفتر کل تاریخچهٔ نسخه‌ها (v0.19.0)
0d13b6a (tag: v0.18.0) پایه‌گذاری مخزن Git — وضعیت کامل نگارش 0.18.0 (خط پایهٔ بازسازی‌شده)
```

> hash کامل `v0.19.0` = `git rev-list -n 1 v0.19.0`. کامیتِ همگام‌سازی همین
> اعداد (جایگزینی placeholder) طبعاً **پس از** نگارش سند ساخته می‌شود
> (محدودیت ذاتی خودارجاعی) — فهرست زنده با `git log` قابل بازیابی است.

| سنجه | مقدار |
|---|---|
| فایل‌های track‌شده | ۲٬۸۶۳ |
| فایل‌های PHP | ۲٬۲۵۰ (۲۴۸٬۶۹۷ خط) |
| مستندات markdown در `docs/` | ۱۴۳ فایل (۱۲۷ سند ثبت‌شده در manifest) |
| موتورها / ماژول‌ها / کیت‌ها | ۵۲ / ۱۲ / ۸ |
| تست‌ها | ۱٬۷۰۴ تست / ۱۱٬۱۱۵ assertion / ۱۱ skip مستند |
| گیت‌ها (آخرین اجرا) | همه سبز — §2.4 با زمان‌های واقعی |
| تگ‌ها | `v0.18.0` (خط پایه)، `v0.19.0` (این نگارش) |

---

## ۶. جمع‌بندی فرمان‌ها (Cheat Sheet)

```bash
# راه‌اندازی (یک بار / آغاز هر نشست sandbox)
bash tools/scripts/git-setup.sh "نام شما" you@example.com

# گردشکار روزانه
git switch -c feature/TVN-0100-slug     # شاخهٔ تیکت
# … کار + تست …
php bin/tvn test                        # گیت اصلی (~۲ دقیقه)
git add -A && git commit                # hookها خودکار: staged-lint + forbidden + arch

# کامیت با دروازهٔ کامل
TVN_HOOK_FULL=1 git commit              # + lint سراسری + کل مجموعه تست

# انتشار
# چک‌لیست §3.7 → bump چهار‌گانه → CHANGELOG → manifest → commit → tag
git tag -a v0.19.1 -m "نگارش ۰.۱۹.۱ — <عنوان>"

# مرور تاریخچه
git log --oneline --decorate            # کامیت‌های واقعی (از v0.18.0)
git show v0.18.0 --stat                 # جزئیات یک تگ
# تاریخچهٔ پیش از خط پایه: §4 همین سند + CHANGELOG.md
```

---

*پایان DOC-135 — این سند با هر انتشار به‌روزرسانی می‌شود: بند تازه در §4 + به‌روزرسانی §5.*
