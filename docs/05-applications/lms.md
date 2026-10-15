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
