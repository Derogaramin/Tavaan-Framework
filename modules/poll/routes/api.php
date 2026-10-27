<?php

declare(strict_types=1);

/*
 * ماژول نظرسنجی — روت‌های api (JSON).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 * کنترلر با expectsJson پاسخ JSON می‌دهد؛ روت وب POST /polls/{slug}/vote هم
 * برای کلاینت‌های JSON کار می‌کند (هم‌راستا با ماژول comment).
 */

use Modules\Poll\Http\Controllers\PollController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Security\Middleware\ThrottleMiddleware;

Route::get('/api/polls/{slug}', [PollController::class, 'apiShow'])->name('api.polls.show');
Route::post('/api/polls/{slug}/vote', [PollController::class, 'vote'])
    ->name('api.polls.vote')
    ->middleware([ThrottleMiddleware::class . ':10,10']);
