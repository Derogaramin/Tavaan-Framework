<?php

declare(strict_types=1);

/*
 * ماژول نظرسنجی — روت‌های پنل مدیریت (/admin/polls).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate) در کنترلر.
 */

use Modules\Poll\Http\Controllers\Admin\PollAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group([
    'prefix' => '/admin/polls',
    'middleware' => [AuthenticateMiddleware::class],
    'where' => ['id' => '[0-9A-Za-z]{26}'],
], static function (): void {
    Route::get('', [PollAdminController::class, 'index'])->name('admin.polls.index');
    Route::get('/create', [PollAdminController::class, 'create'])->name('admin.polls.create');
    Route::post('', [PollAdminController::class, 'store'])->name('admin.polls.store');
    Route::get('/{id}/edit', [PollAdminController::class, 'edit'])->name('admin.polls.edit');
    Route::post('/{id}', [PollAdminController::class, 'update'])->name('admin.polls.update');
    Route::post('/{id}/open', [PollAdminController::class, 'open'])->name('admin.polls.open');
    Route::post('/{id}/close', [PollAdminController::class, 'close'])->name('admin.polls.close');
    Route::delete('/{id}', [PollAdminController::class, 'destroy'])->name('admin.polls.destroy');
});
