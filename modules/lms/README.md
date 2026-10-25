# ماژول LMS (tavaan.lms) 🎓

سامانهٔ مدیریت آموزش — پیاده‌سازی **DOC-095** (تیکت TVN-0095، فاز M9).

> معماری: **hexagonal** (پورت/آداپتر) — همان الگوی ماژول‌های `shop`/`forum`.
> مرز ماژول (ARCH-003): فقط Core Contracts/Facades/Support + Contracts ماژول‌های
> هم‌خانواده (`core-user`, `content`, `comment`, `media`) + SDK مجاز (`Console\Command`).

## دامنه (DOC-095 §1)

```
Course Catalog │ Enrollment │ Content Delivery │ Assessment │ Certification │ Progress
```

| Aggregate | Root | پیاده‌سازی |
|---|---|---|
| Course | `Course` | + `Section`, `Lesson`, پیش‌نیاز (`lms_course_prerequisites`) |
| Lesson | `Lesson` | محتوا (video/text/quiz/attachment) + CommentableInterface (Q&A) |
| Enrollment | `Enrollment` | + `LessonProgress` (uk enrollment+lesson) |
| Quiz | `Quiz` | + `Question`, `QuizAttempt` |
| Certificate | `Certificate` | serial یکتا با رقم کنترل + verify عمومی |

## ساختار

```
modules/lms/
├── module.json                  ← manifest (events/permissions/navigation/tables)
├── config/lms.php               ← payment/quiz/certificates/notifications/search/qa
├── config/rbac.php              ← policies اعلامی (Course → CoursePolicy)
├── migrations/…create_lms_tables.php   ← ۱۲ جدول lms_*
├── routes/{web,admin}.php       ← ۲۰ روت وب + ۳۰ روت پنل
├── seeds/demo.php               ← دادهٔ دمو (idempotent)
├── src/
│   ├── Contracts/PaymentGatewayPort.php        ← پورت پرداخت (sandbox/engine)
│   ├── Domain/Entities/…       ← Course/Section/Lesson/Enrollment/LessonProgress/Quiz/Question/QuizAttempt/Certificate/LmsCategory
│   ├── Domain/Enums/…          ← ۸ enum (چرخه‌های وضعیت با ماتریس انتقال)
│   ├── Domain/Events/…         ← ۸ رویداد (DTO تایپ‌دار + eventName/toPayload)
│   ├── Domain/Repositories/…   ← ۴ پورت مخزن
│   ├── Domain/Services/…       ← QuizGrader/ProgressCalculator/PrerequisiteChecker/AnswerNormalizer/CertificateSerialGenerator (خالص)
│   ├── Application/UseCases/…  ← Catalog/CourseAdmin/QuizAdmin/Enrollment/Progress/Quiz/Certificate/Report
│   ├── Infrastructure/
│   │   ├── Persistence/…       ← ۴ آداپتر DB + DatabaseLmsCommentStore (lms_comments)
│   │   ├── Gateways/…          ← Sandbox + Engine (Payment facade)
│   │   ├── Notifications/LmsNotifier.php   ← binding `notification.message`
│   │   ├── Search/CourseSearchIndexer.php  ← ایندکس `lms_courses`
│   │   └── Workflow/CertificateWorkflow.php← `lms-issue-certificate` (Workflow facade)
│   ├── Http/Controllers/…      ← ۵ عمومی + ۶ ادمین
│   ├── Policies/CoursePolicy.php
│   ├── Console/Commands/RecomputeProgressCommand.php  ← `tvn lms:recompute-progress`
│   └── Providers/LmsServiceProvider.php
├── views/…                      ← RTL (front + admin + سند گواهی print-ready)
└── tests/{Unit,Feature}/…
```

## جریان‌های کلیدی (DOC-095 §3)

1. **ثبت‌نام** — رایگان: مستقیم `active`؛ پولی: `pending_payment` →
   `PaymentGatewayPort::purchase` → sandbox/درگاه → callback →
   **verify با مبلغ محلی** (نه اعتماد به callback) → `active` + رویداد
   `lms.student.enrolled` → welcome notification.
2. **پیشرفت** — `POST /lms/lessons/{id}/complete` → `lms_lesson_progress` →
   recalc درصد → همهٔ درس‌ها؟ → `completed` + `lms.course.completed` →
   certificate check.
3. **آزمون** — max_attempts/time_limit → نمره‌دهی خودکار
   (multiple_choice/true_false/short_answer*) → دستی (descriptive) در صف
   `/admin/lms/grading` → `lms.quiz.passed` / `lms.attempt.graded`.
4. **گواهی (Workflow)** — `lms-issue-certificate`:
   `requested →generate→ generating →issue→ issued` (+`fail`) — serial یکتا
   (Crockford base32 + رقم کنترل)، سند HTML آمادهٔ چاپ روی storage،
   `verify_url` عمومی و رویداد `lms.certificate.issued`.

## نقش‌ها (DOC-095 §4)

| نقش | مجوزها |
|---|---|
| `student` | ثبت‌نام/یادگیری/آزمون (روت‌های auth-guarded — بدون مجوز پنل) |
| `instructor` | `lms.course.create` + ویرایش/انتشار **دورهٔ خود** (CoursePolicy)، `lms.quiz.manage`، `lms.attempt.grade` (دورهٔ خود)، مشاهدهٔ ثبت‌نام/گزارش خود |
| `assistant` | `lms.attempt.grade` + `lms.attempt.grade-any` (نمره‌دهی همه)، `lms.enrollment.view` |
| `lms-admin` | همهٔ `lms.*` |

۱۴ مجوز `lms.*` در manifest — sync با `tvn rbac:sync`.

## نکات پیاده‌سازی

- **پول**: int minor-unit (ADR-0013) — انطباق با DECIMAL مستند در گزارش ثبت شده.
- **XSS**: بدنهٔ درس/توضیحات = Markdown escape-first (ماژول content) +
  `Security::sanitize(rich_text)`؛ Q&A = sanitize(plain) + escape ویو؛
  `video_url` فقط http(s)/نسبی (allowlist).
- **جست‌وجو**: ایندکس native فارسی‌آگاه (`Search` facade) + fallback LIKE با
  واریانت رقم فارسی/لاتین.
- **Idempotency**: ثبت‌نام تکراری، callback تکراری و صدور گواهی تکراری
  اثر دوم ندارند.
- **تست**: `modules/lms/tests/` — Unit (damneٔ خالص) + Feature (E2E-01..07 +
  نقش‌ها/XSS/verify).

@see docs/05-applications/lms.md • kits/lms
