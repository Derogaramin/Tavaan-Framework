<?php

declare(strict_types=1);

/*
 * ماژول LMS — مسیرهای وب (DOC-095 §3).
 * نام‌گذاری با prefix «lms.» (DoD §5).
 *
 * نوشتن‌ها (ثبت‌نام/تکمیل/آزمون/پرسش) با middleware احراز هویت —
 * الگوی کیت‌های M9 (forum/shop).
 */

use Modules\Lms\Http\Controllers\CertificateController;
use Modules\Lms\Http\Controllers\EnrollmentController;
use Modules\Lms\Http\Controllers\LmsController;
use Modules\Lms\Http\Controllers\LearnController;
use Modules\Lms\Http\Controllers\QuizController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

// ── کاتالوگ عمومی ──────────────────────────────────────────────────────────
Route::get('/lms', [LmsController::class, 'index'])->name('lms.index');
Route::get('/lms/search', [LmsController::class, 'search'])->name('lms.search');
Route::get('/lms/category/{slug}', [LmsController::class, 'category'])->name('lms.category');
Route::get('/lms/courses/{slug}', [LmsController::class, 'show'])->name('lms.course.show');

// 🔒 راستی‌آزمایی عمومی گواهی (AC-LMS-08) — بدون احراز هویت
Route::get('/lms/verify-certificate', [CertificateController::class, 'verifyForm'])->name('lms.certificate.form');
Route::get('/lms/certificates/{serial}', [CertificateController::class, 'verify'])->name('lms.certificate.verify');
Route::get('/lms/certificates/{serial}/document', [CertificateController::class, 'document'])->name('lms.certificate.document');

// ── پرداخت (ترتیب: sandbox/callback پیش از الگوی {id}) ─────────────────────
Route::get('/lms/pay/sandbox/{authority}', [EnrollmentController::class, 'sandboxPage'])->name('lms.pay.sandbox');
Route::get('/lms/pay/callback', [EnrollmentController::class, 'callback'])->name('lms.pay.callback');

Route::group(['middleware' => [AuthenticateMiddleware::class]], static function (): void {
    // ثبت‌نام + پرداخت (DOC-095 §3.1)
    Route::post('/lms/courses/{id}/enroll', [EnrollmentController::class, 'enroll'])->name('lms.enroll');
    Route::get('/lms/pay/{id}', [EnrollmentController::class, 'pay'])->name('lms.pay');

    // فضای یادگیری (DOC-095 §3.2)
    Route::get('/lms/learn', [LearnController::class, 'myCourses'])->name('lms.learn');
    Route::get('/lms/learn/{slug}', [LearnController::class, 'coursePlayer'])->name('lms.learn.course');
    Route::get('/lms/lessons/{id}', [LearnController::class, 'lesson'])->name('lms.lesson');
    Route::post('/lms/lessons/{id}/complete', [LearnController::class, 'completeLesson'])->name('lms.lesson.complete');
    Route::post('/lms/lessons/{id}/questions', [LearnController::class, 'askQuestion'])->name('lms.lesson.question');

    // آزمون (DOC-095 §3.3)
    Route::get('/lms/quizzes/{id}', [QuizController::class, 'show'])->name('lms.quiz');
    Route::post('/lms/quizzes/{id}/attempt', [QuizController::class, 'startAttempt'])->name('lms.quiz.attempt');
    Route::get('/lms/attempts/{id}', [QuizController::class, 'attemptForm'])->name('lms.attempt.form');
    Route::post('/lms/attempts/{id}/submit', [QuizController::class, 'submit'])->name('lms.attempt.submit');
    Route::get('/lms/attempts/{id}/result', [QuizController::class, 'result'])->name('lms.attempt.result');
});
