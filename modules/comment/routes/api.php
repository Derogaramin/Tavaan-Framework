<?php

declare(strict_types=1);

/*
 * ماژول Comment — روت‌های api (JSON — بدون CSRF به‌معافیت api/*).
 *
 * 🔒 throttle سخت‌تر از وب (۱۰ درخواست در دقیقه برای هر IP).
 */

use Modules\Comment\Http\Controllers\CommentController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Security\Middleware\ThrottleMiddleware;

Route::group([
    'prefix' => '/api/comments',
    'middleware' => [ThrottleMiddleware::class . ':30,1'],
    'where' => ['type' => '[a-z][a-z0-9_]{0,63}', 'id' => '[A-Za-z0-9_\-]{1,64}'],
], static function (): void {
    Route::get('/{type}/{id}', [CommentController::class, 'show'])->name('api.comments.show');
    Route::post('/{type}/{id}', [CommentController::class, 'store'])->name('api.comments.store');
});
