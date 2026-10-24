<?php

declare(strict_types=1);

/*
 * ماژول خبرگزاری — روت‌های عمومی وب (DOC-092).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 * ترتیب: مسیرهای ایستا (feed/sitemap/breaking) پیش از {slug}.
 */

use Modules\News\Http\Controllers\AmpController;
use Modules\News\Http\Controllers\CommentController;
use Modules\News\Http\Controllers\FeedController;
use Modules\News\Http\Controllers\NewsController;
use Modules\News\Http\Controllers\SitemapController;
use Tavaan\Core\Facade\Facades\Route;

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/breaking', [NewsController::class, 'breaking'])->name('news.breaking');
Route::get('/news/feed', FeedController::class)->name('news.feed');
Route::get('/news/sitemap.xml', SitemapController::class)->name('news.sitemap');
Route::get('/news/category/{slug}', [NewsController::class, 'category'])->name('news.category');
Route::get('/news/tag/{slug}', [NewsController::class, 'tag'])->name('news.tag');
Route::get('/news/archive/{year}/{month}', [NewsController::class, 'archive'])->name('news.archive');

// ثبت نظر — throttle: حداکثر ۵ نظر در ۱۰ دقیقه برای هر IP
Route::post('/news/{slug}/comments', [CommentController::class, 'store'])
    ->name('news.comments.store')
    ->middleware([\Tavaan\Engine\Security\Middleware\ThrottleMiddleware::class . ':5,10']);

Route::get('/news/{slug}/amp', AmpController::class)->name('news.amp');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');
