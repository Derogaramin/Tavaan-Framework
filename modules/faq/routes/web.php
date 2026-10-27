<?php

declare(strict_types=1);

/*
 * ماژول پرسش‌های متداول — روت‌های web (عمومی).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 * throttle: حداکثر ۱۰ رأی «مفید بود» در ۱۰ دقیقه برای هر IP (ضد هرزنامه).
 */

use Modules\Faq\Http\Controllers\FaqController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Security\Middleware\ThrottleMiddleware;

Route::group([
    'prefix' => '/faq',
    'where' => ['id' => '[0-9A-Za-z]{26}'],
], static function (): void {
    Route::get('', [FaqController::class, 'index'])->name('faq.index');
    Route::post('/{id}/helpful', [FaqController::class, 'helpful'])
        ->name('faq.helpful')
        ->middleware([ThrottleMiddleware::class . ':10,10']);
});
