<?php

declare(strict_types=1);

/*
 * ماژول Comment — روت‌های admin (صف moderation سراسری).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate) در کنترلر.
 */

use Modules\Comment\Http\Controllers\Admin\CommentAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group([
    'prefix' => '/admin/comments',
    'middleware' => [AuthenticateMiddleware::class],
    'where' => ['id' => '[0-9A-Za-z]{26}'],
], static function (): void {
    Route::get('', [CommentAdminController::class, 'index'])->name('admin.comments.index');
    Route::post('/{id}/approve', [CommentAdminController::class, 'approve'])->name('admin.comments.approve');
    Route::post('/{id}/spam', [CommentAdminController::class, 'spam'])->name('admin.comments.spam');
    Route::post('/{id}/unapprove', [CommentAdminController::class, 'unapprove'])->name('admin.comments.unapprove');
    Route::delete('/{id}', [CommentAdminController::class, 'destroy'])->name('admin.comments.destroy');
});
