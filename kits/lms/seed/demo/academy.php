<?php

declare(strict_types=1);

/*
 * دادهٔ نمونهٔ سطح-کیت LMS (پس از seed ماژول‌ها اجرا می‌شود — دوره‌ها آنجا
 * ساخته می‌شوند). قرارداد: callable() — idempotent.
 *
 * ۱) نقش‌های LMS را به کاربران دمو (core-user) اختصاص می‌دهد
 * ۲) یک ثبت‌نام فعال برای «دانشجو» در دورهٔ رایگان
 * ۳) یک دورهٔ تکمیل‌شده + گواهی معتبر (serial با رقم کنترل صحیح —
 *    صفحهٔ verify عمومی در دمو کار می‌کند)
 */

use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Support\Ulid;

return static function (): void {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = 'Y-m-d H:i:s.v';

    // ── ۱) نقش‌ها ─────────────────────────────────────────────────────────
    $assignments = [
        'admin@blog.test' => 'lms-admin',
        'author@blog.test' => 'instructor',
        'editor@blog.test' => 'assistant',
        'reader@blog.test' => 'student',
    ];

    foreach ($assignments as $email => $roleKey) {
        $user = DB::table('users')->where('email', $email)->first();
        $role = DB::table('tvn_roles')->where('key', $roleKey)->first();
        if ($user === null || $role === null) {
            continue;
        }

        $exists = DB::table('tvn_role_user')
            ->where('user_id', $user['id'])
            ->where('role_id', $role['id'])
            ->exists();
        if (!$exists) {
            DB::table('tvn_role_user')->insert([
                'user_id' => $user['id'],
                'role_id' => $role['id'],
            ]);
        }
    }

    $student = DB::table('users')->where('email', 'reader@blog.test')->first();
    $course = DB::table('lms_courses')->where('slug', 'php-modern-tavaan')->first();
    if ($student === null || $course === null) {
        return;
    }
    $studentId = (int) $student['id'];
    $courseId = (string) $course['id'];

    // ── ۲) ثبت‌نام فعال در دورهٔ رایگان ───────────────────────────────────
    $hasEnrollment = DB::table('lms_enrollments')
        ->where('student_id', $studentId)
        ->where('course_id', $courseId)
        ->exists();

    if (!$hasEnrollment) {
        $enrollmentId = Ulid::generate($now)->toString();
        DB::table('lms_enrollments')->insert([
            'id' => $enrollmentId,
            'student_id' => $studentId,
            'course_id' => $courseId,
            'status' => 'active',
            'progress_percent' => 0,
            'enrolled_at' => $now->format($fmt),
            'completed_at' => null,
            'payment_transaction_id' => null,
            'payment_authority' => null,
            'created_at' => $now->format($fmt),
        ]);

        // درس اول تکمیل‌شده — پیشرفت قابل مشاهده در دمو
        $firstLesson = DB::table('lms_lessons')->where('course_id', $courseId)->orderBy('position')->first();
        if ($firstLesson !== null) {
            $total = DB::table('lms_lessons')->where('course_id', $courseId)->count();
            $percent = $total > 0 ? round(floor(10000 / $total) / 100, 2) : 0.0;

            DB::table('lms_lesson_progress')->insert([
                'enrollment_id' => $enrollmentId,
                'lesson_id' => (string) $firstLesson['id'],
                'status' => 'completed',
                'completed_at' => $now->format($fmt),
            ]);
            DB::table('lms_enrollments')->where('id', $enrollmentId)->update(['progress_percent' => $percent]);
        }
    }

    // ── ۳) دورهٔ تکمیل‌شده + گواهی دمو (دورهٔ دوم) ────────────────────────
    $course2 = DB::table('lms_courses')->where('slug', 'farsi-business-writing')->first();
    if ($course2 === null) {
        return;
    }
    $course2Id = (string) $course2['id'];

    $completed = DB::table('lms_enrollments')
        ->where('student_id', $studentId)
        ->where('course_id', $course2Id)
        ->first();

    if ($completed === null) {
        $enrollmentId = Ulid::generate($now)->toString();
        DB::table('lms_enrollments')->insert([
            'id' => $enrollmentId,
            'student_id' => $studentId,
            'course_id' => $course2Id,
            'status' => 'completed',
            'progress_percent' => 100,
            'enrolled_at' => $now->sub(new DateInterval('P20D'))->format($fmt),
            'completed_at' => $now->sub(new DateInterval('P2D'))->format($fmt),
            'payment_transaction_id' => null,
            'payment_authority' => null,
            'created_at' => $now->sub(new DateInterval('P20D'))->format($fmt),
        ]);

        foreach (DB::table('lms_lessons')->where('course_id', $course2Id)->get() as $lesson) {
            DB::table('lms_lesson_progress')->insert([
                'enrollment_id' => $enrollmentId,
                'lesson_id' => (string) $lesson['id'],
                'status' => 'completed',
                'completed_at' => $now->sub(new DateInterval('P3D'))->format($fmt),
            ]);
        }

        // گواهی با serial معتبر (همان الگوریتم CertificateSerialGenerator)
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $body = '';
        $bytes = random_bytes(10);
        for ($i = 0; $i < 10; $i++) {
            $body .= $alphabet[ord($bytes[$i]) % 32];
        }
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += strpos($alphabet, $body[$i]) * ($i + 1);
        }
        $serial = 'TVN-LMS-' . $body . '-' . $alphabet[$sum % 31];

        DB::table('lms_certificates')->insert([
            'id' => Ulid::generate($now)->toString(),
            'template_id' => null,
            'enrollment_id' => $enrollmentId,
            'student_id' => $studentId,
            'course_id' => $course2Id,
            'serial' => $serial,
            'status' => 'issued',
            'issued_at' => $now->sub(new DateInterval('P2D'))->format($fmt),
            'verify_url' => '/lms/certificates/' . $serial,
            'file_path' => null,
            'workflow_instance_id' => null,
        ]);
    }
};
