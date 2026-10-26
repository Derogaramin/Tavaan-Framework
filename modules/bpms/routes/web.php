<?php

declare(strict_types=1);

/*
 * ماژول BPMS — مسیرهای وب (کاربرانی که کار انجام می‌دهند/فرایند آغاز می‌کنند).
 * نام‌گذاری با prefix «bpms.» (DoD §5). همه با احراز هویت — کار/فرایند
 * مهمان بی‌معنا است.
 */

use Modules\Bpms\Http\Controllers\Web\ProcessController;
use Modules\Bpms\Http\Controllers\Web\TaskController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group(['prefix' => '/bpms', 'middleware' => [AuthenticateMiddleware::class]], static function (): void {
    // کارهای من (inbox — §7 task-assignee)
    Route::get('/tasks', [TaskController::class, 'index'])->name('bpms.tasks');
    Route::get('/tasks/{id}', [TaskController::class, 'show'])->name('bpms.tasks.show');
    Route::post('/tasks/{id}/claim', [TaskController::class, 'claim'])->name('bpms.tasks.claim');
    Route::post('/tasks/{id}/complete', [TaskController::class, 'complete'])->name('bpms.tasks.complete');
    Route::post('/tasks/{id}/delegate', [TaskController::class, 'delegate'])->name('bpms.tasks.delegate');

    // فرایندها (کاتالوگ + آغاز + نمونه‌های من)
    Route::get('/processes', [ProcessController::class, 'index'])->name('bpms.processes');
    Route::get('/processes/{key}/start', [ProcessController::class, 'create'])->name('bpms.processes.start');
    Route::post('/processes/{key}/start', [ProcessController::class, 'store'])->name('bpms.processes.store');
    Route::get('/instances', [ProcessController::class, 'instances'])->name('bpms.instances');
    Route::get('/instances/{id}', [ProcessController::class, 'show'])->name('bpms.instances.show');
});
