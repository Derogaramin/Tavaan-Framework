<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — روت‌های وب
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق).
 */

use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

Route::view('/', 'welcome')->name('home');

Route::get('/hello/{name}', static function (string $name): string {
    return view('hello', ['name' => $name])->render();
})->name('hello')->where('name', '[\\p{L}\\p{N}\\-_]+');   // slug یونیکد (فارسی مجاز)

Route::get('/time', static function (): Response {
    return Response::json([
        'jalali' => jalali(null, 'Y/m/d H:i:s l'),
        'gregorian' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
    ]);
})->name('time');

Route::prefix('demo')->name('demo.')->group(static function (): void {
    Route::get('/counter', static function (Request $request): Response {
        $session = $request->session();
        $count = $session !== null ? $session->increment('visits') : -1;

        return Response::json(['visits' => $count, 'session_id' => $session?->id()]);
    })->name('counter');
});

Route::get('/xss-test', static fn (): string => view('xss-test', ['input' => '<script>alert(1)</script>'])->render());

Route::fallback(static fn (): Response => new Response(
    (string) view('errors/404', ['path' => $_SERVER['REQUEST_URI'] ?? ''])->render(),
    404,
));
