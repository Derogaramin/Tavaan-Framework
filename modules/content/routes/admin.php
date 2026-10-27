<?php

declare(strict_types=1);

/*
 * ماژول محتوا — روت‌های پنل مدیریت (/admin/pages).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate) در کنترلر.
 */

use Modules\Content\Http\Controllers\Admin\PageAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group([
    'prefix' => '/admin/pages',
    'middleware' => [AuthenticateMiddleware::class],
    'where' => ['id' => '[0-9A-Za-z]{26}'],
], static function (): void {
    Route::get('', [PageAdminController::class, 'index'])->name('admin.pages.index');
    Route::get('/create', [PageAdminController::class, 'create'])->name('admin.pages.create');
    Route::post('', [PageAdminController::class, 'store'])->name('admin.pages.store');
    Route::get('/{id}/edit', [PageAdminController::class, 'edit'])->name('admin.pages.edit');
    Route::post('/{id}', [PageAdminController::class, 'update'])->name('admin.pages.update');
    Route::post('/{id}/publish', [PageAdminController::class, 'publish'])->name('admin.pages.publish');
    Route::post('/{id}/archive', [PageAdminController::class, 'archive'])->name('admin.pages.archive');
    Route::delete('/{id}', [PageAdminController::class, 'destroy'])->name('admin.pages.destroy');
});
