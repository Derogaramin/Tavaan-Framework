<?php

declare(strict_types=1);

/*
 * ماژول خبرگزاری — روت‌های پنل مدیریت (DOC-092 §3-4).
 *
 * middleware: احراز هویت (session guard) — مجوزهای ریز (Gate/Policy) در
 * کنترلرها بررسی می‌شوند.
 */

use Modules\News\Http\Controllers\Admin\ArticleAdminController;
use Modules\News\Http\Controllers\Admin\CategoryAdminController;
use Modules\News\Http\Controllers\Admin\CommentAdminController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;

Route::group(['prefix' => '/admin/news', 'middleware' => [AuthenticateMiddleware::class]], static function (): void {
    // اخبار + workflow
    Route::get('/articles', [ArticleAdminController::class, 'index'])->name('admin.news.articles.index');
    Route::get('/articles/create', [ArticleAdminController::class, 'create'])->name('admin.news.articles.create');
    Route::post('/articles', [ArticleAdminController::class, 'store'])->name('admin.news.articles.store');
    Route::get('/articles/{id}/edit', [ArticleAdminController::class, 'edit'])->name('admin.news.articles.edit');
    Route::post('/articles/{id}', [ArticleAdminController::class, 'update'])->name('admin.news.articles.update');
    Route::post('/articles/{id}/submit', [ArticleAdminController::class, 'submit'])->name('admin.news.articles.submit');
    Route::post('/articles/{id}/approve', [ArticleAdminController::class, 'approve'])->name('admin.news.articles.approve');
    Route::post('/articles/{id}/reject', [ArticleAdminController::class, 'reject'])->name('admin.news.articles.reject');
    Route::post('/articles/{id}/publish', [ArticleAdminController::class, 'publish'])->name('admin.news.articles.publish');
    Route::post('/articles/{id}/breaking', [ArticleAdminController::class, 'breaking'])->name('admin.news.articles.breaking');
    Route::post('/articles/{id}/archive', [ArticleAdminController::class, 'archive'])->name('admin.news.articles.archive');
    Route::delete('/articles/{id}', [ArticleAdminController::class, 'destroy'])->name('admin.news.articles.destroy');

    // دسته‌ها (chief-editor)
    Route::get('/categories', [CategoryAdminController::class, 'index'])->name('admin.news.categories.index');
    Route::post('/categories', [CategoryAdminController::class, 'store'])->name('admin.news.categories.store');
    Route::post('/categories/{id}', [CategoryAdminController::class, 'update'])->name('admin.news.categories.update');
    Route::delete('/categories/{id}', [CategoryAdminController::class, 'destroy'])->name('admin.news.categories.destroy');

    // نظرات (moderation)
    Route::get('/comments', [CommentAdminController::class, 'index'])->name('admin.news.comments.index');
    Route::post('/comments/{id}/approve', [CommentAdminController::class, 'approve'])->name('admin.news.comments.approve');
    Route::post('/comments/{id}/spam', [CommentAdminController::class, 'spam'])->name('admin.news.comments.spam');
    Route::delete('/comments/{id}', [CommentAdminController::class, 'destroy'])->name('admin.news.comments.destroy');
});
