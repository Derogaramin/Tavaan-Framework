# رجیستری بدهی فنی و خطاهای رفع‌شده (Tech Debt & Known Issues Registry)

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-122 |
| نقش | تنها فهرست متمرکز بدهی فنی + خطاهای یافت‌شده/رفع‌شده — جمع‌بندی از همهٔ گزارش‌ها |
| قاعده | هر مورد: شناسه، وضعیت (fixed/open/backlog)، شدت، منبع، اقدام |
| به‌روزرسانی | با هر فاز/گزارش جدید؛ موارد open تا رفع در همین جدول می‌مانند |

---

## ۱. خطاهای رفع‌شده (Fixed — برای جلوگیری از رگرسیون مستند شده‌اند)

| ID | شرح | کجا یافت شد | رفع | نسخه |
|---|---|---|---|---|
| BUG-M9E-01 | `SessionGuard` نشست HTTP واقعی را نمی‌دید (StartSession روی clone می‌بست؛ RequestStack اصلی را نگه می‌داشت) → همهٔ صفحات auth-guard به /login ریدایرکت | ممیزی M9 گام ۷ (ERP) — smoke واقعی | fallback به instance نشست scope-شده در `AuthManager::sessionResolver` + تست رگرسیون kernel | 0.15.0 |
| BUG-M9E-02 | `Request::create` هدر `HTTP_COOKIE` را پارس نمی‌کرد → کوکی نشست در زیردرخواست گم | ممیزی M9 گام ۷ | هم‌راستا با `fromGlobals` | 0.15.0 |
| BUG-M9L-01 | `DatabaseBroker::prune` از `new DateTimeImmutable` مستقیم (نقض قانون Clock یگانه + flake ۲٪) | M9 گام ۵ (LMS) | `Clock::utcNow()->modify()` | 0.13.0 |
| BUG-M9L-02 | `TVN_HTTP_USER_AGENT=` خالی → هدر User-Agent خالی و شکست تست | M9 گام ۵ | fallback `?:` در config + PendingRequest | 0.13.0 |
| BUG-M9P-01..06 | شش باگ ماژول ERP (اقلام یتیم، vouchers بدون خطوط، ستون جامانده، FiscalPeriod بدون id، allowlist audit، تداخل نام متد) | M9 گام ۷ | گزارش §4 همان گزارش | 0.15.0 |
| BUG-BPMS-01 | **join زودهنگام** در advance حلقه‌ای — توکن پارک‌شده بازپردازش و `arriveAtJoin` دوباره شمارش می‌شد | M9 گام ۸ (BPMS) | advance با **work-queue** (هر توکن یک‌بار) | 0.16.0 |
| BUG-BPMS-02 | task با `assignee=initiator` و `assignee_id=NULL` در inbox دیده نمی‌شد | M9 گام ۸ | resolve شناسهٔ آغازگر هنگام ساخت task | 0.16.0 |
| BUG-BPMS-03 | تغییرات متغیرها از اکشن تایمر persist نمی‌شد (`ctx->set` بدون update) | M9 گام ۸ | `instances->update` پس از اکشن سفارشی | 0.16.0 |
| BUG-BPMS-04 | فرم مشترک دو شاخهٔ موازی: normalize چک‌باکس ارسال‌نشده را false می‌نوشت و شاخهٔ دوم مقدار شاخهٔ اول را صفر می‌کرد | M9 گام ۸ | فرم per-شاخه در تعاریف + مستندسازی رفتار normalize | 0.16.0 |
| BUG-TOOL-01 | `migrate:rollback` برای migrationهای `--path` سفارشی فایل را نمی‌یافت (`migration.file_missing`) | اسپرینت پوشش (پس از نقد) | `Migrator::rollback(steps, paths)` + `--path` در فرمان + تست رگرسیون | 0.17.0 |
| BUG-DOC-01 | داشبورد بک‌لاگ «جمع ۶۳» — TVN-0043 شبح (فقط در depends_on ارجاع، هرگز تعریف‌نشده)؛ M5=۹ در برابر ۸ واقعی | تولید مستند تیکت | اصلاح داشبورد به ۶۲ + یادداشت مستند | 0.17.0 |
| BUG-DOC-02 | README ریشه: «۴۸ موتور» در برابر ۵۲ واقع؛ `docs/manifest.json` version=0.8.0 مانده | نقد 1.0 §5 | اصلاح به ۵۲ (۵۳ با pagination در database) + 0.16.0 | 0.16.0/0.17.0 |
| BUG-QA-01 | تست‌های QaTools زیر فشار حافظهٔ sandbox با `proc_open fork failed` **خطای کاذب** می‌دادند (بدون تفکیک محیطی) | نقد 1.0 §3.3-4 | پریزیمیتیو `TestSkipped`/`$this->skip(reason)` در موتور test + skip مستند در QaToolsTest | 0.17.0 |
| PERF-OBS-01 | xdebug (حتی mode=off) بوت را ~۲× کند می‌کرد — اعداد bench اولیه آلوده بود | نقد 1.0 §1 | اندازه‌گیری‌ها با `XDEBUG_MODE=off` تکرار و مستند شد | 0.16.0 |

