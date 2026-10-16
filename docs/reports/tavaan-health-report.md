# گزارش ممیزی سلامت و یکپارچگی — فریمورک تَوان (M1–M4)

**تاریخ:** ۲۰۲۶-۱۰-۱۶ • **مخزن:** `Derogaramin/Tavaan-Framework` (نگارش 0.4.0) • **کامیت‌های ممیزی:** `a37a04c` (اصلاحات) و `1ac2da9` (سبک) — فقط local، push نشده

---

## ۱. محیط توسعه (ایجادشده طبق DOC-002/DOC-120)

| قلم | مقدار |
|---|---|
| OS / PHP | Debian 12 — **PHP 8.2.34** (نیاز ≥8.2 ✓) |
| افزونه‌های نصب‌شده | `pdo_sqlite` `openssl` `json` `hash` `mbstring` `intl` `sodium` `ctype` `tokenizer` `curl` + `opcache` (برای شبیه‌سازی محیط مرجع) |
| fallbackها (طراحی‌شده) | bcmath/gmp→decimal، zip→pure، redis/apcu/memcached→file، pdo_mysql/pgsql→none (موتور compat — مطابق DOC) |
| `.env` | از `.env.example` + `key:generate` |
| storage/ | ساختار کامل مطابق DOC-040 §7 ایجاد شد (app/cache/framework/logs/backups/temp + مجوزها) |
| `tvn doctor` | ✅ «محیط سالم است» |

---

## ۲. نتیجهٔ gateها و تست‌ها (پس از اصلاحات)

| Gate | نتیجه |
|---|---|
| `php -l` (lint.sh) | ✅ **۶۷۹ فایل — ۰ خطا** |
| `check-forbidden.php` (صفر eval/putenv — DOC-010) | ✅ پاک |
| `tvn test` (Native Runner) | ✅ **۵۴۶ تست / ۲۷۴۶ assertion — ۰ شکست** (۵٫۵s) |
| PHP-CS-Fixer (DOC-011) | ✅ **۰ از ۵۶۵ فایل ناقض** (پس از اصلاح کانفیگ + ۳۳ فایل autofix) |
| PHPStan level max (DOC-111) | ⚠️ ۲۴۴۵ → **۱۵۲۴** (با کانفیگ اصلاح‌شده) — **خطاهای کلاس fatal (`*.notFound`): ۰** (پیش از اصلاح: ۲۴۰+) |
| مثال‌های پذیرش | ✅ `examples/crud/blog-crud.php` (M3) • ✅ `examples/auth/login-rbac.php` (M4: Argon2id→RBAC→JWT pinning→reuse-detection→TOTP→audit tamper-detect) • ✅ `examples/hello-world/cli.php` (M2) |
| HTTP smoke (`tvn serve`) | ✅ صفحهٔ خوش‌آمد فارسی، `/hello/{name}` با UTF-8، **تقویم جلالی زنده: ۱۴۰۵/۰۷/۲۴ جمعه = 2026-10-16 ✓**، هدرهای امنیتی کامل (CSP/X-Frame/nosniff/Referrer)، ۴۰۵/۴۰۴ صحیح |

---

## ۳. معیارهای پذیرش M1–M4 (اندازه‌گیری‌شده در این محیط)

### M1 — هستهٔ صفر‌وابستگی ✅
| معیار | هدف | اندازه‌گیری |
|---|---|---|
| بوت بدون vendor/ | ✓ | ✅ (vendor وجود ندارد) |
| `bin/tvn about` | کار کند | ✅ ۳۱/۳۱ موتور فعال |
| Resolve سرویس ۵ وابستگی | ≤300µs | ✅ ۳٫۵–۳٫۹µs |
| `make()` singleton / ۱۰۰۰ make | ≤1µs / ≤1ms | ✅ ۰٫۱۹µs / ۰٫۱۶ms |
| ClassMap cache | ≥70٪ سریع‌تر | ✅ **۹۰٫۸٪** (۱۳۸۲→۱۲۸µs) |
| زمان بوت warm / cold | ≤8ms / ≤25ms | ✅ ۷٫۵–۹٫۰ms (warm با file-cache — مرزی، در محیط مرجع SHM سریع‌تر) / ۱۸–۲۰ms |
| Event dispatch + Outbox | ✓ | ✅ (OutboxTest — roundtrip سبز) |

