<?php

declare(strict_types=1);

/*
 * ماژول وبلاگ — روت‌های پنل مدیریت (DOC-091 §3: /admin/blog/posts).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate/Policy) در
 * کنترلرها بررسی می‌شوند (DOC-091 §4).
 */

use Modules\Blog\Http\Controllers\Admin\CommentAdminController;
use Modules\Blog\Http\Controllers\Admin\PostAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group(['prefix' => '/admin/blog', 'middleware' => [AuthenticateMiddleware::class]], static function (): void {
    // مقالات
    Route::get('/posts', [PostAdminController::class, 'index'])->name('admin.blog.posts.index');
    Route::get('/posts/create', [PostAdminController::class, 'create'])->name('admin.blog.posts.create');
    Route::post('/posts', [PostAdminController::class, 'store'])->name('admin.blog.posts.store');
    Route::get('/posts/{id}/edit', [PostAdminController::class, 'edit'])->name('admin.blog.posts.edit');
    Route::post('/posts/{id}', [PostAdminController::class, 'update'])->name('admin.blog.posts.update');
    Route::post('/posts/{id}/publish', [PostAdminController::class, 'publish'])->name('admin.blog.posts.publish');
    Route::post('/posts/{id}/archive', [PostAdminController::class, 'archive'])->name('admin.blog.posts.archive');
    Route::delete('/posts/{id}', [PostAdminController::class, 'destroy'])->name('admin.blog.posts.destroy');

    // نظرات (moderation)
    Route::get('/comments', [CommentAdminController::class, 'index'])->name('admin.blog.comments.index');
    Route::post('/comments/{id}/approve', [CommentAdminController::class, 'approve'])->name('admin.blog.comments.approve');
    Route::post('/comments/{id}/spam', [CommentAdminController::class, 'spam'])->name('admin.blog.comments.spam');
    Route::delete('/comments/{id}', [CommentAdminController::class, 'destroy'])->name('admin.blog.comments.destroy');
});
