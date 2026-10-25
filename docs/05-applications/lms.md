# DOC-095 — کاربرد LMS (سامانهٔ مدیریت آموزش) 🎓

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-095 |
| سطح | حرفه‌ای |
| معماری | `modular` → `hexagonal` |
| ماژول‌ها | core-user, lms, payment, content, comment, notification |
| موتورها | media, workflow, report, queue, search |

---

## ۱. دامنه
سامانهٔ آموزش آنلاین: دوره، درس، محتوا (ویدیو/متن/آزمون)، ثبت‌نام، پیشرفت،
مدرک، آزمون و نمره‌دهی.

### ۱٫۱ Bounded Contextها
```
Course Catalog │ Enrollment │ Content Delivery │ Assessment │ Certification │ Progress
```

### ۱٫۲ Aggregateها
| Aggregate | Root | Entities/VO |
|---|---|---|
| Course | `Course` | Section, Lesson, Prerequisite |
| Lesson | `Lesson` | Content (Video/Text/Quiz/Attachment) |
| Enrollment | `Enrollment` | Student, Course, Progress, Status |
| Quiz | `Quiz` | Question, Option, Attempt, Answer |
| Certificate | `Certificate` | Template, IssuedTo |

## ۲. مدل داده
```sql
CREATE TABLE lms_courses (
  id CHAR(26) PRIMARY KEY,
  slug VARCHAR(200) UNIQUE, title VARCHAR(255), summary TEXT, description LONGTEXT,
  instructor_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED,
  level VARCHAR(20),                      -- beginner|intermediate|advanced
  language CHAR(5) DEFAULT 'fa',
  price DECIMAL(18,4) DEFAULT 0, currency CHAR(3) DEFAULT 'IRR',
  is_free TINYINT(1) DEFAULT 0,
  status VARCHAR(20) DEFAULT 'draft',     -- draft|published|archived
  thumbnail CHAR(26), total_duration INT DEFAULT 0,   -- دقیقه
  rating_avg DECIMAL(3,2) DEFAULT 0, student_count INT DEFAULT 0,
  published_at DATETIME(3), created_at DATETIME(3), updated_at DATETIME(3),
  KEY idx_status (status, published_at)
);

CREATE TABLE lms_sections (
  id CHAR(26) PRIMARY KEY, course_id CHAR(26) NOT NULL,
  title VARCHAR(255), position INT, KEY idx_course (course_id, position)
);

CREATE TABLE lms_lessons (
  id CHAR(26) PRIMARY KEY, section_id CHAR(26) NOT NULL, course_id CHAR(26) NOT NULL,
  title VARCHAR(255), slug VARCHAR(255), type VARCHAR(20),  -- video|text|quiz|attachment
  content LONGTEXT, video_url VARCHAR(500), duration INT DEFAULT 0,
  is_free_preview TINYINT(1) DEFAULT 0, position INT,
  KEY idx_course (course_id, position)
);

CREATE TABLE lms_enrollments (
  id CHAR(26) PRIMARY KEY, student_id BIGINT UNSIGNED NOT NULL, course_id CHAR(26) NOT NULL,
  status VARCHAR(20) DEFAULT 'active',    -- active|completed|dropped
  progress_percent DECIMAL(5,2) DEFAULT 0,
  enrolled_at DATETIME(3), completed_at DATETIME(3),
  payment_transaction_id CHAR(26) NULL,
  UNIQUE KEY uk_enroll (student_id, course_id)
);

CREATE TABLE lms_lesson_progress (
  id BIGINT UNSIGNED PK, enrollment_id CHAR(26), lesson_id CHAR(26),
  status VARCHAR(20) DEFAULT 'not_started',  -- not_started|in_progress|completed
  completed_at DATETIME(3), UNIQUE KEY uk_progress (enrollment_id, lesson_id)
);

CREATE TABLE lms_quizzes (
  id CHAR(26) PRIMARY KEY, lesson_id CHAR(26), course_id CHAR(26),
  title VARCHAR(255), time_limit INT NULL, pass_score INT DEFAULT 50,
  max_attempts INT DEFAULT 1, KEY idx_lesson (lesson_id)
);

CREATE TABLE lms_questions (
  id CHAR(26) PRIMARY KEY, quiz_id CHAR(26) NOT NULL,
  type VARCHAR(20),                       -- multiple_choice|true_false|short_answer|descriptive
  body TEXT, options JSON, correct_answer TEXT, score INT DEFAULT 1, position INT
);

CREATE TABLE lms_quiz_attempts (
  id CHAR(26) PRIMARY KEY, quiz_id CHAR(26), student_id BIGINT UNSIGNED,
  answers JSON, score INT, passed TINYINT(1),
  started_at DATETIME(3), submitted_at DATETIME(3)
);

CREATE TABLE lms_certificates (
  id CHAR(26) PRIMARY KEY, template_id BIGINT UNSIGNED, enrollment_id CHAR(26),
  student_id BIGINT UNSIGNED, course_id CHAR(26),
  serial VARCHAR(100) UNIQUE, issued_at DATETIME(3),
  verify_url VARCHAR(255), file_path VARCHAR(500)
);
```