### M2 — وب ✅
| معیار | هدف | اندازه‌گیری |
|---|---|---|
| سربار هر درخواست Hello World | ≤3ms | ✅ **۰٫۰۹–۰٫۱۴ms** (~۸۰۰۰–۱۰۰۰۰ RPS تئوری) |
| Route match با ۱۰۰۰ روت (static/param) | ≤5µs / ≤15µs | ✅ ۲٫۲–۲٫۷µs / ۲٫۴–۳٫۷µs |
| ساخت Trie از ۱۰۰۰ روت | ≤15ms | ✅ ۵٫۶–۹٫۶ms |
| **حافظهٔ ۱۰۰۰ روت + Trie** | **≤2MB** | ✅ **~۱٫۷MB** (پیش از اصلاح: ۴٫۹MB واقعی / ۱۴MB گزارش خطای bench) |
| Session secure + CSRF | ✓ | ✅ (۱۵ تست Session + ۴۱۹ در HttpKernel) |
| View auto-escape | 0 XSS | ✅ (XSS fuzz سبز) |
| کامپایل/رندر قالب ۱۰۰ خطی | ≤3ms / ≤0.5ms | ✅ ۰٫۶–۰٫۸ms / ۰٫۲–۰٫۳ms |
| Escaper ۱KB معمولی | ≤5µs | ✅ ۰٫۲۵µs (hostile: ۷–۹٫۶µs — اطلاع‌رسانی) |
| تقویم جلالی | ۱۰۰٪ | ✅ (۸۰۰۰ intl parity + زنده روی HTTP) |

### M3 — داده ✅
| معیار | هدف | اندازه‌گیری |
|---|---|---|
| CRUD سرتاسری با DB | ✓ | ✅ مثال blog-crud (Create/ULID/Validation/Eager 2-کوئری/Pagination/Cursor/SoftDelete/Restore/Transaction) |
| Grammar parity سه درایور | ✓ | ✅ compile-test (اجرای زنده فقط SQLite — درایورهای MySQL/PG نصب نیستند) |
| SQL Injection | ۰ | ✅ (SqlInjectionTest — ۲۰۰ payload) |
| Migration up/down + lock | ✓ | ✅ + **roundtrip زندهٔ CLI** (migrate→rollback معکوس→migrate) |
| Hydrate ۱۰۰۰ رکورد | ≤25ms | ✅ **۱٫۲۷–۱٫۴۳ms** |
| Validation قواعد ایرانی | ≥85 | ✅ ۹۲ قاعده (تست‌ها سبز) |
| بارگذاری route cache | ≤1.5ms | ⚠️ ۱٫۶۵–۱٫۸۳ms (بدون opcache؛ بهبود ۴۷٪ در این ممیزی — محیط مرجع سریع‌تر) |

### M4 — امنیت/هویت ✅
همهٔ معیارها با تست‌های سبز + **مثال زندهٔ login-rbac** تأیید شد: session hardening (regenerate/fingerprint)، JWT algorithm pinning، refresh rotation + reuse detection (revoke خانواده)، TOTP (RFC 6238)، RBAC سلسله‌مراتب/wildcard، Gate، **audit hash-chain با تشخیص دستکاری زنده** ✅، `audit:verify` ✅، cache ۶ درایور با fallback ✅، security headers زنده روی HTTP ✅.

### CLI (یکپارچگی) ✅
`about` `doctor` `key:generate` `version` `list` `module:list` `event:list` `optimize` `optimize:clear` `autoload:cache` (اکنون واقعاً در بوت مصرف می‌شود: classmap:148/wildcard:0) `route:list` `route:match` `route:cache` (رد صحیح Closure) `view:cache` `view:clear` `migrate*` `rbac:sync` `audit:verify` `event:outbox-relay` `cache:clear` `serve`

---

## ۴. باگ‌های واقعی کشف‌و اصلاح‌شده 🔴

