<?php

declare(strict_types=1);

/*
 * ماژول پرسش‌های متداول — روت‌های پنل مدیریت (/admin/faqs + /admin/faq-categories).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate) در کنترلر.
 */

use Modules\Faq\Http\Controllers\Admin\FaqAdminController;
use Modules\Faq\Http\Controllers\Admin\FaqCategoryAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group([
    'prefix' => '/admin/faqs',
    'middleware' => [AuthenticateMiddleware::class],
    'where' => ['id' => '[0-9A-Za-z]{26}'],
], static function (): void {
    Route::get('', [FaqAdminController::class, 'index'])->name('admin.faqs.index');
    Route::get('/create', [FaqAdminController::class, 'create'])->name('admin.faqs.create');
    Route::post('', [FaqAdminController::class, 'store'])->name('admin.faqs.store');
    Route::get('/{id}/edit', [FaqAdminController::class, 'edit'])->name('admin.faqs.edit');
    Route::post('/{id}', [FaqAdminController::class, 'update'])->name('admin.faqs.update');
    Route::post('/{id}/publish', [FaqAdminController::class, 'publish'])->name('admin.faqs.publish');
    Route::post('/{id}/archive', [FaqAdminController::class, 'archive'])->name('admin.faqs.archive');
    Route::post('/{id}/draft', [FaqAdminController::class, 'draft'])->name('admin.faqs.draft');
    Route::delete('/{id}', [FaqAdminController::class, 'destroy'])->name('admin.faqs.destroy');
});

Route::group([
    'prefix' => '/admin/faq-categories',
    'middleware' => [AuthenticateMiddleware::class],
    'where' => ['id' => '[0-9A-Za-z]{26}'],
], static function (): void {
    Route::get('', [FaqCategoryAdminController::class, 'index'])->name('admin.faq-categories.index');
    Route::post('', [FaqCategoryAdminController::class, 'store'])->name('admin.faq-categories.store');
    Route::post('/{id}', [FaqCategoryAdminController::class, 'update'])->name('admin.faq-categories.update');
    Route::delete('/{id}', [FaqCategoryAdminController::class, 'destroy'])->name('admin.faq-categories.destroy');
});
