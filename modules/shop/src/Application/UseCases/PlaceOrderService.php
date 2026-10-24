<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * PlaceOrderService — ثبت سفارش در تراکنش (DOC-093 §4.2).
 */

namespace Modules\Shop\Application\UseCases;

use Modules\Shop\Domain\Entities\Cart;
use Modules\Shop\Domain\Entities\Order;
use Modules\Shop\Domain\Entities\OrderItem;
use Modules\Shop\Domain\Repositories\CartRepositoryInterface;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Modules\Shop\Domain\Services\CouponService;
use Modules\Shop\Domain\Services\InventoryException;
use Modules\Shop\Domain\Services\InventoryService;
use Modules\Shop\Domain\Services\PricingService;
use Modules\Shop\Domain\ValueObjects\Address;
use Modules\Shop\Domain\ValueObjects\Money;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Core\Facade\Facades\Event;

/**
 * PlaceOrderService — زنجیرهٔ DOC-093 §4.2 در یک تراکنش DB:
 *
 * ```
 * 1. refresh قیمت‌ها از کاتالوگ معتبر (ضد دستکاری unit_price سبد)
 * 2. کوپن → discount
 * 3. مالیات + ارسال (PricingService)
 * 4. Order::place + inventory.reserve (UPDATE اتمیک — race-safe)
 * 5. save + clear cart + consume coupon + dispatch رویدادها
 * ```
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class PlaceOrderService
{
    /**
     * @param array<string, mixed> $config کلید config('shop')
     */
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly InventoryService $inventory,
        private readonly PricingService $pricing,
        private readonly CouponService $coupons,
        private readonly array $config = [],
    ) {
    }

    /**
     * ثبت سفارش از سبد فعال.
     *
     * @param array<string, mixed> $addressData receiver/phone/province/city/address/postal_code
     *
     * @throws ValidationException سبد خالی / آدرس ناقص / موجودی ناکافی
     */
    public function execute(int $customerId, Cart $cart, array $addressData, ?string $paymentMethod = null, ?string $notes = null): Order
    {
        if ($cart->isEmpty()) {
            throw new ValidationException(
                errors: ['cart' => ['سبد خرید خالی است.']],
                messageId: 'shop::messages.cart_empty',
            );
        }

        $address = Address::fromArray($addressData);
        if (!$address->isComplete()) {
            throw new ValidationException(
                errors: ['address' => ['آدرس ارسال ناقص است (گیرنده/تلفن/شهر/آدرس).']],
                messageId: 'shop::messages.address_incomplete',
            );
        }

        $currency = (string) ($this->config['currency'] ?? 'IRR');

        /** @var Order|null $placed */
        try {
            $placed = DB::transaction(function () use ($cart, $customerId, $address, $paymentMethod, $notes, $currency): ?Order {
            // 1) اقلام با قیمت معتبر کاتالوگ (snapshot تازه)
            $items = $this->buildItems($cart, $currency);
            if ($items === []) {
                throw new ValidationException(
                    errors: ['cart' => ['هیچ قلم قابل خریدی در سبد نیست.']],
                    messageId: 'shop::messages.cart_empty',
                );
            }

            // 2) کوپن
            $subtotal = Money::zero($currency);
            foreach ($items as $item) {
                $subtotal = $subtotal->add($item->lineTotal());
            }
            $discount = Money::zero($currency);
            $coupon = null;
            if ($cart->couponCode() !== null) {
                $result = $this->coupons->validate($cart->couponCode(), $subtotal);
                if (isset($result['coupon'], $result['discount'])) {
                    $coupon = $result['coupon'];
                    $discount = $result['discount'];
                }
                // کوپن نامعتبر در checkout → بدون تخفیف ادامه (سبد خطا نمی‌گیرد)
            }

            // 3) ساخت سفارش + مالیات + ارسال
            $order = Order::place(
                orderNumber: $this->orders->nextOrderNumber(),
                customerId: $customerId,
                items: $items,
                shippingAddress: $address,
                paymentMethod: $paymentMethod ?? 'gateway',
                notes: $notes,
            );
            $order->applyDiscount($discount);
            $order->applyShipping($this->pricing->shippingQuote($subtotal->subtract($discount)));
            $order->applyTax($this->pricing->tax($order));

            // 4) موجودی — اتمیک/همه-یا-هیچ (AC-SHOP-03)
            $this->inventory->reserve($order->items(), $order->id());

            // 5) ذخیره + پاک‌سازی سبد + مصرف کوپن
            $this->orders->save($order);
            $this->carts->clear($cart);
            if ($coupon !== null) {
                $this->coupons->consume($coupon);
            }

            return $order;
            }, attempts: 2);
        } catch (InventoryException $e) {
            // موجودی ناکافی → ۴۲۲ قابل‌توضیح (E2E-02)؛ تراکنش rollback شده
            throw new ValidationException(
                errors: ['stock' => ['موجودی «' . (string) ($e->context['title'] ?? '') . '» کافی نیست (درخواست: '
                    . (string) ($e->context['requested'] ?? '?') . '، موجود: ' . (string) ($e->context['available'] ?? '?') . ').']],
                messageId: 'shop::messages.out_of_stock',
                hint: $e->hint,
            );
        }

        if ($placed === null) {
            throw new ValidationException(messageId: 'shop::messages.order_failed');
        }

        // sold_count + رویدادها (خارج از تراکنش — best effort)
        $this->afterPlace($placed);

        return $placed;
    }

    /**
     * ساخت OrderItemها با قیمت/موجودی معتبر کاتالوگ.
     *
     * @return list<OrderItem>
     */
    private function buildItems(Cart $cart, string $currency): array
    {
        $items = [];

        foreach ($cart->items() as $cartItem) {
            $product = $this->products->findById($cartItem->productId());
            if ($product === null || !$product->status()->isPublic() || $product->isTrashed()) {
                continue;   // قلم نامعتبر بی‌صدا حذف می‌شود
            }

            $unitPrice = $this->pricing->unitPrice($product);
            $variantId = null;
            $sku = $product->sku();
            $title = $product->title();

            if ($cartItem->variantId() !== null) {
                $variant = $this->products->findVariant($cartItem->variantId());
                if ($variant === null || $variant->productId() !== $product->id()) {
                    continue;
                }
                $unitPrice = $this->pricing->variantPrice($product, $variant);
                $variantId = $variant->id();
                $sku = $variant->sku() ?? $sku;
                $title = $product->title() . ' — ' . $variant->title();
            }

            $items[] = OrderItem::of(
                productId: $product->id(),
                variantId: $variantId,
                title: $title,
                sku: $sku,
                quantity: $cartItem->quantity(),
                unitPrice: $unitPrice->currency === $currency ? $unitPrice : Money::of($unitPrice->minor, $currency),
            );
        }

        return $items;
    }

    private function afterPlace(Order $order): void
    {
        foreach ($order->items() as $item) {
            $product = $this->products->findById($item->productId);
            if ($product !== null) {
                $product->incrementSold($item->quantity());
                $this->products->save($product);
                $product->pullEvents();
            }
        }

        foreach ($order->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
