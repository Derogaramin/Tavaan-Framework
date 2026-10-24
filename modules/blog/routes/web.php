<?php

declare(strict_types=1);

/*
 * ماژول وبلاگ — روت‌های عمومی وب (DOC-091 §3).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 * ترتیب: مسیرهای ایستا (feed/sitemap) پیش از {slug}.
 */

use Modules\Blog\Http\Controllers\BlogController;
use Modules\Blog\Http\Controllers\CommentController;
use Modules\Blog\Http\Controllers\FeedController;
use Modules\Blog\Http\Controllers\SitemapController;
use Tavaan\Core\Facade\Facades\Route;

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/feed', FeedController::class)->name('blog.feed');
Route::get('/blog/sitemap.xml', SitemapController::class)->name('blog.sitemap');
Route::get('/blog/category/{slug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/tag/{slug}', [BlogController::class, 'tag'])->name('blog.tag');
Route::get('/blog/archive/{year}/{month}', [BlogController::class, 'archive'])->name('blog.archive');

// ثبت نظر — throttle: حداکثر ۵ نظر در ۱۰ دقیقه برای هر IP (ضد هرزنامه)
Route::post('/blog/{slug}/comments', [CommentController::class, 'store'])
    ->name('blog.comments.store')
    ->middleware([\Tavaan\Engine\Security\Middleware\ThrottleMiddleware::class . ':5,10']);

Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
