<?php

declare(strict_types=1);

/*
 * ماژول کاربران پایه — روت‌های پنل مدیریت (/admin/users).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate) در کنترلر.
 */

use Modules\CoreUser\Http\Controllers\Admin\UserAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group([
    'prefix' => '/admin/users',
    'middleware' => [AuthenticateMiddleware::class],
    'where' => ['id' => '[0-9]+'],
], static function (): void {
    Route::get('', [UserAdminController::class, 'index'])->name('admin.users.index');
    Route::get('/create', [UserAdminController::class, 'create'])->name('admin.users.create');
    Route::post('', [UserAdminController::class, 'store'])->name('admin.users.store');
    Route::get('/{id}/edit', [UserAdminController::class, 'edit'])->name('admin.users.edit');
    Route::post('/{id}', [UserAdminController::class, 'update'])->name('admin.users.update');
    Route::post('/{id}/status', [UserAdminController::class, 'setStatus'])->name('admin.users.status');
    Route::post('/{id}/password', [UserAdminController::class, 'resetPassword'])->name('admin.users.password');
    Route::delete('/{id}', [UserAdminController::class, 'destroy'])->name('admin.users.destroy');
});