## ۳. جریان‌های اصلی

### ۳٫۱ ثبت‌نام در دوره
```
دانشجو → POST /lms/courses/{id}/enroll
  → اگر رایگان: مستقیم ثبت‌نام
  → اگر پولی: Payment::purchase → callback → ثبت‌نام
  → Enrollment created → Event: StudentWasEnrolled
  → welcome email
```

### ۳٫۲ پیشرفت درس
```
دانشجو درس را تمام می‌کند → POST /lms/lessons/{id}/complete
  → LessonProgress.status = completed
  → recalculate Enrollment.progress_percent
  → اگر همهٔ درس‌ها تمام شد → Enrollment.status = completed
  → Event: LessonWasCompleted → (certificate check)
```

### ۳٫۳ آزمون
```
دانشجو → POST /lms/quizzes/{id}/attempt
  → QuizAttempt ایجاد (با time_limit)
  → ثبت پاسخ‌ها
  → نمره‌دهی خودکار (multiple_choice/true_false)
  → نمره‌دهی دستی (descriptive) توسط مدرس
  → اگر pass → Event: QuizWasPassed
```

### ۳٫۴ صدور مدرک (با Workflow)
```
Enrollment completed + همهٔ آزمون‌ها pass
  → Workflow: issue-certificate
    → generate PDF (report engine یا اختصاصی)
    → ذخیره در media
    → serial یکتا + verify_url
    → Event: CertificateWasIssued → notification
```

## ۴. نقش‌ها
| نقش | مجوز |
|---|---|
| `student` | ثبت‌نام، مشاهدهٔ محتوای خود، آزمون |
| `instructor` | ساخت/ویرایش دورهٔ خود، نمره‌دهی |
| `assistant` | کمک به نمره‌دهی، پاسخ به سؤال |
| `lms-admin` | مدیریت همهٔ دوره‌ها، دسته‌ها، گواهی‌ها |

## ۵. رویدادها
| رویداد | مصرف‌کننده |
|---|---|
| `lms.student.enrolled` | notification (welcome), report |
| `lms.lesson.completed` | progress recalc, gamification |
| `lms.course.completed` | certificate workflow, notification |
| `lms.quiz.passed` | progress, badge |
| `lms.certificate.issued` | notification (email با PDF) |

## ۶. ویژگی‌های پیشرفته
| ویژگی | موتور |
|---|---|
| پخش ویدیو (HLS/streaming) | media + filesystem |
| آزمون آنلاین با timer | lms + realtime |
| نمره‌دهی دستی | workflow (task) |
| مدرک PDF با verify | report/media |
| مسیر یادگیری (Learning Path) | lms |
| Gamification (badge/points) | lms + event |
| گزارش پیشرفت | report |
| وبینار زنده | realtime |

## ۷. تست E2E
| ID | سناریو |
|---|---|
| E2E-01 | دانشجو در دورهٔ پولی ثبت‌نام می‌کند (پرداخت) |
| E2E-02 | مشاهدهٔ درس + پیشرفت |
| E2E-03 | آزمون با نمره‌دهی خودکار |
| E2E-04 | تکمیل دوره → صدور مدرک |
| E2E-05 | verify مدرک با serial |
| E2E-06 | instructor دوره می‌سازد |
| E2E-07 | گزارش پیشرفت |

## ۸. معیار پذیرش 🧪
- AC-LMS-01..07.
- AC-LMS-08: صدور مدرک با Workflow و verify عمومی.

---

## ۹. وضعیت پیاده‌سازی (M9 — TVN-0095) ✅

