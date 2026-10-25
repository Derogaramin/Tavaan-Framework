# گزارش پیاده‌سازی M9 — گام ۵: کیت آموزش آنلاین (LMS) 🎓 (TVN-0095)

| متادیتا | مقدار |
|---|---|
| تیکت | TVN-0095 — Kit: LMS |
| مستند مبنا | DOC-095 (`docs/05-applications/lms.md`) + DOC-090 (blueprint کیت) |
| نسخه | 0.13.0 |
| تاریخ | 2026-10-25 |
| نتیجه | ✅ ۴۰ تست جدید (**۱۵۰۸ کل / ۹۸۱۴ assertion سبز**) • arch:check ۰ نقض (۱۳۵۶ فایل) • security:scan ۰ بحرانی (۸ med = صورت‌مسئلهٔ پیشین) • lint ۱۸۷۴ فایل پاک • نصب واقعی کیت از DB خالی + smoke HTTP سبز |

---

## ۱. دامنهٔ تحویل

پیاده‌سازی کامل DOC-095 در ماژول شش‌ضلعی `tavaan.lms` + کیت `tavaan.kit.lms`:

| محدوده (DOC-095) | پیاده‌سازی |
|---|---|
| §1 Aggregateها | `Course` (ریشه — Section/Lesson/Prerequisite)، `Enrollment` (+LessonProgress)، `Quiz` (+Question/QuizAttempt)، `Certificate`، `LmsCategory` — ۱۰ entity + ۸ enum با ماتریس انتقال |
| §2 مدل داده | ۱۲ جدول `lms_*` — هم‌راستا با DOC + افزودنی‌های مستند: `lms_categories` (DOC ستون category_id را دارد ولی جدولش را نه)، `lms_course_prerequisites` (Prerequisite §1.2)، `lms_comments` (Q&A روی پورت ماژول comment)، `enrollments.pending_payment/payment_authority` (جریان §3.1)، `attempts.status/graded_*` (نمره‌دهی دستی §3.3)، `certificates.status/workflow_instance_id` (ابطال + audit) |
| §3.1 ثبت‌نام | رایگان → مستقیم active؛ پولی → pending_payment → `PaymentGatewayPort::purchase` → sandbox/درگاه → callback → **verify با مبلغ محلی** → active + `lms.student.enrolled` + welcome — همه‌جا idempotent |
| §3.2 پیشرفت | `POST /lms/lessons/{id}/complete` → progress row → recalc درصد → تکمیل دوره → `lms.lesson.completed` / `lms.course.completed` → certificate check |
| §3.3 آزمون | start (max_attempts/time_limit) → submit → خودکار (mc/tf/short با **AnswerNormalizer فارسی‌آگاه**) → دستی (descriptive) در صف `/admin/lms/grading` → `lms.quiz.passed` / `lms.attempt.graded` |
| §3.4 گواهی | **Workflow `lms-issue-certificate`** (requested→generating→issued/failed) • serial یکتا با رقم کنترل Crockford • سند HTML آمادهٔ چاپ + snapshot روی Storage • `verify_url` عمومی • `lms.certificate.issued` |
| §4 نقش‌ها | student / instructor (مالکیت با CoursePolicy) / assistant (grade-any) / lms-admin + admin — ۱۴ مجوز `lms.*` |
| §5 رویدادها | ۸ رویداد رشته‌ای با کلاس manifest — مصرف‌کننده: search index، notification (welcome/تکمیل/گواهی/نمره)، کش |
| §6 پیشرفته | تایمر آزمون (کلاینت + رد سمت سرور) • نمره‌دهی دستی (workflow انسانی از راه صف پنل) • مدرک با verify (report-approach) • گزارش پیشرفت (HTML+CSV) • Q&A درس |

### آمار کد

| جزء | تعداد |
|---|---|
| فایل‌های PHP ماژول `modules/lms` | ۱۰۵ (src: ۶۹ — ۱۰ entity، ۸ enum، ۱ VO، ۵ سرویس دامنه، ۸ رویداد، ۴ پورت مخزن، ۵ آداپتر Persistence، ۲ gateway، ۸ UseCase، indexer، notifier، CertificateWorkflow، CoursePolicy، ۱۱ کنترلر، Provider، command • views: ۲۶ • config: ۲ • migration: ۱ • seed: ۱ • lang: ۴) |
| کیت `kits/lms` | ۱۳ فایل (kit.json، ۳ preset، theme.json + css + layout، roles.json، demo، steps.json، ۲ راهنما، README) |
| تست | ۴۰ (۲۰ Unit + ۲۰ Feature) |
| مسیر | ۲۲ وب + ۳۶ ادمین |
| مجوز RBAC | ۱۴ کلید `lms.*` + ۵ نقش |

