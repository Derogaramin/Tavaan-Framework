<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * PricingService — محاسبهٔ مالی سفارش (DOC-093 §4.2 گام ۲).
 */

namespace Modules\Shop\Domain\Services;

use Modules\Shop\Domain\Entities\Cart;
use Modules\Shop\Domain\Entities\Order;
use Modules\Shop\Domain\Entities\Product;
use Modules\Shop\Domain\Entities\ProductVariant;
use Modules\Shop\Domain\ValueObjects\Money;

/**
 * PricingService — دامنهٔ خالص: قیمت واحد، مالیات، ارسال.
 *
 * نرخ‌ها از config تزریق می‌شوند (tax.percent، shipping.flat/
 * free_threshold) — آداپترهای TaxGateway/ShippingGateway در Infrastructure
 * همین منطق را پشت پورت می‌برند.
 *
 * @api 1.0
 * @see modules/shop/README.md
 */
final class PricingService
{
    /**
     * @param array{tax?: array{percent?: float, shipping_taxable?: bool}, shipping?: array{flat?: int, free_threshold?: int|null}} $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /** قیمت مؤثر محصول (sale < price). */
    public function unitPrice(Product $product): Money
    {
        return $product->effectivePrice();
    }

    /** قیمت مؤثر variant = قیمت پایهٔ محصول + delta. */
    public function variantPrice(Product $product, ProductVariant $variant): Money
    {
        return $variant->priceFor($product->effectivePrice());
    }

    /** نرخ مالیات (درصد). */
    public function taxRate(): float
    {
        return (float) ($this->config['tax']['percent'] ?? 0.0);
    }

    /** آیا ارسال هم مشمول مالیات است؟ */
    public function shippingTaxable(): bool
    {
        return (bool) ($this->config['tax']['shipping_taxable'] ?? false);
    }

    /** مالیات بر یک پایهٔ مبلغ‌دار (گرد به پایین). */
    public function taxOn(Money $taxableBase): Money
    {
        $percent = $this->taxRate();
        if ($percent <= 0.0) {
            return Money::zero($taxableBase->currency);
        }

        return $taxableBase->percent($percent);
    }

    /** مالیات سفارش بر (subtotal - discount) [+ shipping اگر taxable] — DOC-093 §4.2. */
    public function tax(Order $order): Money
    {
        $taxable = $order->subtotal()->subtract($order->discount());
        $tax = $this->taxOn($taxable);

        if ($this->shippingTaxable()) {
            $tax = $tax->add($this->taxOn($order->shippingCost()));
        }

        return $tax;
    }

    /** هزینهٔ ارسال: flat با آستانهٔ ارسال رایگان. */
    public function shippingQuote(Money $subtotalAfterDiscount): Money
    {
        $currency = $subtotalAfterDiscount->currency;
        $flat = (int) ($this->config['shipping']['flat'] ?? 0);
        $threshold = $this->config['shipping']['free_threshold'] ?? null;

        if ($threshold !== null && (int) $threshold > 0 && $subtotalAfterDiscount->minor >= (int) $threshold) {
            return Money::zero($currency);
        }

        return Money::of(\max(0, $flat), $currency);
    }

    /** جمع سبد پس از تخفیف (پایهٔ محاسبهٔ مالیات/ارسال). */
    public function subtotalAfterDiscount(Cart $cart, Money $discount): Money
    {
        $subtotal = $cart->subtotal();

        return $subtotal->subtract($discount->min($subtotal));
    }
}
