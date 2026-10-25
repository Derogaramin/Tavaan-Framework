<?php

declare(strict_types=1);

use Tavaan\Core\Facade\Facades\Schema;
use Tavaan\Engine\Database\Migration\Migration;
use Tavaan\Engine\Database\Schema\Blueprint;

/*
 * جداول ماژول LMS — DOC-095 §2.
 *
 * 🔒 پول: int minor-unit (ADR-0013 — هرگز float/DECIMAL در لایهٔ اپ؛
 * انطباق با DECIMAL(18,4)/DECIMAL مستند در گزارش M9 گام ۵ ثبت شده است).
 * prefix ماژول = lms_ (DoD §3). FULLTEXT روی sqlite/pg → index معمولی.
 *
 * افزودنی‌های مستند نسبت به DOC-095 §2:
 * - lms_categories: جدول دسته‌ها (DOC ستون category_id را دارد ولی جدولش را تعریف نکرده)
 * - lms_course_prerequisites: پیش‌نیاز دوره (Aggregate §1.2 — Prerequisite)
 * - lms_comments: پرسش‌وپاسخ درس (پورت CommentStoreInterface ماژول comment)
 * - enrollments.status='pending_payment' + payment_authority: جریان پرداخت §3.1
 * - quiz_attempts.status/graded_*: نمره‌دهی دستی §3.3
 * - certificates.status/workflow_instance_id: Workflow صدور مدرک §3.4 + ابطال
 */

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('lms_categories')) {
            Schema::create('lms_categories', function (Blueprint $t): void {
                $t->id();
                $t->string('title', 190);
                $t->string('slug', 200)->unique();
                $t->text('description')->nullable();
                $t->timestamps(3);
            });
        }

        if (!Schema::hasTable('lms_courses')) {
            Schema::create('lms_courses', function (Blueprint $t): void {
                $t->char('id', 26)->primary();                       // ULID
                $t->string('slug', 200)->unique();
                $t->string('title', 255);
                $t->text('summary')->nullable();
                $t->longText('description')->nullable();
                $t->unsignedBigInteger('instructor_id');
                $t->unsignedBigInteger('category_id')->nullable();
                $t->string('level', 20)->default('beginner');        // beginner|intermediate|advanced
                $t->char('language', 5)->default('fa');
                $t->unsignedBigInteger('price')->default(0);         // minor-unit (ADR-0013)
                $t->char('currency', 3)->default('IRR');
                $t->boolean('is_free')->default(false);
                $t->string('status', 20)->default('draft');          // draft|published|archived
                $t->char('thumbnail', 26)->nullable();               // → tvn_media
                $t->integer('total_duration')->default(0);           // دقیقه
                $t->decimal('rating_avg', 3, 2)->default(0);
                $t->integer('student_count')->default(0);
                $t->dateTime('published_at', 3)->nullable();
                $t->dateTime('created_at', 3);
                $t->dateTime('updated_at', 3)->nullable();
                $t->index(['status', 'published_at'], 'idx_lms_course_status');
                $t->index(['instructor_id'], 'idx_lms_course_instructor');
                $t->fullText(['title', 'summary'], 'ft_lms_course');
            });
        }

        if (!Schema::hasTable('lms_sections')) {
            Schema::create('lms_sections', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('course_id', 26);
                $t->string('title', 255);
                $t->integer('position')->default(0);
                $t->index(['course_id', 'position'], 'idx_lms_section_course');
            });
        }

        if (!Schema::hasTable('lms_lessons')) {
            Schema::create('lms_lessons', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('section_id', 26);
                $t->char('course_id', 26);
                $t->string('title', 255);
                $t->string('slug', 255);
                $t->string('type', 20)->default('text');             // video|text|quiz|attachment
                $t->longText('content')->nullable();
                $t->string('video_url', 500)->nullable();
                $t->string('attachment_path', 500)->nullable();
                $t->char('quiz_id', 26)->nullable();                 // درسِ نوع quiz → آزمون
                $t->integer('duration')->default(0);                 // دقیقه
                $t->boolean('is_free_preview')->default(false);
                $t->integer('position')->default(0);
                $t->index(['course_id', 'position'], 'idx_lms_lesson_course');
                $t->index(['section_id', 'position'], 'idx_lms_lesson_section');
            });
        }

        if (!Schema::hasTable('lms_course_prerequisites')) {
            Schema::create('lms_course_prerequisites', function (Blueprint $t): void {
                $t->char('course_id', 26);
                $t->char('prerequisite_id', 26);
                $t->unique(['course_id', 'prerequisite_id'], 'uk_lms_prereq');
            });
        }

        if (!Schema::hasTable('lms_enrollments')) {
            Schema::create('lms_enrollments', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->unsignedBigInteger('student_id');
                $t->char('course_id', 26);
                $t->string('status', 20)->default('active');         // pending_payment|active|completed|dropped
                $t->decimal('progress_percent', 5, 2)->default(0);
                $t->dateTime('enrolled_at', 3)->nullable();
                $t->dateTime('completed_at', 3)->nullable();
                $t->char('payment_transaction_id', 26)->nullable();  // تراکنش موتور payment
                $t->string('payment_authority', 190)->nullable();    // authority درگاه (sandbox/engine)
                $t->dateTime('created_at', 3);                       // افزودنی مستند — ترتیب‌دهی/audit
                $t->unique(['student_id', 'course_id'], 'uk_lms_enroll');
                $t->index(['course_id', 'status'], 'idx_lms_enroll_course');
            });
        }

        if (!Schema::hasTable('lms_lesson_progress')) {
            Schema::create('lms_lesson_progress', function (Blueprint $t): void {
                $t->id();
                $t->char('enrollment_id', 26);
                $t->char('lesson_id', 26);
                $t->string('status', 20)->default('not_started');    // not_started|in_progress|completed
                $t->dateTime('completed_at', 3)->nullable();
                $t->unique(['enrollment_id', 'lesson_id'], 'uk_lms_progress');
            });
        }

        if (!Schema::hasTable('lms_quizzes')) {
            Schema::create('lms_quizzes', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('lesson_id', 26)->nullable();
                $t->char('course_id', 26);
                $t->string('title', 255);
                $t->integer('time_limit')->nullable();               // دقیقه
                $t->integer('pass_score')->default(50);              // درصدی از جمع نمرات
                $t->integer('max_attempts')->default(1);
                $t->index(['lesson_id'], 'idx_lms_quiz_lesson');
                $t->index(['course_id'], 'idx_lms_quiz_course');
            });
        }

        if (!Schema::hasTable('lms_questions')) {
            Schema::create('lms_questions', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('quiz_id', 26);
                $t->string('type', 20)->default('multiple_choice');  // multiple_choice|true_false|short_answer|descriptive
                $t->text('body');
                $t->json('options')->nullable();                     // [{key,text},…]
                $t->text('correct_answer')->nullable();              // کلید گزینه/true|false/متن مرجع
                $t->integer('score')->default(1);
                $t->integer('position')->default(0);
                $t->index(['quiz_id', 'position'], 'idx_lms_question_quiz');
            });
        }

        if (!Schema::hasTable('lms_quiz_attempts')) {
            Schema::create('lms_quiz_attempts', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->char('quiz_id', 26);
                $t->unsignedBigInteger('student_id');
                $t->json('answers')->nullable();                     // {answers:{qid:…}, auto_score, grades:{qid:{score,note}}}
                $t->integer('score')->default(0);
                $t->boolean('passed')->nullable();                   // null = در انتظار نمره‌دهی دستی
                $t->string('status', 20)->default('in_progress');    // in_progress|submitted|graded
                $t->dateTime('started_at', 3);
                $t->dateTime('submitted_at', 3)->nullable();
                $t->unsignedBigInteger('graded_by')->nullable();
                $t->dateTime('graded_at', 3)->nullable();
                $t->index(['quiz_id', 'student_id'], 'idx_lms_attempt_quiz_student');
            });
        }

        if (!Schema::hasTable('lms_certificates')) {
            Schema::create('lms_certificates', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->unsignedBigInteger('template_id')->nullable();
                $t->char('enrollment_id', 26);
                $t->unsignedBigInteger('student_id');
                $t->char('course_id', 26);
                $t->string('serial', 100)->unique();
                $t->string('status', 20)->default('issued');         // issued|revoked
                $t->dateTime('issued_at', 3);
                $t->string('verify_url', 255);
                $t->string('file_path', 500)->nullable();
                $t->string('workflow_instance_id', 64)->nullable();  // نمونهٔ Workflow صدور (audit)
                $t->index(['student_id'], 'idx_lms_cert_student');
                $t->index(['course_id'], 'idx_lms_cert_course');
            });
        }

        if (!Schema::hasTable('lms_comments')) {
            Schema::create('lms_comments', function (Blueprint $t): void {
                $t->char('id', 26)->primary();
                $t->string('commentable_type', 100);
                $t->char('commentable_id', 26);
                $t->string('author_name', 100);
                $t->string('author_email', 190);
                $t->text('body');
                $t->char('parent_id', 26)->nullable();
                $t->string('status', 20)->default('pending');
                $t->dateTime('created_at', 3);
                $t->index(['commentable_type', 'commentable_id', 'status'], 'idx_lms_commentable');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_comments');
        Schema::dropIfExists('lms_certificates');
        Schema::dropIfExists('lms_quiz_attempts');
        Schema::dropIfExists('lms_questions');
        Schema::dropIfExists('lms_quizzes');
        Schema::dropIfExists('lms_lesson_progress');
        Schema::dropIfExists('lms_enrollments');
        Schema::dropIfExists('lms_course_prerequisites');
        Schema::dropIfExists('lms_lessons');
        Schema::dropIfExists('lms_sections');
        Schema::dropIfExists('lms_courses');
        Schema::dropIfExists('lms_categories');
    }
};
