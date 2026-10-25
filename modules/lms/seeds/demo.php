<?php

declare(strict_types=1);

/*
 * ماژول LMS — seed دمو (DOC-095؛ kit:install lms --with-demo).
 *
 * دادهٔ نمایشی: ۳ دسته، ۲ دورهٔ منتشرشده (رایگان + پولی) با بخش/درس/
 * آزمون و سؤال. idempotent — با slug موجود، رد می‌شود.
 */

use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Ulid;

return static function (): void {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = 'Y-m-d H:i:s.v';

    // ── مدرس — از کاربران دمو (core-user) ─────────────────────────────────
    $instructor = DB::table('users')->where('email', 'author@blog.test')->first()
        ?? DB::table('users')->orderBy('id')->first();
    if ($instructor === null) {
        return;
    }
    $instructorId = (int) $instructor['id'];

    // ── دسته‌ها ───────────────────────────────────────────────────────────
    $categories = [];
    foreach ([
        ['title' => 'برنامه‌نویسی', 'slug' => 'programming', 'desc' => 'دوره‌های توسعهٔ نرم‌افزار'],
        ['title' => 'زبان خارجی', 'slug' => 'languages', 'desc' => 'آموزش زبان'],
        ['title' => 'کسب‌وکار', 'slug' => 'business', 'desc' => 'مهارت‌های کسب‌وکار'],
    ] as $cat) {
        $exists = DB::table('lms_categories')->where('slug', $cat['slug'])->first();
        if ($exists !== null) {
            $categories[$cat['slug']] = (int) $exists['id'];

            continue;
        }
        $categories[$cat['slug']] = (int) DB::table('lms_categories')->insertGetId([
            'title' => $cat['title'],
            'slug' => $cat['slug'],
            'description' => $cat['desc'],
            'created_at' => $now->format($fmt),
            'updated_at' => null,
        ]);
    }

    // ── دوره‌ها ───────────────────────────────────────────────────────────
    $courses = [
        [
            'slug' => 'php-modern-tavaan', 'title' => 'PHP نوین با فریمورک تَوان',
            'category' => 'programming', 'price' => 0, 'is_free' => true,
            'level' => 'intermediate', 'duration' => 150,
            'summary' => 'از صفر تا انتشار یک اپلیکیشن با PHP 8.2 و معماری ماژولار تَوان.',
            'description' => '<p>در این دوره با PHP نوین، الگوهای معماری (Hexagonal/HMVC) و ساخت کیت اپلیکیشن روی فریمورک صفر‌وابستگی تَوان آشنا می‌شوید.</p>',
            'lessons' => [
                ['section' => 'شروع کار', 'title' => 'معرفی دوره و نصب ابزارها', 'type' => 'text', 'duration' => 20, 'preview' => true,
                 'content' => '<p>در این درس با پیش‌نیازهای دوره و ابزارهای توسعه آشنا می‌شوید.</p>'],
                ['section' => 'شروع کار', 'title' => 'نخستین درخواست HTTP', 'type' => 'video', 'duration' => 35,
                 'content' => '<p>مسیر یک درخواست از <code>public/index.php</code> تا پاسخ را دنبال می‌کنیم.</p>'],
                ['section' => 'معماری', 'title' => 'Container و Auto-Wiring', 'type' => 'text', 'duration' => 45,
                 'content' => '<p>تزریق وابستگی، ReflectionCache و پیاده‌سازی Container صفر‌وابستگی.</p>'],
                ['section' => 'معماری', 'title' => 'آزمون پایانی دوره', 'type' => 'quiz', 'duration' => 50],
            ],
        ],
        [
            'slug' => 'farsi-business-writing', 'title' => 'نگارش تجاری به فارسی',
            'category' => 'business', 'price' => 8_900_000, 'is_free' => false,
            'level' => 'beginner', 'duration' => 95,
            'summary' => 'ایمیل، گزارش و نامهٔ اداریِ حرفه‌ای به فارسی — با نمونه‌های واقعی.',
            'description' => '<p>اصول نگارش تجاری فارسی، لحن مناسب، ساختار نامهٔ اداری و رایج‌ترین اشتباه‌ها.</p>',
            'lessons' => [
                ['section' => 'اصول', 'title' => 'ساختار ایمیل حرفه‌ای', 'type' => 'text', 'duration' => 30, 'preview' => true,
                 'content' => '<p>موضوع، گیرنده، متن و امضا — اجزای یک ایمیل اداری.</p>'],
                ['section' => 'اصول', 'title' => 'لحن و ادب در مکاتبهٔ اداری', 'type' => 'text', 'duration' => 25,
                 'content' => '<p>رسمی/نیمه‌رسمی؛ چه زمانی کدام لحن را انتخاب کنیم.</p>'],
                ['section' => 'کارگاه', 'title' => 'گزارش‌نویسی در یک صفحه', 'type' => 'text', 'duration' => 40,
                 'content' => '<p>خلاصه‌نویسی اجرایی، جدول و نمودار در گزارش.</p>'],
            ],
        ],
    ];

    foreach ($courses as $data) {
        if (DB::table('lms_courses')->where('slug', $data['slug'])->exists()) {
            continue;
        }

        $courseId = Ulid::generate($now)->toString();
        DB::table('lms_courses')->insert([
            'id' => $courseId,
            'slug' => $data['slug'],
            'title' => $data['title'],
            'summary' => $data['summary'],
            'description' => $data['description'],
            'instructor_id' => $instructorId,
            'category_id' => $categories[$data['category']] ?? null,
            'level' => $data['level'],
            'language' => 'fa',
            'price' => $data['price'],
            'currency' => 'IRR',
            'is_free' => $data['is_free'],
            'status' => 'published',
            'thumbnail' => null,
            'total_duration' => $data['duration'],
            'rating_avg' => 0,
            'student_count' => 0,
            'published_at' => $now->format($fmt),
            'created_at' => $now->format($fmt),
            'updated_at' => null,
        ]);

        // بخش‌ها/درس‌ها
        $sectionIds = [];
        $position = 0;
        foreach ($data['lessons'] as $i => $lesson) {
            if (!isset($sectionIds[$lesson['section']])) {
                $sectionIds[$lesson['section']] = Ulid::generate($now)->toString();
                DB::table('lms_sections')->insert([
                    'id' => $sectionIds[$lesson['section']],
                    'course_id' => $courseId,
                    'title' => $lesson['section'],
                    'position' => \count($sectionIds) - 1,
                ]);
            }

            $lessonId = Ulid::generate($now)->toString();
            $quizId = null;

            if (($lesson['type'] ?? 'text') === 'quiz') {
                // آزمون نمونه با دو سؤال (خودکار + تشریحی)
                $quizId = Ulid::generate($now)->toString();
                DB::table('lms_quizzes')->insert([
                    'id' => $quizId,
                    'lesson_id' => $lessonId,
                    'course_id' => $courseId,
                    'title' => $lesson['title'],
                    'time_limit' => 10,
                    'pass_score' => 50,
                    'max_attempts' => 3,
                ]);

                $q1 = Ulid::generate($now)->toString();
                DB::table('lms_questions')->insert([
                    'id' => $q1, 'quiz_id' => $quizId, 'type' => 'multiple_choice',
                    'body' => 'کدام نسخهٔ PHP حداقلِ موردنیاز فریمورک تَوان است؟',
                    'options' => (string) \json_encode([
                        ['key' => 'a', 'text' => 'PHP 7.4'],
                        ['key' => 'b', 'text' => 'PHP 8.1'],
                        ['key' => 'c', 'text' => 'PHP 8.2'],
                        ['key' => 'd', 'text' => 'PHP 8.4'],
                    ], JSON_UNESCAPED_UNICODE),
                    'correct_answer' => 'c', 'score' => 2, 'position' => 0,
                ]);

                $q2 = Ulid::generate($now)->toString();
                DB::table('lms_questions')->insert([
                    'id' => $q2, 'quiz_id' => $quizId, 'type' => 'true_false',
                    'body' => 'تَوان در زمان اجرا به Composer نیاز دارد.',
                    'options' => null,
                    'correct_answer' => 'false', 'score' => 1, 'position' => 1,
                ]);

                $q3 = Ulid::generate($now)->toString();
                DB::table('lms_questions')->insert([
                    'id' => $q3, 'quiz_id' => $quizId, 'type' => 'descriptive',
                    'body' => 'تفاوت معماری لایه‌ای و شش‌ضلعی را در دو پاراگراف توضیح دهید.',
                    'options' => null,
                    'correct_answer' => null, 'score' => 2, 'position' => 2,
                ]);
            }

            $slugBase = 'lesson-' . ($i + 1);
            DB::table('lms_lessons')->insert([
                'id' => $lessonId,
                'section_id' => $sectionIds[$lesson['section']],
                'course_id' => $courseId,
                'title' => $lesson['title'],
                'slug' => $slugBase,
                'type' => $lesson['type'],
                'content' => $lesson['content'] ?? null,
                'video_url' => ($lesson['type'] ?? '') === 'video' ? 'https://cdn.example.com/videos/tavaan-intro.mp4' : null,
                'attachment_path' => null,
                'quiz_id' => $quizId,
                'duration' => $lesson['duration'] ?? 0,
                'is_free_preview' => $lesson['preview'] ?? false,
                'position' => $position++,
            ]);
        }
    }
};
