<?php

declare(strict_types=1);

/*
 * ماژول محتوا — روت‌های web (صفحات CMS عمومی).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 * ترتیب: مسیرهای ایستا پیش از {slug}.
 */

use Modules\Content\Http\Controllers\PageController;
use Tavaan\Core\Facade\Facades\Route;

Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
Route::get('/pages-sitemap.xml', [PageController::class, 'sitemap'])->name('pages.sitemap');
Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');