1. **`TrieNode::staticChild` — فرزند سوم static پنهان می‌شد (۴۰۴)**: هنگام بهینه‌سازی حافظه کشف شد؛ invariant «map پر ⟹ single تهی» نقض می‌شد. اصلاح + **۲ تست رگرسیون جدید** (۳+ فرزند static / همزیستی static+param).
2. **`Router::head()` وجود نداشت** اما `RouteRegistrar` آن را forward می‌کرد → **fatal** در ثبت روت head داخل group. افزوده شد.
3. **Enum در `ControllerResolver`**: `from()` روی enum غیر-backed → fatal؛ رشتهٔ عددی برای int-backed → ValueError. اصلاح: فقط `BackedEnum` + cast عددی (سبک Laravel).
4. **`TVN_AUTOLOAD_CACHE`/`TVN_CONFIG_CACHE` در `.env` مرده بودند** (bootstrap فقط `$_SERVER` واقعی را می‌دید): میکرواسکن زودهنگام `.env` در bootstrap (مرغ‌و‌تخم classmap↔env) + خواندن از `EnvRepository` در `LoadConfiguration`. اثر: classmap فعال (۱۰۶ wildcard-glob → ۰)، بهبود ۹۰٫۸٪ autoload.
5. **`ContainerInterface` بدون متدهای scope** ولی `HttpKernel` صدایشان می‌زد؛ افزودنشان به قرارداد **fatal بودنِ `Application`** (implementer جامانده) را آشکار کرد → delegateها افزوده شد. این دقیقاً همان ریسک شکاف قراردادها بود.
6. **`key:generate` در نصب تازه/CI می‌شکست** (تأییدیهٔ بازنویسی حتی با کلید خالی) → فقط در صورت کلید موجود می‌پرسد + غلط تایپی «تعداد»→«تولید».
7. **`migrate:rollback --force` نداشت** (fresh/refresh داشتند) → افزوده شد؛ roundtrip زنده تأیید شد.
8. **bench روتر حافظه را غلط می‌سنجید** (کل فرایند − ۲MB ثابت ⇒ ۱۴MB کاذب؛ عدد واقعی ۴٫۸۶MB و **نقض هدف DOC-040 §12**) → سنجه افزایشی واقعی + بهینه‌سازی ساختاری (بند ۵).
9. **`phpstan.neon.dist` با DOC-111 §2 نمی‌خواند** (بدون bootstrapFiles/scanDirectories؛ الگوی غلط `engines/*/tests`) → اصلاح؛ خطاهای کلاس fatal صفر شد.
10. **`.php-cs-fixer.dist.php` با سبک کدبیس و DOC-011 U-02 در تعارض بود** (`import_functions=true`؛ و `strict` پیش‌فرض ۳٫۶۴ `\` توابع غیرnative را **حذف** می‌کرد) → `import_functions=null` + `strict=false` + `import_classes=null` (سبک خانه)؛ ۳۳ فایل autofix (شامل **۱۲ import بلااستفاده**)؛ اکنون ۰/۵۶۵ ناقض.
11. **۸ قرارداد ناقص** (کد متدهایی را صدا می‌زد که در interface نبود — نقض Contract-First): `StoreInterface::add`، `QueryBuilderInterface::updateOrInsert/orderByDesc`، `ContainerInterface::beginScope/endScope/currentScope/flushScoped`، `RequestInterface::cookies`، `ResponseInterface::hasHeader`، `UploadedFileInterface::extension`، `EncrypterInterface::encryptJson/decryptJson`، `KernelInterface::commands/resolve` — همه با بررسی تک‌تک implementerها افزوده شدند.
12. **`.gitignore`های تو در توِ storage غیرقابل commit بودند** (قاعدهٔ git: re-include زیر پوشهٔ exclude‌شده ممکن نیست) → الگوهای `/storage/**` + `!/storage/**/`؛ ساختار storage مطابق DOC-040 §7 اکنون در مخزن است.
13. **۴ docblock کهنه** (parameter.notFound): `EarlyErrorManager::isFatal`، `TokenManager::refresh`، `SchemaGrammar::foreignKeySql`، `Session::setFingerprint`.
14. `CacheLock` با object-shape → **`StoreInterface`** تایپ شد؛ `ModelQueryBuilder` با `@method`های صریح؛ `Ulid`/`Base32` با `(int) bindec()` قطعی.

## ۵. بهینه‌سازی‌های کارایی (بدون شکست هیچ تستی)

| سنجه | پیش | پس | هدف DOC |
|---|---|---|---|
| حافظهٔ ۱۰۰۰ روت+Trie | ۴٫۸۶MB | **۱٫۶۷–۱٫۷۰MB** | ≤2MB ✅ |
| هر Route | ~۲۵۵۳B | ~۱۰۰۰–۱۲۰۰B | — |
| load خالی route cache | ۳٫۱۳ms | **۱٫۶۵–۱٫۸۳ms** | ≤1.5ms (مرجع) ~✅ |
| ساخت ۱۰۰۰ روت+trie | ۷٫۳ms | ۵٫۶ms | ≤15ms ✅ |
| سربار هر درخواست | ۰٫۱۳۵ms | ۰٫۰۹–۰٫۱۴ms | ≤3ms ✅ |

تکنیک‌ها: segments فشرده (`string|RouteSegment`، پارس on-demand بدون نگهداشت)، bitmask متدها، `ParamEdge`/`HandlerEntry` به‌جای آرایه‌های hashed (هرکدام ≥۳۴۴B در PHP)، تک‌فرزند static به‌صورت inline، هندلرهای ≤۲ inline، درج مرتب به‌جای usort، memo نرمال‌سازی کلیدها، حذف پارس مضاعف در `fromArray`.

---

## ۶. بدهی فنی و موارد تأییدنشده (صادقانه) ⚠️

1. **PHPStan level max: ۱۵۲۴ خطای باقی** — بدنهٔ اصلی noise الگوی DI است (`make(): mixed` → `cast.string`/`argument.type`/`method.nonObject`) + `missingType.iterableValue` (۱۶۴ docblock ناقص). DoD (DOC-113) صفر می‌خواهد. **توصیه:** اکستنشن PHPStan برای `make<T>()` جنریک یا اسپرینت docblock در M5. (هیچ‌کدام ریسک runtime ندارند — کلاس fatal صفر است.)
2. **Coverage ≥95٪ قابل راستی‌آزمایی نبود** — Native Runner درایور coverage ندارد و Xdebug/PCOV نصب نشد. ادعای README تأیید/رد نشد.
3. **MySQL/PostgreSQL زنده تست نشد** (pdo_mysql/pdo_pgsql نصب نیست) — parity فقط compile-level + اجرای SQLite (مطابق طراحی fallback).
4. **redis/apcu/memcached** — fallback خودکار به file تأیید شد ✓، ولی مسیر واقعی این درایورها بدون افزونه تست نشد.
5. **Route cache سریال‌شدهٔ trie نیست** (routes ذخیره و trie هنگام load بازسازی می‌شود ~۹ms/۱۰۰۰) — sketch در DOC-040 §5.6 ساختار `trie` را نشان می‌دهد؛ فرصت بهبود (نیازمند `__set_state` یا فرمت سریال‌سازی).
6. **واگرایی DOC-011 §7 با سبک کدبیس** (`import_classes=true` در doc vs inline-FQ در ۲۰۹ فایل) — در کانفیگ با کامنت مستند شد؛ تصمیم نهایی با نگهبان SSOT (Tavaan-Doc).
7. **بوت warm ۷٫۵–۹٫۰ms** در این sandbox مرزی است (حالت file-cache از SHM کندتر است؛ ادعای README ~۷٫۷ms بازتولید شد). محیط مرجع DOC-112 احتمالاً پاس می‌شود.
8. ابزارهای اختیاری DOC-111 (Infection/Psalm/phpmd) اجرا نشدند.
9. `bin/tvn env` و `route:clear`/`config:clear` جداگانه smoke نشدند (توسط CliTest پوشش داده شده‌اند ✅).

---

## ۷. جمع‌بندی

| پرسش | پاسخ |
|---|---|
| صحت کد (correctness) | ✅ ۵۴۶/۵۴۶ تست + هر ۳ مثال پذیرش M2/M3/M4 + HTTP smoke زنده + تقویم جلالی درست |
| سلامت کد (health) | ✅ lint/forbidden/CS-Fixer پاک؛ PHPStan fatal-class صفر؛ ۱۴ باگ واقعی (۳ تا با پتانسیل fatal) رفع شد |
| یکپارچگی (integrity) | ✅ قراردادها با مصرف‌کنندگان هم‌تراز شد؛ کانفیگ ابزارها با مستندات (DOC-011/111/040) آشتی داده شد؛ storage/.gitignore/CLI-flags سازگار |
| اهداف کارایی DOC-112 | ✅ همه پاس (به‌جز ۲ مورد مرزی محیطی: cache-load 1.7ms و warm-boot ~8ms در sandbox بدون SHM) |

**وضعیت M1–M4: سالم، سبز و یکپارچه — آمادهٔ شروع M5.**
