<?php

declare(strict_types=1);

/*
 * ماژول فروشگاه — مسیرهای پنل مدیریت (DOC-093 §5).
 */

use Modules\Shop\Http\Controllers\Admin\CategoryAdminController;
use Modules\Shop\Http\Controllers\Admin\CommentAdminController;
use Modules\Shop\Http\Controllers\Admin\CouponAdminController;
use Modules\Shop\Http\Controllers\Admin\OrderAdminController;
use Modules\Shop\Http\Controllers\Admin\ProductAdminController;
use Tavaan\Core\Facade\Facades\Route;

Route::group(['prefix' => '/admin/shop'], static function (): void {
    // داشبورد/گزارش فروش
    Route::get('/orders/sales.csv', [OrderAdminController::class, 'salesCsv'])->name('admin.shop.sales.csv');

    // محصولات
    Route::get('/products', [ProductAdminController::class, 'index'])->name('admin.shop.products');
    Route::get('/products/create', [ProductAdminController::class, 'create'])->name('admin.shop.products.create');
    Route::post('/products', [ProductAdminController::class, 'store'])->name('admin.shop.products.store');
    Route::get('/products/{id}/edit', [ProductAdminController::class, 'edit'])->name('admin.shop.products.edit');
    Route::put('/products/{id}', [ProductAdminController::class, 'update'])->name('admin.shop.products.update');
    Route::delete('/products/{id}', [ProductAdminController::class, 'destroy'])->name('admin.shop.products.destroy');
    Route::post('/products/{id}/media', [ProductAdminController::class, 'uploadMedia'])->name('admin.shop.products.media');
    Route::post('/products/{id}/variants', [ProductAdminController::class, 'addVariant'])->name('admin.shop.products.variants.add');
    Route::delete('/products/{id}/variants/{variantId}', [ProductAdminController::class, 'removeVariant'])->name('admin.shop.products.variants.destroy');
    Route::post('/products/{id}/inventory', [ProductAdminController::class, 'adjustInventory'])->name('admin.shop.products.inventory');

    // دسته‌ها
    Route::get('/categories', [CategoryAdminController::class, 'index'])->name('admin.shop.categories');
    Route::post('/categories', [CategoryAdminController::class, 'store'])->name('admin.shop.categories.store');
    Route::delete('/categories/{id}', [CategoryAdminController::class, 'destroy'])->name('admin.shop.categories.destroy');

    // سفارش‌ها
    Route::get('/orders', [OrderAdminController::class, 'index'])->name('admin.shop.orders');
    Route::get('/orders/{id}', [OrderAdminController::class, 'show'])->name('admin.shop.orders.show');
    Route::post('/orders/{id}/status', [OrderAdminController::class, 'updateStatus'])->name('admin.shop.orders.status');
    Route::post('/orders/{id}/refund', [OrderAdminController::class, 'refund'])->name('admin.shop.orders.refund');
    Route::delete('/orders/{id}', [OrderAdminController::class, 'destroy'])->name('admin.shop.orders.destroy');

    // کوپن‌ها
    Route::get('/coupons', [CouponAdminController::class, 'index'])->name('admin.shop.coupons');
    Route::post('/coupons', [CouponAdminController::class, 'store'])->name('admin.shop.coupons.store');
    Route::post('/coupons/{id}/deactivate', [CouponAdminController::class, 'deactivate'])->name('admin.shop.coupons.deactivate');
    Route::delete('/coupons/{id}', [CouponAdminController::class, 'destroy'])->name('admin.shop.coupons.destroy');

    // نقد و بررسی‌ها
    Route::get('/comments', [CommentAdminController::class, 'index'])->name('admin.shop.comments');
    Route::post('/comments/{id}/approve', [CommentAdminController::class, 'approve'])->name('admin.shop.comments.approve');
    Route::post('/comments/{id}/spam', [CommentAdminController::class, 'spam'])->name('admin.shop.comments.spam');
    Route::delete('/comments/{id}', [CommentAdminController::class, 'destroy'])->name('admin.shop.comments.destroy');
});
