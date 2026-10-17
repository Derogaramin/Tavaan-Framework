# گزارش رفع ناسازگاری‌های ویندوز — نگارش 0.7.1

**تاریخ:** ۲۰۲۶-۱۰-۱۷ • **محرک:** خروجی `php bin/tvn test` روی ویندوز (۱۵ شکست از ۹۹۲)
**نتیجه:** `۹۹۲ تست / ۶۷۰۴ assertion سبز` روی Linux • همهٔ مسیرهای ویندوزی به‌صورت تدافعی اصلاح شدند • تست‌های M7 (Workflow/Report/Payment) روی ویندوز هم از ابتدا سبز بودند

---

## ۱. خلاصهٔ دسته‌بندی‌شده

| # | تست/نشانه | دسته | ریشه | اصلاح |
|---|---|---|---|---|
| 1 | `ApiTest::testApiDocsCommandProducesYamlFile` | مسیر | مقایسهٔ `str_starts_with` با `/` روی خروجی `realpath` ویندوز (backslash) | `PathGuard` + تشخیص مسیر مطلق `C:\…` |
| 2–3 | `BackupTest` (۲ مورد) | مسیر | `$path[0] === '/'` → مسیر `C:\…` نسبی فرض و به base الحاق شد → صفر فایل جمع‌شده | تشخیص مطلق cross-platform + `isExcluded` مبتنی بر normalize |
| 4 | `ConsoleTest::testOutputColorOverride` | خروجی | `Output` از `PHP_EOL` استفاده می‌کند (ویندوز `\r\n`) ولی تست `\n` انتظار داشت | assert با `PHP_EOL` |
| 5,7 | `FilesystemTest` (symlink ×۲) | قابلیت OS | ساخت symlink در ویندوز بدون ادمین/Developer Mode → `Permission denied` | probe عملی + skip مستند (موتور از قبل با `@symlink` graceful است) |
| 6 | `FilesystemTest::testVisibilityLocal` | قابلیت OS | NTFS بیت‌های مجوز POSIX ندارد؛ `chmod(0600)` فقط پرچم read-only است | skip مستند در ویندوز |
| 8 | `ResponseTest::testFileOutsideBasePathRejected` | مسیر | existence-check پیش از containment → `/etc/passwd` (وجود ندارد در ویندوز) → `FileNotFound` به‌جای `PathTraversal` | containment اول با probe جدِّ موجود + مقایسه با separator (باگ prefix هم رفع شد) |
| 9–11 | `MailTest` (۳ مورد) | قابلیت OS | `stream_socket_pair()` در ویندوز پشتیبانی نمی‌شود (خطای 10042) | guard پلتفرمی + skip مستند |
| 12 | `MediaTest::testResizeThumbnailConvert…` | fixture خراب | base64 فیکسچر «کوچک‌ترین JPEG» جدول Huffman دست‌ساز دارد که libjpeg سخت‌گیر (gd جدید ویندوز **و** دیبیان) رد می‌کند — «Bogus Huffman table definition» | تولید fixture با خودِ gd در زمان اجرا (همیشه برای libjpeg همان محیط معتبر) |
| 13 | `RealtimeTest::testWebSocketServer…` | قابلیت OS | همان `stream_socket_pair` — guard موجود فقط `function_exists` بود (در ویندوز تابع *هست* ولی شکست می‌خورد) | افزودن `PHP_OS_FAMILY === 'Windows'` به guard |
| 14–15 | `TenancyTest` Strategy B (۲ مورد) | مسیر | charset محافظ `^[A-Za-z0-9_/.-]+$` → backslash و `C:` رد شدند → `tenancy.unsafe_identifier` | charset ویندوزی (`\` و `:`) فقط روی ویندوز؛ `..` همچنان در همهٔ پلتفرم‌ها رد می‌شود |

---

## ۲. جزئیات اصلاحات موتور (کد)

### ۲٫۱ `core/Support/PathGuard.php` (جدید)
ابزار مشترک containment که سه نگهبان مسیر حالا از آن استفاده می‌کنند:
- `isAbsolute()` — `/…`، `\\server\share`، `C:\…` و `C:/…`
- `normalize()` — یکدست‌سازی separator + lowercase در ویندوز (NTFS case-insensitive)
- `contains()` — مقایسه با **separator انتهایی**؛ 🔒 باگ نهفته رفع شد: base `/app` دیگر `/application` را «داخل» حساب نمی‌کند
- `existingAncestor()` + `isInside()` — برای مسیر *وجود‌نداشته*، نزدیک‌ترین جدِّ موجود مبناست؛ در ویندوز `/etc/passwd` به ریشهٔ درایو می‌رسد و بیرون از base تشخیص داده می‌شود

### ۲٫۲ `ApiDocsCommand` (موتور api)
- ساخت `$target`: `PathGuard::isAbsolute($output)` به‌جای `str_starts_with($output,'/')` — مسیر مطلق ویندوز دیگر به base الحاق نمی‌شود
- مقایسهٔ containment: `PathGuard::contains(realpath(base), realpath(dir))`

### ۲٫۳ `ResponseFactory::assertSafePath` (موتور http)
- 🔒 **ترتیب اصلاح شد**: containment **پیش از** وجودسنجی — مسیر بیرونیِ وجود‌نداشته حالا `PathTraversalException` می‌دهد (نه `FileNotFoundException`)؛ این هم رفتار امنیتی درست‌تر است (عدم افشای وجود فایل) و هم انتظار تست
- `ReportExporter` (موتور report) هم برای یکدستی به `PathGuard::isInside` منتقل شد

### ۲٫۴ `BackupManager` (موتور backup)
- `collectFiles`: تشخیص مسیر مطلق با `PathGuard::isAbsolute` — مسیرهای `include_files` خارج از base (مثل temp) در ویندوز دیگر به `base + '/' + 'C:\…'` تبدیل نمی‌شوند
- `safeAbsolute`: شرط «مطلقِ خارج از base مجاز» حالا ویندوزی هم هست
- `isExcluded`: relative فقط وقتی واقعاً زیر base باشیم با substr ساخته می‌شود (پیش‌تر برای مسیرهای بیرونی برش کورانه می‌خورد)

### ۲٫۵ `DatabaseTenantManager::assertSafeIdentifier` (موتور tenancy)
- charset مسیر sqlite روی ویندوز: `\` و `:` (حرف درایو) افزوده شد — **فقط** وقتی `PHP_OS_FAMILY === 'Windows'`؛ POSIX سخت‌گیرانه باقی است
- محافظ `..` و charset شناسهٔ SQL (`[A-Za-z0-9_]`) بدون تغییر

### ۲٫۶ `MediaTest` (fixture — نه موتور)
- موتور **درست** عمل می‌کرد (JPEG خراب را رد می‌کرد)؛ fixture دست‌ساز `JPEG_1X1` با libjpeg سخت‌گیر ناسازگار بود — روی هر دو پلتفرم (ویندوز کاربر و sandbox دیبیان) شکست می‌خورد، یعنی باگ از M6 latent بود
- `jpegBytes()` جدید: JPEG 1×1 با `imagejpeg(imagecreatetruecolor(1,1))` همان محیط تولید می‌شود (memoized)؛ fallback به base64 قدیمی وقتی gd نیست

## ۳. اصلاحات سمت تست (مستند و محدود)

| تست | رفتار جدید |
|---|---|
| `MailTest` ×۳ + `RealtimeTest` WS | skip وقتی `stream_socket_pair` نیست **یا** ویندوز است (محدودیت خودِ PHP — موتور SMTP/WebSocket روی ویندوز با socket واقعی کار می‌کند؛ فقط *هارنس تست* pair می‌خواهد) |
| `FilesystemTest` symlink ×۲ | probe عملیِ `symlink()` — در ویندوز بدون مجوز skip؛ موتور از قبل با `@symlink` graceful degrade می‌کند |
| `FilesystemTest::testVisibilityLocal` | skip در ویندوز (POSIX perms معنی ندارد) |
| `ConsoleTest::testOutputColorOverride` | انتظار `'plain' . PHP_EOL` |

الگوی skip همان قرارداد موجود مخزن است (early-return + `assertTrue(true)` — مانند MediaTest برای محیط بدون gd).

## ۴. راستی‌آزمایی

- Linux: **۹۹۲ تست / ۶۷۰۴ assertion سبز** (دو اجرای متوالی پایدار؛ پیش از این ۹۹۱ سبز + ۱ قرمزِ Media)
- `PathGuard` روی بردارهای واقعی: `/etc/passwd`→بیرون، `storage/temp/a.xlsx`→داخل، `C:\…`→مطلق، `/app`↛`/application`
- `tvn api:docs --output=storage/…` سبز و `--output=../evil.yaml` قرمز (live)
- انتظار روی ویندوز: ۱۵ شکست → **صفر** (۹ تست skip مستندِ capability-based + ۶ اصلاح کد/فیکسچر)

## ۵. نکتهٔ استقرار (ویندوز)

- برای `storage:link`/public links: PowerShell با **Developer Mode** فعال یا ادمین (وگرنه موتور graceful رد می‌کند)
- `stream_socket_pair` فقط در هارنس تست استفاده می‌شد؛ سرور WebSocket واقعی (`realtime:websocket`) با `stream_socket_server` کار می‌کند و روی ویندوز هم فعال است

---

## ۶. اصلاحات ابزاری پیدا‌شده در مسیر (خارج از ۱۵ شکست)

1. **`TVN_VERSION` کهنه در `core/bootstrap.php`** — ثابت `0.6.0` با M7 به‌روز نشده بود (صفحهٔ serve و `tvn about` نسخهٔ غلط نشان می‌دادند) → همگام با `0.7.1`.
2. **Quality Gate از M5 خاموش قرمز بود**:
   - مثبت کاذب `F-EXTRACT` روی متد `ZipReader::extract` و `$zip->extract(…)` (قانون فقط تابع زبانی `extract()` را هدف می‌گیرد) → الگو با lookbehind دقیق شد؛ کشف extract برهنه با تست تأیید گردید.
   - `F-SHELL` روی `ScheduleManager` → معافیت مستند (پیاده‌سازی `Schedule::exec()` طبق DOC-062).
   - اسکن strict_types قالب‌های `.tv.php` را شامل می‌شد (قالب view منبع PHP مستقل نیست — `resources/views` هم بیرون اسکن است) → حذف از اسکن.
   - نتیجه: gate روی ۱۰۰۷ فایل **سبز**.

## ۷. به‌روزرسانی مستندات عمومی (همزمان با 0.7.1)

- **README.md**: وضعیت M1–M7 + نگارش 0.7.1، جدول فازها (M5/M6/M7 سبز)، جدول معیارهای پذیرش M7 (اعداد اندازه‌گیری‌شده)، فهرست کامل دستورات CLI، جدول صفحه‌های دمو، راهنمای ویندوز، بنچ‌های M7 در بخش توسعه.
- **`tvn serve`**: صفحهٔ خوش‌آمد با نگارش جاری و تازه‌های M7؛ ناوبری layout با لینک دموها؛ **روت‌های دموی جدید**: `/demo/report` (+`/csv`)، `/demo/workflow` (چرخهٔ کامل BPM + Mermaid)، `/demo/payment` (کیف پول داخلی)، `/payment/result` (صفحهٔ نتیجهٔ callback) — همگی با ویوهای اختصاصی و smoke زنده تأیید شدند (۲۰۰ + نشانگرهای محتوا).
- **CHANGELOG.md**: ورودی‌های `0.7.0` (M7) و `0.7.1` (این اصلاحات).
- **گزارش‌ها**: `docs/reports/M7-implementation-report.md` و همین سند.
