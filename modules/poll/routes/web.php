<?php

declare(strict_types=1);

/*
 * ماژول نظرسنجی — روت‌های web (عمومی).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 * throttle: حداکثر ۱۰ رأی در ۱۰ دقیقه برای هر IP (ضد هرزنامه — config poll.throttle).
 */

use Modules\Poll\Http\Controllers\PollController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Security\Middleware\ThrottleMiddleware;

Route::get('/polls', [PollController::class, 'index'])->name('polls.index');
Route::get('/polls/{slug}', [PollController::class, 'show'])->name('polls.show');
Route::post('/polls/{slug}/vote', [PollController::class, 'vote'])
    ->name('polls.vote')
    ->middleware([ThrottleMiddleware::class . ':10,10']);