| بخش | محل |
|---|---|
| ماژول دامنه | `modules/lms/` (tavaan.lms — hexagonal) |
| کیت | `kits/lms/` (kit.json + ۳ preset + theme emerald + roles.json + demo + docs) |
| نصب | `php bin/tvn kit:install lms --with-demo` |
| جداول | `lms_categories` / `lms_courses` / `lms_sections` / `lms_lessons` / `lms_course_prerequisites` / `lms_enrollments` (uk student+course) / `lms_lesson_progress` (uk enrollment+lesson) / `lms_quizzes` / `lms_questions` / `lms_quiz_attempts` / `lms_certificates` (serial یکتا) / `lms_comments` (Q&A) |
| Aggregateها | Course (+Section/Lesson/Prerequisite) / Enrollment (+LessonProgress) / Quiz (+Question/Attempt) / Certificate — ULID برای شناسه‌ها |
| نقش‌ها | student / instructor (مالکیت با CoursePolicy) / assistant (grade-any) / lms-admin (kits/lms/seed/roles.json + ۱۴ مجوز manifest) |
| پرداخت | پورت `PaymentGatewayPort` — Sandbox قطعی (شبیه‌ساز `/lms/pay/sandbox/{authority}`) + Engine (موتور payment) • verify با مبلغ محلی • callback idempotent |
| آزمون | خودکار (mc/tf/short با AnswerNormalizer فارسی‌آگاه) + دستی (descriptive) در صف `/admin/lms/grading` • time_limit (رد ارسال دیرهنگام + تایمر کلاینت) • max_attempts • pass_score درصدی (مقایسهٔ int) |
| گواهی | Workflow `lms-issue-certificate` (requested→generating→issued/failed) • serial با رقم کنترل Crockford • سند HTML آمادهٔ چاپ + snapshot روی Storage • verify عمومی + ابطال |
| پیشرفت | POST `/lms/lessons/{id}/complete` → recalc → تکمیل دوره → certificate check • درس quiz با قبولی آزمون تکمیل می‌شود • `tvn lms:recompute-progress` |
| جست‌وجو | ایندکس `lms_courses` (رویدادمحور) + fallback LIKE با واریانت رقم فارسی/لاتین |
| Q&A | پورت ماژول comment روی `lms_comments` — پاسخ staff (مالک/grade) بدون moderation |
| گزارش | `/admin/lms/reports/progress` (+CSV با BOM و ضد injection) — تفکیک مدرس/مدیر |
| تست | `modules/lms/tests/` — ۴۰ تست (۲۰ Unit + ۲۰ Feature: E2E-01..07 + نقش‌ها/پیش‌نیاز/XSS/Q&A/tamper-serial) |

انطباق: E2E-01 (ثبت‌نام پولی: enroll→sandbox→callback→active+اعلان+idempotency) ✅ •
E2E-02 (درس+پیشرفت ۵۰→۱۰۰٪+تکمیل+گاردهای دسترسی/پیش‌نمایش رایگان) ✅ • E2E-03 (آزمون
خودکار: فرم/submit/نمره/قبولی + دستی: صف→نمره→اعلان + سقف تلاش + تایمر) ✅ • E2E-04
(تکمیل دوره → صدور گواهی با Workflow — نمونهٔ فرایند در `tvn_process_instances` +
workflow_instance_id روی گواهی) ✅ • E2E-05 (verify عمومی با serial: معتبر/tamper→«یافت
نشد»/ابطال→«باطل»/سند ۴۰۴) ✅ • E2E-06 (instructor: ساخت→انتشار مشروط به درس→ویترین
+ ۴۰۳ روی دورهٔ غیرخود) ✅ • E2E-07 (گزارش پیشرفت + CSV) ✅ • AC-LMS-08 (Workflow +
verify عمومی + رقم کنترل) ✅

> 📄 سند گواهی: HTML آمادهٔ چاپ (print-to-PDF) — همان رویکرد `PdfExporter` موتور
> report با `pdf_driver=html` (بدون ext، RTL/فارسی کامل). PDF باینری با فونت
> فارسیِ embed در بک‌لاگ Release 1.0 ثبت شد.

```yaml
# machine-readable: tavaan.kit.lms.v1 (وضعیت M9)
kit:
  id: tavaan.kit.lms
  status: implemented
  ticket: TVN-0095
  modules: [tavaan.core-user, tavaan.content, tavaan.comment, tavaan.media, tavaan.lms]
  routes_public: [/lms, /lms/search, /lms/category/{slug}, /lms/courses/{slug}, /lms/courses/{id}/enroll, /lms/pay/{id}, /lms/pay/sandbox/{authority}, /lms/pay/callback, /lms/learn, /lms/learn/{slug}, /lms/lessons/{id}, /lms/lessons/{id}/complete, /lms/lessons/{id}/questions, /lms/quizzes/{id}, /lms/quizzes/{id}/attempt, /lms/attempts/{id}, /lms/attempts/{id}/submit, /lms/attempts/{id}/result, /lms/verify-certificate, /lms/certificates/{serial}, /lms/certificates/{serial}/document]
  routes_admin: [/admin/lms/courses, /admin/lms/courses/{id}/edit, /admin/lms/courses/{id}/publish, /admin/lms/courses/{id}/quizzes, /admin/lms/quizzes/{id}/edit, /admin/lms/grading, /admin/lms/enrollments, /admin/lms/certificates, /admin/lms/reports/progress]
  events: [lms.course.published, lms.course.updated, lms.student.enrolled, lms.lesson.completed, lms.course.completed, lms.quiz.passed, lms.attempt.graded, lms.certificate.issued]
  workflow: lms-issue-certificate
  search_index: lms_courses
  tests: {unit: modules/lms/tests/Unit, feature: modules/lms/tests/Feature}
```
