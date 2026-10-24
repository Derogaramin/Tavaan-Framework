<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CheckoutController — ثبت سفارش + پرداخت + بازگشت (DOC-093 §4.2/§4.3).
 */

namespace Modules\Shop\Http\Controllers;

use Modules\Shop\Application\UseCases\CartService;
use Modules\Shop\Application\UseCases\CheckoutService;
use Modules\Shop\Application\UseCases\PlaceOrderService;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Tavaan\Core\Contracts\Http\RequestInterface;
use Tavaan\Core\Exceptions\Http\ForbiddenException;
use Tavaan\Core\Exceptions\Http\RouteNotFoundException;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Auth;
use Tavaan\Core\Facade\Facades\Session;
use Tavaan\Core\Facade\Facades\View;

/**
 * CheckoutController:
 *
 * 1. GET  /shop/checkout            — فرم آدرس + خلاصهٔ سبد (auth)
 * 2. POST /shop/checkout            — PlaceOrder → redirect به /shop/pay/{number}
 * 3. GET  /shop/pay/{number}        — startPayment → sandbox: صفحهٔ شبیه‌ساز، engine: درگاه
 * 4. GET  /shop/pay/sandbox/{auth}  — صفحهٔ شبیه‌ساز (موفق/ناموفق)
 * 5. GET  /shop/pay/callback        — handleReturn (verify + markPaid) → صفحهٔ نتیجه
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class CheckoutController
{
    public function __construct(
        private readonly CartService $carts,
        private readonly PlaceOrderService $placeOrder,
        private readonly CheckoutService $checkout,
        private readonly OrderRepositoryInterface $orders,
    ) {
    }

    /** فرم checkout (لازم: ورود). */
    public function show(RequestInterface $request): mixed
    {
        [$userId, $sessionId] = $this->identity();
        $cart = $this->carts->current($userId, $sessionId);

        if ($cart->isEmpty()) {
            Session::flash('error', 'cart_empty');


            return redirect('/shop/cart');
        }

        return View::render('shop::front.checkout', [
            'cart' => $cart,
            'summary' => $this->carts->summary($cart),
            'user' => Auth::user(),
        ]);
    }

    /** ثبت سفارش — POST /shop/checkout (E2E-01). */
    public function place(RequestInterface $request): mixed
    {
        [$userId, $sessionId] = $this->identity();
        if ($userId === null) {
            throw new ForbiddenException(messageId: 'shop::messages.login_required');
        }

        $cart = $this->carts->current($userId, $sessionId);

        try {
            $order = $this->placeOrder->execute(
                customerId: $userId,
                cart: $cart,
                addressData: [
                    'receiver' => (string) $request->input('receiver', ''),
                    'phone' => (string) $request->input('phone', ''),
                    'province' => (string) $request->input('province', ''),
                    'city' => (string) $request->input('city', ''),
                    'address' => (string) $request->input('address', ''),
                    'postal_code' => (string) $request->input('postal_code', ''),
                ],
                paymentMethod: (string) $request->input('payment_method', 'gateway'),
                notes: $request->input('notes') !== null ? (string) $request->input('notes') : null,
            );
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());


            Session::flash('errors', $e->errors);
            Session::flash('error', $e->getMessage());

            return redirect('/shop/checkout');
        }

        if ($request->expectsJson()) {
            return [
                'order_id' => $order->id(),
                'order_number' => $order->orderNumber(),
                'status' => $order->status()->value,
                'total' => $order->total()->minor,
                'pay_url' => '/shop/pay/' . $order->orderNumber(),
            ];
        }

        return redirect('/shop/pay/' . $order->orderNumber());
    }

    /** شروع پرداخت — GET /shop/pay/{orderNumber}. */
    public function pay(RequestInterface $request, string $orderNumber): mixed
    {
        $order = $this->orders->findByNumber($orderNumber);
        if ($order === null) {
            throw new RouteNotFoundException(messageId: 'shop::messages.order_not_found');
        }

        $user = Auth::user();
        if ($user === null || (int) $user->getAuthIdentifier() !== $order->customerId()) {
            throw new ForbiddenException(messageId: 'shop::messages.order_forbidden');
        }

        if ($order->isPaid() || $order->status()->isFinal()) {
            return redirect('/shop/orders/' . $order->orderNumber());
        }

        $callbackUrl = $this->absoluteUrl('/shop/pay/callback');
        $mobile = (string) $request->input('mobile', $order->shippingAddress()?->phone ?? '');

        try {
            $result = $this->checkout->startPayment(
                $order,
                $callbackUrl,
                mobile: $mobile !== '' ? $mobile : null,
                email: null,
                simulateFailure: $request->input('simulate_failure') !== null,
            );
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());


            return redirect('/shop/orders/' . $order->orderNumber());
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());


            return redirect('/shop/orders/' . $order->orderNumber());
        }

        $redirectUrl = (string) $result['redirect_url'];
        if ($redirectUrl === '') {
            Session::flash('error', 'payment_unavailable');


            return redirect('/shop/orders/' . $order->orderNumber());
        }

        return redirect($redirectUrl);
    }

    /** صفحهٔ شبیه‌ساز sandbox — GET /shop/pay/sandbox/{authority}. */
    public function sandboxPage(RequestInterface $request, string $authority): mixed
    {
        $order = $this->orders->findByAuthority($authority);

        return View::render('shop::front.pay-sandbox', [
            'authority' => $authority,
            'order' => $order,
        ]);
    }

    /** بازگشت از درگاه — GET /shop/pay/callback (E2E-01 موفق / E2E-03 ناموفق). */
    public function callback(RequestInterface $request): mixed
    {
        $authority = (string) $request->input('authority', '');
        if ($authority === '') {
            throw new RouteNotFoundException(messageId: 'shop::messages.order_not_found');
        }

        try {
            $order = $this->checkout->handleReturn($authority, [
                'status' => (string) $request->input('status', ''),
            ]);
        } catch (ValidationException $e) {
            // پرداخت ناموفق → سفارش pending؛ کاربر می‌تواند retry کند (E2E-03)
            $byAuth = $this->orders->findByAuthority($authority);
            $number = $byAuth?->orderNumber() ?? '';

            return View::render('shop::front.payment-result', [
                'success' => false,
                'order' => $byAuth,
                'orderNumber' => $number,
                'message' => $e->getMessage(),
            ], 200);
        }

        return View::render('shop::front.payment-result', [
            'success' => true,
            'order' => $order,
            'orderNumber' => $order->orderNumber(),
            'message' => '',
        ]);
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
            $sessionId = 'guest-anon';
        }

        return [$userId, $sessionId];
    }

    private function absoluteUrl(string $path): string
    {
        $base = \rtrim((string) \config('app.url', ''), '/');

        return $base . $path;
    }
}