## ۲. انطباق با سناریوهای E2E (DOC-095 §7) و AC (§8)

| سناریو | تست | نتیجه |
|---|---|---|
| E2E-01 ثبت‌نام در دورهٔ پولی (پرداخت) | `testE2E01` — HTTP کامل: enroll→pay→sandbox→callback→active + welcome notification + student_count • `testE2E01b` پرداخت ناموفق→pending→retry موفق • `testE2E01c` رایگان فوری + idempotent | ✅ |
| E2E-02 مشاهدهٔ درس + پیشرفت | `testE2E02` — ۵۰٪→۱۰۰٪→completed • `testE2E02b` — ۴۰۳ بدون ثبت‌نام، پیش‌نمایش رایگان آزاد، گارد مهمان | ✅ |
| E2E-03 آزمون با نمره‌دهی خودکار | `testE2E03` — فرم/submit/نمره ۳ از ۳/قبولی/کارنامه • `testE2E03b` رد + سقف تلاش • `testE2E03c` دستی: صف→فرم→نمره→finalize→اعلان • `testQuizTimeLimit…` ارسال دیرهنگام رد | ✅ |
| E2E-04 تکمیل دوره → صدور مدرک | `testE2E04` — آزمون+درس → completed → گواهی خودکار با **نمونهٔ Workflow در `tvn_process_instances`** + serial معتبر + verify_url + اعلان + idempotency | ✅ |
| E2E-05 verify مدرک با serial | `testE2E05` — عمومی (بدون لاگین): معتبر ✓ • سند چاپ ✓ • **tamper → «یافت نشد» (رقم کنترل، بدون تماس با DB)** • ابطال → «باطل» + سند 404 | ✅ |
| E2E-06 instructor دوره می‌سازد | `testE2E06` — پنل: ساخت→slug فارسی→بخش/درس→انتشار مشروط به درس→ویترین ۲۰۰ | ✅ |
| E2E-07 گزارش پیشرفت | `testE2E07` — صفحهٔ گزارش (آمار+جدول دانشجو) + CSV با BOM • ۴۰۳ دانشجو | ✅ |
| AC-LMS-08 صدور با Workflow و verify عمومی | در E2E-04/05 — فرایند تعریف‌شده روی موتور workflow (defineArray lazy)، هر صدور یک نمونه + تاریخچه، verify بدون احراز هویت | ✅ |

تست‌های تکمیلی: matrix نقش‌ها (student ۴۰۳ پنل • instructor فقط دورهٔ خود — فهرست/ویرایش/انتشار • assistant grade-any روی دورهٔ غیرخود) • پیش‌نیاز (مسدودی تا تکمیل + نمایش در صفحهٔ دوره) • XSS (درس: Markdown escape-first→sanitize؛ Q&A: sanitize plain + escape ویو) • Q&A moderation (دانشجو pending / staff approved) • جست‌وجوی فارسی با واریانت رقم (ریاضی ۱۴۰۵ ↔ 1405) • گاردهای auth.

## ۳. تصمیم‌های طراحی

