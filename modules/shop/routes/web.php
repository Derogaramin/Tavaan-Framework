<?php

declare(strict_types=1);

/*
 * ماژول فروشگاه — مسیرهای وب ویترین (DOC-093 §7).
 * نام‌گذاری با prefix «shop.» (DoD §5).
 */

use Modules\Shop\Http\Controllers\CartController;
use Modules\Shop\Http\Controllers\CheckoutController;
use Modules\Shop\Http\Controllers\MyOrdersController;
use Modules\Shop\Http\Controllers\ShopController;
use Tavaan\Core\Facade\Facades\Route;

// ویترین
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/search', [ShopController::class, 'search'])->name('shop.search');
Route::get('/shop/categories/{slug}', [ShopController::class, 'category'])->name('shop.category');
Route::get('/shop/products/{slug}', [ShopController::class, 'show'])->name('shop.product.show');
Route::post('/shop/products/{slug}/reviews', [ShopController::class, 'submitReview'])->name('shop.product.review');

// سبد خرید (مهمان + کاربر)
Route::get('/shop/cart', [CartController::class, 'show'])->name('shop.cart');
Route::post('/shop/cart/items', [CartController::class, 'add'])->name('shop.cart.add');
Route::put('/shop/cart/items/{id}', [CartController::class, 'update'])->name('shop.cart.update');
Route::delete('/shop/cart/items/{id}', [CartController::class, 'remove'])->name('shop.cart.remove');
Route::post('/shop/cart/coupon', [CartController::class, 'coupon'])->name('shop.cart.coupon');

// checkout + پرداخت (DOC-093 §4.2/§4.3)
Route::get('/shop/checkout', [CheckoutController::class, 'show'])->name('shop.checkout');
Route::post('/shop/checkout', [CheckoutController::class, 'place'])->name('shop.checkout.place');
// 🔒 ترتیب ثبت: مسیرهای قطعی (sandbox/callback) پیش از الگوی {orderNumber}
Route::get('/shop/pay/sandbox/{authority}', [CheckoutController::class, 'sandboxPage'])->name('shop.pay.sandbox');
Route::get('/shop/pay/callback', [CheckoutController::class, 'callback'])->name('shop.pay.callback');
Route::get('/shop/pay/{orderNumber}', [CheckoutController::class, 'pay'])->name('shop.pay');

// سفارش‌های من
Route::get('/shop/orders', [MyOrdersController::class, 'index'])->name('shop.orders');
Route::get('/shop/orders/{number}', [MyOrdersController::class, 'show'])->name('shop.order.show');
Route::post('/shop/orders/{number}/cancel', [MyOrdersController::class, 'cancel'])->name('shop.order.cancel');
