<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CartController — سبد خرید (DOC-093 §4.1/§7).
 */

namespace Modules\Shop\Http\Controllers;

use Modules\Shop\Application\UseCases\CartService;
use Modules\Shop\Domain\Entities\Cart;
use Modules\Shop\Domain\Repositories\ShopCategoryRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * @api 1.0
 * @see modules/shop/README.md
 */
final class CartController
{
    public function __construct(
        private readonly CartService $carts,
        private readonly ShopCategoryRepositoryInterface $categories,
    ) {
    }

    /** نمایش سبد — GET /shop/cart */
    public function show(): mixed
    {
        [$userId, $sessionId] = $this->identity();
        $cart = $this->carts->current($userId, $sessionId);

        return View::render('shop::front.cart', [
            'cart' => $cart,
            'summary' => $this->carts->summary($cart),
            'categories' => $this->categories->all(),
        ]);
    }

    /** افزودن قلم — POST /shop/cart/items */
    public function add(RequestInterface $request): mixed
    {
        [$userId, $sessionId] = $this->identity();

        try {
            $cart = $this->carts->add(
                $userId,
                $sessionId,
                productId: (string) $request->input('product_id', ''),
                quantity: (int) $request->input('quantity', 1),
                variantId: $request->input('variant_id') !== null ? (string) $request->input('variant_id') : null,
            );
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        if ($request->expectsJson()) {
            return $this->jsonCart($cart);
        }

        Session::flash('status', 'added_to_cart');



        return redirect('/shop/cart');
    }

    /** ویرایش تعداد — PUT /shop/cart/items/{id} */
    public function update(RequestInterface $request, string $id): mixed
    {
        [$userId, $sessionId] = $this->identity();

        try {
            $cart = $this->carts->updateQuantity($userId, $sessionId, $id, (int) $request->input('quantity', 1));
        } catch (ValidationException $e) {
            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return back();
        }

        if ($request->expectsJson()) {
            return $this->jsonCart($cart);
        }

        Session::flash('status', 'cart_updated');



        return redirect('/shop/cart');
    }

    /** حذف قلم — DELETE /shop/cart/items/{id} */
    public function remove(RequestInterface $request, string $id): mixed
    {
        [$userId, $sessionId] = $this->identity();
        $cart = $this->carts->remove($userId, $sessionId, $id);

        if ($request->expectsJson()) {
            return $this->jsonCart($cart);
        }

        Session::flash('status', 'cart_item_removed');



        return redirect('/shop/cart');
    }

    /** کوپن تخفیف — POST /shop/cart/coupon (E2E-05) */
    public function coupon(RequestInterface $request): mixed
    {
        [$userId, $sessionId] = $this->identity();
        $result = $this->carts->applyCoupon($userId, $sessionId, (string) $request->input('coupon_code', ''));

        if ($request->expectsJson()) {
            return [
                'error' => $result['error'],
                'discount' => $result['discount']->minor,
                'summary' => $this->summaryArray($result['cart']),
            ];
        }

        if ($result['error'] !== null) {
            Session::flash('error', \trans('shop::messages.' . $result['error']));

            return redirect('/shop/cart');
        }

        Session::flash('status', 'coupon_applied');

        return redirect('/shop/cart');
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    /** @return array{0: int|null, 1: string|null} */
    private function identity(): array
    {
        $user = Auth::user();
        $userId = $user !== null ? (int) $user->getAuthIdentifier() : null;

        $sessionId = null;
        try {
            $id = Session::id();
            if (\is_string($id) && $id !== '') {
                $sessionId = $id;
            }
        } catch (\Throwable) {
            $sessionId = null;
        }

        if ($userId === null && $sessionId === null) {
            // مهمان بدون session — شناسهٔ موقت deterministic برای پایداری سبد
            $sessionId = 'guest-anon';
        }

        return [$userId, $sessionId];
    }

    /** @return array<string, mixed> */
    private function jsonCart(Cart $cart): array
    {
        return [
            'cart' => [
                'id' => $cart->id(),
                'items' => \array_map(static fn ($item): array => [
                    'id' => $item->id(),
                    'product_id' => $item->productId(),
                    'variant_id' => $item->variantId(),
                    'title' => $item->title(),
                    'slug' => $item->slug(),
                    'quantity' => $item->quantity(),
                    'unit_price' => $item->unitPrice()->minor,
                    'line_total' => $item->lineTotal()->minor,
                ], $cart->items()),
                'coupon_code' => $cart->couponCode(),
                'count' => $cart->count(),
            ],
            'summary' => $this->summaryArray($cart),
        ];
    }

    /** @return array<string, int|string|null> */
    private function summaryArray(Cart $cart): array
    {
        $summary = $this->carts->summary($cart);

        return [
            'subtotal' => $summary['subtotal']->minor,
            'discount' => $summary['discount']->minor,
            'shipping' => $summary['shipping']->minor,
            'tax' => $summary['tax']->minor,
            'total' => $summary['total']->minor,
            'currency' => $summary['subtotal']->currency,
        ];
    }
}
