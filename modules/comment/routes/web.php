<?php

declare(strict_types=1);

/*
 * ماژول Comment — روت‌های web (نظرات standalone — پلی‌مورفیک).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 * {type} از allowlist رجیستری عبور می‌کند (SubmitCommentService).
 * throttle: حداکثر ۵ نظر در ۱۰ دقیقه برای هر IP (ضد هرزنامه).
 */

use Modules\Comment\Http\Controllers\CommentController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Security\Middleware\ThrottleMiddleware;

Route::get('/comments/{type}/{id}', [CommentController::class, 'show'])
    ->name('comments.show')
    ->whereAll(['type' => '[a-z][a-z0-9_]{0,63}', 'id' => '[A-Za-z0-9_\-]{1,64}']);

Route::post('/comments/{type}/{id}', [CommentController::class, 'store'])
    ->name('comments.store')
    ->middleware([ThrottleMiddleware::class . ':5,10'])
    ->whereAll(['type' => '[a-z][a-z0-9_]{0,63}', 'id' => '[A-Za-z0-9_\-]{1,64}']);
