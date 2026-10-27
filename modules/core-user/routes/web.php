<?php

declare(strict_types=1);

/*
 * ماژول کاربران پایه — روت‌های وب (احراز هویت + پروفایل).
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 * throttle ورود از config خوانده می‌شود (پیش‌فرض ۵ تلاش در ۱۰ دقیقه).
 */

use Modules\CoreUser\Http\Controllers\AuthController;
use Modules\CoreUser\Http\Controllers\ProfileController;
use Modules\CoreUser\Http\Controllers\RegisterController;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware;
use Tavaan\Engine\Security\Middleware\ThrottleMiddleware;

$loginThrottle = (int) config('core-user.login.throttle.0', 5) . ',' . (int) config('core-user.login.throttle.1', 10);

// ورود/خروج
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt')
    ->middleware([ThrottleMiddleware::class . ':' . $loginThrottle]);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ثبت‌نام (config: core-user.registration.enabled)
Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register.attempt')
    ->middleware([ThrottleMiddleware::class . ':10,10']);

// پروفایل کاربر جاری
Route::group(['prefix' => '/profile', 'middleware' => [AuthenticateMiddleware::class]], static function (): void {
    Route::get('', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