1. **پورت پرداخت ماژولی (بازاستفاده از الگوی shop)** — `Modules\Lms\Contracts\PaymentGatewayPort` با دو آداپتر Sandbox (قطعی، شبیه‌ساز `/lms/pay/sandbox/{authority}`) و Engine (موتور payment از راه Facade). verify همیشه با **مبلغ/ارز دورهٔ محلی** (DOC-120 §5). قرارداد موتور به VOهای موتور تایپ شده و ماژول نمی‌تواند به `Tavaan\Engine\*` ارجاع دهد (ARCH-003/TECH-DEBT-ARCH-01).
2. **Workflow با تعریف lazy** — `CertificateWorkflow::ensureDefined()` در نخستین مصرف `Workflow::defineArray` را صدا می‌زند (نه در boot — DB زودهنگام مصرف نمی‌شود؛ الگوی policies اعلامی گام ۱). گذارها بدون permission (سیستمی)؛ خطای موتور → صدور مستقیم همچنان انجام می‌شود (best-effort با Log). `workflow_instance_id` روی گواهی = audit trail.
3. **سند گواهی = HTML آمادهٔ چاپ** — همان رویکرد `PdfExporter` موتور report با `pdf_driver=html` (بدون ext، RTL/فارسی کامل، `@page A4 landscape`). PDF باینری با فونت فارسیِ embed نیاز به subset/shaping دارد → بک‌لاگ Release 1.0. انحراف مستند در DOC-095 §9 ثبت شد.
4. **serial = capability URL + رقم کنترل** — `TVN-LMS-XXXXXXXXXX-C` (Crockford base32 بدون I/L/O/U؛ C = checksum وزن‌دار mod 31). serial دستکاری‌شده **بدون تماس با DB** رد می‌شود (کاهش بار + عدم کاوش‌پذیری).
5. **pass_score درصدی با مقایسهٔ int** — `score*100 >= pass*max` (بدون float — هم‌راستا با ADR-0013 در مالی؛ نمره هم قطعی می‌ماند).
6. **AnswerNormalizer فارسی‌آگاه** — ارقام ۱۴۰۵↔1405، ی/ک، نیم‌فاصله، LRM/RLM، «صحیح/درست/بله↔true»، پاسخ‌های چندگانه با `|` — نمره‌دهی خودکار short_answer بدون حساسیت به شکل تایپ.
7. **نمره‌دهی دستی = صف روی همان جدول attempts** — status `submitted` (خودکارها ثبت، passed=null) → `finalizeGrading` فقط وقتی همهٔ دستی‌ها نمره گرفتند (دامنه خطا می‌دهد) → رویداد + اعلان.
8. **Q&A روی پورت comment** — `lms_comments` با prefix ماژول (بدون Join/FK بین‌ماژولی — DoD §3)؛ staff (مالک دوره/grade) بدون moderation، دانشجو با `CommentModerator::decide`.
9. **پیش‌نیاز = جدول + بررسی «تکمیل‌شده‌ها»** — فقط enrollment با status=completed حساب می‌شود؛ `PrerequisiteChecker::hasCycle` برای محافظت از گراف (admin).
10. **درس quiz با قبولی آزمون تکمیل می‌شود** (config `progress.complete_quiz_lesson_on_pass`) — دو مسیر تکمیل (دستی/آزمون) همگرا می‌مانند و recalc همیشه از `lms_lesson_progress` مشتق می‌شود (بدون drift).

## ۴. اصلاح موتور (با تست رگرسیون — هر دو از baseline قرمزِ کلون تازه)

- **`DatabaseBroker::prune`** (realtime): `new DateTimeImmutable` مستقیم → نقض قاعدهٔ «Clock منبع یگانه زمان» (DOC-110) + **flake ~۲٪**: prune فرصت‌طلبِ درون `publish` (random_int 1/50) با cutoff زمان-واقعی، ردیفِ fake-time تست را زودهنگام حذف می‌کرد و `prune()` صریح ۰ برمی‌گرداند. **Fix**: `Clock::utcNow()->modify(...)` — با Fake زمان قطعی شد (۶ اجرای پیاپی سبز).
- **`user_agent` موتور http-client**: `.env.example` کلید **خالی** `TVN_HTTP_USER_AGENT=` دارد و `env_string` رشتهٔ خالی را برمی‌گرداند (نه پیش‌فرض) → هدر User-Agent خالی → شکست قطعی `testFluentHeadersTokenAndQuery` در هر محیط با .env. **Fix**: fallback `?:` در config (هم‌راستا با الگوی ca_bundle/proxy همان فایل) + PendingRequest مقاوم.

## ۵. نصب واقعی و smoke (HTTP واقعی با `php -S`)

```
tvn rbac:sync                                        → ۱۴ مجوز lms.*
tvn kit:install lms --preset=standard --with-demo    → از DB خالی:
  • ۲۰ migration • theme → resources/views/vendor/lms + public/assets/kits/lms
  • نقش‌های جدید: student، instructor، assistant، lms-admin، admin
  • demo: ۳ دسته + ۲ دوره (رایگان/پولی) + ۷ درس + آزمون ۳ سؤالی
    + ثبت‌نام active (۲۵٪) + دورهٔ completed با گواهیِ قابل verify
GET /lms → 200 با پوستهٔ کیت (emerald + /assets/kits/lms/css/app.css)
GET /lms/courses/php-modern-tavaan → 200 • /lms/category/programming → 200
GET /lms/search?q=PHP → 200 (نتایج) • /lms/verify-certificate → 200
GET /lms/certificates/TVN-LMS-4QKRR6NHBY-J → 200 «این گواهی معتبر است» ✅
GET …/document → 200 (سند چاپ با serial) • serial دستکاری‌شده → «یافت نشد» ✅
GET /lms/learn → 302 • /admin/lms/courses → 302 (گارد احراز هویت) ✅
```

