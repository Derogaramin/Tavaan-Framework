<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — روت‌های API (نمونهٔ M6)
 *
 * این فایل با prefix «api» و group «api» بارگذاری می‌شود
 * (config/router.files). نگارش‌گذاری URL-based: /api/v1/…
 *
 * 🔒 این فایل فقط روت تعریف می‌کند (بدون منطق/کلاس).
 */

use App\Http\Resources\DemoItemResource;
use Tavaan\Core\Facade\Facades\Route;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

Route::prefix('v1')->name('api.v1.')->group(static function (): void {
    // فهرست آیتم‌ها — throttle:api + جمع‌آوری Resource
    Route::get('/items', static function (): mixed {
        $items = [
            ['id' => 1, 'title' => 'آیتم یک'],
            ['id' => 2, 'title' => 'آیتم دو'],
        ];

        return DemoItemResource::collection($items);
    })->name('items.index')->middleware('throttle:api');

    // جزئیات یک آیتم
    Route::get('/items/{item}', static function (string $item): mixed {
        if (\ctype_digit($item) === false) {
            return Response::json(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404], 404);
        }

        return DemoItemResource::make(['id' => (int) $item, 'title' => 'آیتم ' . $item]);
    })->name('items.show')->whereNumber('item');

    // ایجاد آیتم — idempotent + throttle
    Route::post('/items', static function (Request $request): Response {
        return Response::json([
            'data' => ['id' => \random_int(100, 999), 'title' => (string) $request->input('title', 'بی‌نام')],
        ], 201);
    })->name('items.store')->middleware(['throttle:api', 'idempotent']);
});
