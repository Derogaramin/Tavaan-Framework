<?php

declare(strict_types=1);

/*
 * ماژول رسانه — روت‌های پنل مدیریت (/admin/media).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 */

use Modules\Media\Http\Controllers\Admin\MediaAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group([
    'prefix' => '/admin/media',
    'middleware' => [AuthenticateMiddleware::class],
    'where' => ['id' => '[0-9A-Za-z]{26}'],
], static function (): void {
    Route::get('', [MediaAdminController::class, 'index'])->name('admin.media.index');
    Route::post('', [MediaAdminController::class, 'store'])->name('admin.media.store');
    Route::delete('/{id}', [MediaAdminController::class, 'destroy'])->name('admin.media.destroy');
});
