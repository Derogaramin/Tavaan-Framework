<?php

declare(strict_types=1);

/*
 * ماژول انجمن — روت‌های پنل مدیریت (DOC-094 §4).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate/Policy) در
 * کنترلرها بررسی می‌شوند.
 */

use Modules\Forum\Http\Controllers\Admin\ForumAdminController;
use Modules\Forum\Http\Controllers\Admin\ModerationAdminController;
use Modules\Forum\Http\Controllers\Admin\ThreadAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group(['prefix' => '/admin/forum', 'middleware' => [AuthenticateMiddleware::class]], static function (): void {
    // تالارها (admin)
    Route::get('/forums', [ForumAdminController::class, 'index'])->name('admin.forum.forums.index');
    Route::get('/forums/create', [ForumAdminController::class, 'create'])->name('admin.forum.forums.create');
    Route::post('/forums', [ForumAdminController::class, 'store'])->name('admin.forum.forums.store');
    Route::get('/forums/{id}/edit', [ForumAdminController::class, 'edit'])->name('admin.forum.forums.edit');
    Route::post('/forums/{id}', [ForumAdminController::class, 'update'])->name('admin.forum.forums.update');
    Route::post('/forums/{id}/lock', [ForumAdminController::class, 'lock'])->name('admin.forum.forums.lock');
    Route::delete('/forums/{id}', [ForumAdminController::class, 'destroy'])->name('admin.forum.forums.destroy');

    // موضوعات (moderator)
    Route::get('/threads', [ThreadAdminController::class, 'index'])->name('admin.forum.threads.index');
    Route::post('/threads/{id}/lock', [ThreadAdminController::class, 'lock'])->name('admin.forum.threads.lock');
    Route::post('/threads/{id}/pin', [ThreadAdminController::class, 'pin'])->name('admin.forum.threads.pin');
    Route::post('/threads/{id}/move', [ThreadAdminController::class, 'move'])->name('admin.forum.threads.move');
    Route::post('/threads/{id}/rename', [ThreadAdminController::class, 'rename'])->name('admin.forum.threads.rename');
    Route::delete('/threads/{id}', [ThreadAdminController::class, 'destroy'])->name('admin.forum.threads.destroy');

    // صف moderation (moderator)
    Route::get('/moderation', [ModerationAdminController::class, 'index'])->name('admin.forum.moderation.index');
    Route::post('/moderation/{flagId}/resolve', [ModerationAdminController::class, 'resolve'])->name('admin.forum.moderation.resolve');
    Route::post('/moderation/{flagId}/dismiss', [ModerationAdminController::class, 'dismiss'])->name('admin.forum.moderation.dismiss');

    // پست‌ها (moderator)
    Route::post('/posts/{id}/hide', [ModerationAdminController::class, 'hide'])->name('admin.forum.posts.hide');
    Route::post('/posts/{id}/unhide', [ModerationAdminController::class, 'unhide'])->name('admin.forum.posts.unhide');
    Route::delete('/posts/{id}', [ModerationAdminController::class, 'destroyPost'])->name('admin.forum.posts.destroy');

    // ban کاربر (admin)
    Route::post('/users/{id}/ban', [ModerationAdminController::class, 'ban'])->name('admin.forum.users.ban');
    Route::post('/users/{id}/unban', [ModerationAdminController::class, 'unban'])->name('admin.forum.users.unban');
});
