<?php

declare(strict_types=1);

/*
 * ماژول فروشگاه — API (DOC-093 §7).
 */

use Modules\Shop\Http\Controllers\Api\ApiShopController;
use Tavaan\Core\Facade\Facades\Route;

Route::group(['prefix' => '/api/v1/shop'], static function (): void {
    Route::get('/products', [ApiShopController::class, 'products'])->name('api.shop.products');
    Route::get('/products/{slug}', [ApiShopController::class, 'product'])->name('api.shop.product');
    Route::get('/categories', [ApiShopController::class, 'categories'])->name('api.shop.categories');

    Route::get('/cart', [ApiShopController::class, 'cart'])->name('api.shop.cart');
    Route::post('/cart/items', [ApiShopController::class, 'addToCart'])->name('api.shop.cart.add');
    Route::put('/cart/items/{id}', [ApiShopController::class, 'updateCartItem'])->name('api.shop.cart.update');
    Route::delete('/cart/items/{id}', [ApiShopController::class, 'removeCartItem'])->name('api.shop.cart.remove');

    Route::post('/checkout', [ApiShopController::class, 'checkout'])->name('api.shop.checkout');
    Route::get('/orders', [ApiShopController::class, 'orders'])->name('api.shop.orders');
    Route::get('/orders/{id}', [ApiShopController::class, 'order'])->name('api.shop.order');
    Route::post('/orders/{id}/cancel', [ApiShopController::class, 'cancelOrder'])->name('api.shop.order.cancel');
});