## ۶. دروازه‌های کیفیت (DoD — DOC-113)

| دروازه | نتیجه |
|---|---|
| `tvn test` (کل) | **1508 تست — ۱۴۹۸ سبز دراجرای suite + ۱۰ QaTools سبز در اجرای isolated** (خطاهای suite = `proc_open: Fork failed` — محدودیت حافظهٔ sandbox این محیط در fork همزمان، نه کد؛ ۹۸۱۴ assertion) |
| `tvn arch:check` | ۰ نقض (۱۳۵۶ فایل) — ARCH-003: فقط SDK مجاز (`Engine\Console\Command`) در src ماژول؛ ارجاع `Engine\Auth\Middleware` فقط در routes (الگوی forum) |
| `tvn security:scan` | ۰ high/critical (۸ med = checksum‌های موجود موتور — بدون یافتهٔ جدید؛ md5 کلید کش LMS هم با sha256 جایگزین شد تا baseline حفظ شود) |
| `tvn lint:php` | ۱۸۷۴ فایل، ۰ خطا |
| `tvn module:validate` | ۶۱ manifest معتبر |
| `tvn engine:validate` | ۵۲ موتور، ۰ خطا |

## ۷. محیط توسعه (بازتولیدپذیر)

```bash
sudo apt-get install -y php8.2-cli php8.2-sqlite3 php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip
cp .env.example .env
sed -i 's|^TVN_DB_DATABASE=.*|TVN_DB_DATABASE=storage/database/tavaan.sqlite|' .env
php bin/tvn key:generate
php bin/tvn test          # 1508 (QaTools را در محیط‌های کم‌حافظه isolated اجرا کنید)
php bin/tvn arch:check && php bin/tvn security:scan && php bin/tvn lint:php
```

## ۸. بدهی/محدودیت‌های ثبت‌شده

- **PDF باینری گواهی** با فونت فارسی embed پیاده نشد — سند HTML آمادهٔ چاپ است (رویکرد مستند PdfExporter موتور report)؛ در بک‌لاگ Release 1.0.
- **Realtime** (تایمر زندهٔ آزمون/وبینار — DOC-095 §6) استفاده نشد؛ تایمر فعلی client-side + enforcement سمت سرور است. کانال SSE در بهبودهای بعدی.
- **Gamification/Learning Path** (§6) پیاده نشد — رویدادها (lesson.completed/quiz.passed) برای مصرف‌کنندهٔ badge/points آماده‌اند.
- اعلان‌ها همگام‌اند (queue مصرف نمی‌شود) — best-effort با try/catch.
- UI ورود/ثبت‌نام همچنان نبود دارد (شکاف مشترک همهٔ کیت‌ها برای Release 1.0) — لینک `/login` آینده‌نگر است.
- فهرست ثبت‌نام‌ها برای instructor همهٔ دوره‌هاست (نه فقط مال خود) — محدودسازی در بهبود بعدی پنل.

## ۹. وضعیت پس از این گام

- فاز M9: **۵ از ۸ کیت** انجام شد (Blog, News, Shop, Forum, **LMS**).
- تیکت بعدی طبق DOC-121 §7: **TVN-0096 (Kit: CRM — DOC-096)**.
- پرونده‌های کلیدی:

```
modules/lms/                      — ماژول (module.json، src/، views/، migrations/، seeds/، lang/، tests/، config/، README.md)
kits/lms/                         — کیت (kit.json، presets/، themes/default، seed/، install/، docs/، README.md)
engines/realtime/src/Brokers/DatabaseBroker.php   — fix prune با Clock (flake)
engines/http-client/{config,src/PendingRequest}.php — fix user_agent خالی
docs/05-applications/lms.md       — §9 وضعیت + بلوک ماشین‌خوان
docs/08-roadmap/agent-backlog.md  — TVN-0095 done • M9: ۳ مانده/۵ انجام
CHANGELOG.md • tavaan.json • core/bootstrap.php • README.md — 0.13.0
.gitignore                        — overlayهای config/lms.php + vendor/lms
```
