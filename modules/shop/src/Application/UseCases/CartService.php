<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * CartService — مدیریت سبد (DOC-093 §4.1).
 */

namespace Modules\Shop\Application\UseCases;

use Modules\Shop\Domain\Entities\Cart;
use Modules\Shop\Domain\Entities\CartItem;
use Modules\Shop\Domain\Entities\Coupon;
use Modules\Shop\Domain\Repositories\CartRepositoryInterface;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Modules\Shop\Domain\Services\CouponService;
use Modules\Shop\Domain\Services\PricingService;
use Modules\Shop\Domain\ValueObjects\Money;
use Tavaan\Core\Exceptions\Validation\ValidationException;
use Tavaan\Core\Facade\Facades\Event;

/**
 * CartService — افزودن/ویرایش/حذف/کوپن با بررسی موجودی و قیمت معتبر.
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class CartService
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly ProductRepositoryInterface $products,
        private readonly CouponService $coupons,
        private readonly PricingService $pricing,
    ) {
    }

    public function current(?int $userId, ?string $sessionId): Cart
    {
        return $this->carts->findOrCreate($userId, $sessionId);
    }

    /** افزودن قلم — DOC-093 §4.1 (بررسی موجودی + قیمت معتبر). */
    public function add(?int $userId, ?string $sessionId, string $productId, int $quantity, ?string $variantId = null): Cart
    {
        $quantity = \max(1, $quantity);

        $product = $this->products->findById($productId);
        if ($product === null || !$product->status()->isPublic() || $product->isTrashed()) {
            throw new ValidationException(
                errors: ['product_id' => ['محصول یافت نشد یا فعال نیست.']],
                messageId: 'shop::messages.product_unavailable',
            );
        }

        $unitPrice = $this->pricing->unitPrice($product);
        if ($variantId !== null && $variantId !== '') {
            $variant = $this->products->findVariant($variantId);
            if ($variant === null || $variant->productId() !== $product->id()) {
                throw new ValidationException(
                    errors: ['variant_id' => ['تنوع انتخاب‌شده معتبر نیست.']],
                    messageId: 'shop::messages.variant_invalid',
                );
            }
            $unitPrice = $this->pricing->variantPrice($product, $variant);

            // موجودی variant
            if ($variant->stock() < $quantity) {
                throw new ValidationException(
                    errors: ['quantity' => ['موجودی این تنوع کافی نیست (موجود: ' . $variant->stock() . ').']],
                    messageId: 'shop::messages.out_of_stock',
                );
            }
        } elseif ($product->isOutOfStock()) {
            throw new ValidationException(
                errors: ['quantity' => ['«' . $product->title() . '» ناموجود است.']],
                messageId: 'shop::messages.out_of_stock',
            );
        }

        $cart = $this->carts->findOrCreate($userId, $sessionId);

        // موجودی کل (قلم فعلی + افزودن جدید)
        $existingQty = 0;
        foreach ($cart->items() as $item) {
            if ($item->productId() === $productId && $item->variantId() === ($variantId !== '' ? $variantId : null)) {
                $existingQty = $item->quantity();
            }
        }
        $stockLimit = $variantId !== null && $variantId !== ''
            ? ($this->products->findVariant($variantId)?->stock() ?? 0)
            : $product->stock();
        if ($existingQty + $quantity > $stockLimit) {
            throw new ValidationException(
                errors: ['quantity' => ['موجودی کافی نیست (حداکثر قابل خرید: ' . \max(0, $stockLimit - $existingQty) . ').']],
                messageId: 'shop::messages.out_of_stock',
            );
        }

        $cart->addItem(CartItem::of($productId, $variantId !== '' ? $variantId : null, $quantity, $unitPrice, $product->title(), $product->slug()));
        $this->carts->save($cart);
        $this->flushEvents($cart);

        return $cart;
    }

    public function updateQuantity(?int $userId, ?string $sessionId, string $itemId, int $quantity): Cart
    {
        $cart = $this->carts->findActive($userId, $sessionId) ?? $this->carts->findOrCreate($userId, $sessionId);

        if (!$cart->updateQuantity($itemId, $quantity)) {
            throw new ValidationException(
                errors: ['item' => ['قلم در سبد یافت نشد.']],
                messageId: 'shop::messages.cart_item_missing',
            );
        }

        $this->carts->save($cart);
        $this->flushEvents($cart);

        return $cart;
    }

    public function remove(?int $userId, ?string $sessionId, string $itemId): Cart
    {
        $cart = $this->carts->findActive($userId, $sessionId) ?? $this->carts->findOrCreate($userId, $sessionId);
        $cart->removeItem($itemId);
        $this->carts->save($cart);
        $this->flushEvents($cart);

        return $cart;
    }

    /**
     * اعمال کوپن — با خلاصهٔ مالی برای نمایش.
     *
     * @return array{cart: Cart, discount: Money, coupon: ?Coupon, error: ?string}
     */
    public function applyCoupon(?int $userId, ?string $sessionId, string $code): array
    {
        $cart = $this->carts->findActive($userId, $sessionId) ?? $this->carts->findOrCreate($userId, $sessionId);
        $subtotal = $cart->subtotal();

        $result = $this->coupons->validate($code, $subtotal);
        if (isset($result['error'])) {
            $cart->applyCoupon(null);
            $this->carts->save($cart);

            return ['cart' => $cart, 'discount' => Money::zero($subtotal->currency), 'coupon' => null, 'error' => (string) $result['error']];
        }

        $cart->applyCoupon($code);
        $this->carts->save($cart);
        $this->flushEvents($cart);

        return ['cart' => $cart, 'discount' => $result['discount'], 'coupon' => $result['coupon'], 'error' => null];
    }

    /** خلاصهٔ مالی سبد (subtotal/discount/tax/shipping/total). @return array<string, mixed> */
    public function summary(Cart $cart): array
    {
        $subtotal = $cart->subtotal();
        $discount = Money::zero($subtotal->currency);

        if ($cart->couponCode() !== null) {
            $result = $this->coupons->validate($cart->couponCode(), $subtotal);
            if (isset($result['discount'])) {
                $discount = $result['discount'];
            }
        }

        $afterDiscount = $subtotal->subtract($discount);
        $shipping = $this->pricing->shippingQuote($afterDiscount);
        $taxBase = $this->pricing->shippingTaxable() ? $afterDiscount->add($shipping) : $afterDiscount;
        $tax = $this->pricing->taxOn($taxBase);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => $afterDiscount->add($shipping)->add($tax),
            'coupon_code' => $cart->couponCode(),
        ];
    }

    private function flushEvents(Cart $cart): void
    {
        foreach ($cart->pullEvents() as $event) {
            if (\method_exists($event, 'eventName') && \method_exists($event, 'toPayload')) {
                Event::dispatch($event->eventName(), $event->toPayload());
            }
        }
    }
}
