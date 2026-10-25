<?php

declare(strict_types=1);

/*
 * ماژول LMS — مسیرهای پنل مدیریت (DOC-095 §4).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate/Policy) در
 * کنترلرها بررسی می‌شوند (الگوی forum/shop).
 */

use Modules\Lms\Http\Controllers\Admin\CertificateAdminController;
use Modules\Lms\Http\Controllers\Admin\CourseAdminController;
use Modules\Lms\Http\Controllers\Admin\EnrollmentAdminController;
use Modules\Lms\Http\Controllers\Admin\GradingAdminController;
use Modules\Lms\Http\Controllers\Admin\QuizAdminController;
use Modules\Lms\Http\Controllers\Admin\ReportAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group(['prefix' => '/admin/lms', 'middleware' => [AuthenticateMiddleware::class]], static function (): void {
    // گزارش پیشرفت (DOC-095 §6)
    Route::get('/reports/progress', [ReportAdminController::class, 'progress'])->name('admin.lms.report.progress');
    Route::get('/reports/progress.csv', [ReportAdminController::class, 'progressCsv'])->name('admin.lms.report.csv');

    // دوره‌ها
    Route::get('/courses', [CourseAdminController::class, 'index'])->name('admin.lms.courses');
    Route::get('/courses/create', [CourseAdminController::class, 'create'])->name('admin.lms.courses.create');
    Route::post('/courses', [CourseAdminController::class, 'store'])->name('admin.lms.courses.store');
    Route::get('/courses/{id}/edit', [CourseAdminController::class, 'edit'])->name('admin.lms.courses.edit');
    Route::post('/courses/{id}', [CourseAdminController::class, 'update'])->name('admin.lms.courses.update');
    Route::post('/courses/{id}/publish', [CourseAdminController::class, 'publish'])->name('admin.lms.courses.publish');
    Route::post('/courses/{id}/archive', [CourseAdminController::class, 'archive'])->name('admin.lms.courses.archive');
    Route::delete('/courses/{id}', [CourseAdminController::class, 'destroy'])->name('admin.lms.courses.destroy');

    // بخش‌ها/درس‌ها
    Route::post('/courses/{id}/sections', [CourseAdminController::class, 'addSection'])->name('admin.lms.sections.store');
    Route::post('/courses/{id}/sections/{sectionId}', [CourseAdminController::class, 'updateSection'])->name('admin.lms.sections.update');
    Route::delete('/courses/{id}/sections/{sectionId}', [CourseAdminController::class, 'deleteSection'])->name('admin.lms.sections.destroy');
    Route::post('/courses/{id}/sections/{sectionId}/lessons', [CourseAdminController::class, 'addLesson'])->name('admin.lms.lessons.store');
    Route::post('/courses/{id}/lessons/{lessonId}', [CourseAdminController::class, 'updateLesson'])->name('admin.lms.lessons.update');
    Route::delete('/courses/{id}/lessons/{lessonId}', [CourseAdminController::class, 'deleteLesson'])->name('admin.lms.lessons.destroy');

    // آزمون‌ها/سؤال‌ها
    Route::get('/courses/{id}/quizzes', [QuizAdminController::class, 'index'])->name('admin.lms.quizzes');
    Route::post('/courses/{id}/quizzes', [QuizAdminController::class, 'store'])->name('admin.lms.quizzes.store');
    Route::get('/quizzes/{quizId}/edit', [QuizAdminController::class, 'edit'])->name('admin.lms.quizzes.edit');
    Route::post('/quizzes/{quizId}', [QuizAdminController::class, 'update'])->name('admin.lms.quizzes.update');
    Route::delete('/quizzes/{quizId}', [QuizAdminController::class, 'destroy'])->name('admin.lms.quizzes.destroy');
    Route::post('/quizzes/{quizId}/questions', [QuizAdminController::class, 'addQuestion'])->name('admin.lms.questions.store');
    Route::post('/quizzes/{quizId}/questions/{questionId}', [QuizAdminController::class, 'updateQuestion'])->name('admin.lms.questions.update');
    Route::delete('/quizzes/{quizId}/questions/{questionId}', [QuizAdminController::class, 'deleteQuestion'])->name('admin.lms.questions.destroy');

    // نمره‌دهی دستی
    Route::get('/grading', [GradingAdminController::class, 'index'])->name('admin.lms.grading');
    Route::get('/grading/{attemptId}', [GradingAdminController::class, 'show'])->name('admin.lms.grading.show');
    Route::post('/grading/{attemptId}', [GradingAdminController::class, 'grade'])->name('admin.lms.grading.grade');

    // ثبت‌نام‌ها
    Route::get('/enrollments', [EnrollmentAdminController::class, 'index'])->name('admin.lms.enrollments');
    Route::post('/enrollments/{id}/drop', [EnrollmentAdminController::class, 'drop'])->name('admin.lms.enrollments.drop');
    Route::post('/enrollments/{id}/reactivate', [EnrollmentAdminController::class, 'reactivate'])->name('admin.lms.enrollments.reactivate');
    Route::post('/enrollments/{id}/certificate', [EnrollmentAdminController::class, 'issueCertificate'])->name('admin.lms.enrollments.certificate');

    // گواهی‌ها
    Route::get('/certificates', [CertificateAdminController::class, 'index'])->name('admin.lms.certificates');
    Route::post('/certificates/{id}/revoke', [CertificateAdminController::class, 'revoke'])->name('admin.lms.certificates.revoke');

    // دسته‌ها
    Route::post('/categories', [CourseAdminController::class, 'storeCategory'])->name('admin.lms.categories.store');
    Route::delete('/categories/{categoryId}', [CourseAdminController::class, 'destroyCategory'])->name('admin.lms.categories.destroy');
});