## ۲. بدهی فنی باز (Open — هدف 1.x مگر ذکر شود)

| ID | شرح | شدت | منبع |
|---|---|---|---|
| TECH-DEBT-ARCH-01 | برخی contractهای هسته به VOهای موتور تایپ‌اند (SearchResult/LogRecord/Payment VO/Cookie) — coupling عمل‌گرایانه تا انتقال VOها به هسته | متوسط | ممیزی M0–M8، config/architecture.php |
| TECH-COV-01 | **پوشش کد ۷۱٫۷٪ (motors) در برابر هدف ۹۰٪** — تمرکز گام بعد: queue console (~۱۶۶ خط)، CurlTransport، tenancy/media/filesystem console، شاخه‌های خطای core | **بالا** | نقد §3، readiness §3.1 |
| TECH-PERF-01 | نبود runtime مقیم opt-in (FrankenPHP/Swoole adapter) ⇒ سقف ~۱۵۰ RPS/core؛ هدف RPS≥۳۰۰۰ به 1.x موکول | بالا | ADR-0017 |
| TECH-SEC-01 | اسکنر امنیتی token-based است (نه taint-analysis) — کلاس‌هایی از تزریق مرتبهٔ دوم را اصولاً نمی‌بیند | متوسط | نقد §3.3-2 |
| TECH-SEC-02 | ۸ یافتهٔ med (md5/sha1 غیررمزنگاری) — بازبینی‌شده و بی‌خطر، ولی allowlist خودکار در scanner ندارند | پایین | readiness §6 |
| TECH-QUEUE-01 | queue worker تک‌فرایند بدون supervisor داخلی (QueueListenCommand → proc_open) | متوسط | نقد §3.3-3 |
| TECH-COV-02 | CoverageReporter اجرای چند-بخش را merge نمی‌کند (core از دو اجرای engines/app باید دستی جمع شود) | پایین | readiness §3 |
| TECH-BPMS-01 | BPMS: Sub-process تو‌در‌تو | متوسط | گزارش BPMS §6 |
| TECH-BPMS-02 | BPMS: Designer گرافیکی drag-drop (فعلاً JSON+SVG) | متوسط | گزارش BPMS §6 |
| TECH-BPMS-03 | BPMS: چند توکن روی یک userTask (multi-instance task) | پایین | گزارش BPMS §6 |
| TECH-BPMS-04 | BPMS: گزارش xlsx پایش (فعلاً CSV) | پایین | گزارش BPMS §6 |
| TECH-BPMS-05 | BPMS: service task سنگین روی queue (فعلاً همگام در تراکنش) | متوسط | گزارش BPMS §6 |
| TECH-ERP-01 | ERP: Budgeting/دارایی ثابت/استهلاک • FIFO/LIFO + lot/serial • مغایرت‌گیری بانک • چند-ارزی • cost-center UI • اعلان‌های po_needs_approval/low_stock | متوسط | گزارش ERP §6 |
| TECH-LMS-01 | LMS: PDF باینری با فونت فارسی embed (فعلاً HTML آمادهٔ چاپ) | پایین | گزارش LMS |
| TECH-DOC-01 | نبود دیگرام تصویری (غیر ASCII) در مستندات — برای کتاب/سایت لازم است | پایین | نقد §6 |
| TECH-DOC-02 | بنچمارک side-by-side با Laravel/Symfony هرگز اجرا نشده (اعداد DOC-130 تقریبی‌اند) | متوسط | نقد §4.1 |
| TECH-TEST-01 | دو فایل هم‌نام `CrmE2ETest` در Unit و Feature ماژول CRM (ابهام در گزارش‌ها) | پایین | نقد §5 |

## ۳. محدودیت‌های محیطی (باگ نیستند — برای تکرارپذیری مستند شده‌اند)

| مورد | شرح | راه‌حل |
|---|---|---|
| sandbox ~۱GB RAM | اجرای کامل سوئیت + xdebug → OOM؛ `proc_open` زیر فشار → fail | اجرای scoped؛ `skip` مستند؛ coverage خالی |
| `php -S` تک‌رشته | اعداد RPS در sandbox کفِ بدبینانه‌اند | بنچ روی fpm+opcache (DOC-133) |
| somaxconn=128 | burst ۳۰۰ همزمان → ~۲٫۷٪ reset در لایهٔ TCP (نه اپ) | tuning کرنل/nginx در تولید |
