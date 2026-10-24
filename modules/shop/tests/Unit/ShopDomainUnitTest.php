<?php

declare(strict_types=1);

namespace Modules\Shop\Tests\Unit;

use Modules\Shop\Domain\Entities\Cart;
use Modules\Shop\Domain\Entities\CartItem;
use Modules\Shop\Domain\Entities\Coupon;
use Modules\Shop\Domain\Entities\Order;
use Modules\Shop\Domain\Entities\OrderItem;
use Modules\Shop\Domain\Entities\Product;
use Modules\Shop\Domain\Entities\ProductVariant;
use Modules\Shop\Domain\Enums\OrderStatus;
use Modules\Shop\Domain\Enums\ProductStatus;
use Modules\Shop\Domain\Events\OrderWasPaid;
use Modules\Shop\Domain\Events\OrderWasPlaced;
use Modules\Shop\Domain\Services\PricingService;
use Modules\Shop\Domain\ValueObjects\Address;
use Modules\Shop\Domain\ValueObjects\Money;
use Tavaan\Testing\TestCase;

/**
 * تست واحد دامنهٔ فروشگاه — DOC-093 (Money/OrderStatus/Order/Cart/Coupon/Pricing/Product/Variant).
 */
final class ShopDomainUnitTest extends TestCase
{
    // ─── Money (ADR-0013) ─────────────────────────────────────────────────

    public function testMoneyArithmeticIsIntegerOnly(): void
    {
        $a = Money::of(1_000_000);
        $b = Money::of(250_000);

        self::assertSame(1_250_000, $a->add($b)->minor);
        self::assertSame(750_000, $a->subtract($b)->minor);
        self::assertSame(3_000_000, $a->multiply(3)->minor);
        self::assertSame(100_000, $a->percent(10.0)->minor);
        self::assertSame(250_000, $a->min($b)->minor);
        self::assertSame(1_000_000, $a->max($b)->minor);
        self::assertTrue(Money::zero()->isZero());
        self::assertTrue($b->isPositive());
        self::assertTrue($a->equals(Money::of(1_000_000)));
    }

    public function testMoneyPercentFloorsTowardCustomer(): void
    {
        // ۹٬۹۹۹ × ۱۰٪ = ۹۹۹٫۹ → ۹۹۹ (گرد به پایین)
        self::assertSame(999, Money::of(9_999)->percent(10.0)->minor);
        // نرخ منفی/بیش‌از‌۱۰۰ مهار می‌شود
        self::assertSame(0, Money::of(1_000)->percent(-5.0)->minor);
        self::assertSame(1_000, Money::of(1_000)->percent(150.0)->minor);
    }

    public function testMoneyCurrencyMismatchThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::of(100, 'IRR')->add(Money::of(100, 'USD'));
    }

    // ─── OrderStatus (ماتریس انتقال) ──────────────────────────────────────

    public function testOrderStatusTransitionMatrix(): void
    {
        $cases = [
            // [از, به, مجاز؟]
            [OrderStatus::Pending, OrderStatus::Paid, true],
            [OrderStatus::Pending, OrderStatus::Canceled, true],
            [OrderStatus::Pending, OrderStatus::Shipped, false],
            [OrderStatus::Paid, OrderStatus::Shipped, true],
            [OrderStatus::Paid, OrderStatus::Canceled, true],
            [OrderStatus::Paid, OrderStatus::Refunded, true],
            [OrderStatus::Paid, OrderStatus::Pending, false],
            [OrderStatus::Shipped, OrderStatus::Delivered, true],
            [OrderStatus::Shipped, OrderStatus::Refunded, true],
            [OrderStatus::Shipped, OrderStatus::Canceled, false],
            [OrderStatus::Delivered, OrderStatus::Refunded, true],
            [OrderStatus::Delivered, OrderStatus::Shipped, false],
            [OrderStatus::Canceled, OrderStatus::Paid, false],
            [OrderStatus::Refunded, OrderStatus::Paid, false],
        ];

        foreach ($cases as [$from, $to, $allowed]) {
            self::assertSame($allowed, $from->canTransitionTo($to), "{$from->value} → {$to->value}");
        }
    }

    public function testOrderStatusLabelsAndFinality(): void
    {
        self::assertTrue(OrderStatus::Canceled->isFinal());
        self::assertTrue(OrderStatus::Refunded->isFinal());
        self::assertFalse(OrderStatus::Pending->isFinal());
        self::assertTrue(OrderStatus::Paid->holdsStock());
        self::assertFalse(OrderStatus::Canceled->holdsStock());
        self::assertSame('در انتظار پرداخت', OrderStatus::Pending->labelFa());
    }

    // ─── Order (invariant مالی + رویدادها) ────────────────────────────────

    public function testOrderPlaceComputesSubtotalAndInvariant(): void
    {
        $order = $this->makeOrder(items: 2, unitPrice: 500_000);

        self::assertSame(1_500_000, $order->subtotal()->minor);   // 500k×2 + 500k×1
        self::assertSame(1_500_000, $order->total()->minor);       // بدون discount/tax/shipping

        $order->applyDiscount(Money::of(200_000));
        $order->applyTax(Money::of(130_000));
        $order->applyShipping(Money::of(50_000));

        // total = 1_500_000 − 200_000 + 130_000 + 50_000
        self::assertSame(1_480_000, $order->total()->minor);
        self::assertSame(3, $order->itemCount());
    }

    public function testOrderDiscountNeverExceedsSubtotal(): void
    {
        $order = $this->makeOrder(items: 1, unitPrice: 100_000);
        $order->applyDiscount(Money::of(500_000));

        self::assertSame(100_000, $order->discount()->minor);
        self::assertSame(0, $order->total()->minor);
    }

    public function testOrderEmitsPlacedThenPaidEvents(): void
    {
        $order = $this->makeOrder(items: 1);

        $events = $order->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrderWasPlaced::class, $events[0]);
        self::assertSame('shop.order.placed', $events[0]->eventName());
        self::assertSame($order->orderNumber(), $events[0]->toPayload()['order_number']);

        $order->markPaid('sbx-authority-1');
        $paid = $order->pullEvents();
        self::assertCount(1, $paid);
        self::assertInstanceOf(OrderWasPaid::class, $paid[0]);
        self::assertSame('sbx-authority-1', $order->paymentAuthority());
        self::assertTrue($order->isPaid());
        self::assertSame(OrderStatus::Paid, $order->status());
    }

    public function testOrderInvalidTransitionThrowsDomainException(): void
    {
        $order = $this->makeOrder(items: 1);

        $this->expectException(\DomainException::class);
        $order->ship();   // pending → shipped نامجاز
    }

    public function testOrderCancelRecordsWasPaid(): void
    {
        $order = $this->makeOrder(items: 1);
        $order->markPaid('sbx-x');
        $order->pullEvents();

        $order->cancel('انصراف');
        $events = $order->pullEvents();

        self::assertCount(1, $events);
        self::assertTrue($events[0]->toPayload()['was_paid']);
        self::assertSame('انصراف', $events[0]->toPayload()['reason']);
        self::assertNotNull($order->canceledAt());
    }

    public function testOrderRequiresCompleteAddress(): void
    {
        $this->expectException(\DomainException::class);

        Order::place(
            orderNumber: 'SH-X',
            customerId: 1,
            items: [$this->makeItem(1)],
            shippingAddress: new Address(receiver: '', phone: '', province: '', city: '', address: ''),
        );
    }

    public function testOrderRowRoundTrip(): void
    {
        $order = $this->makeOrder(items: 2);
        $order->applyDiscount(Money::of(10_000));
        $row = $order->toRow();

        $restored = Order::fromRow($row);
        self::assertSame($order->orderNumber(), $restored->orderNumber());
        self::assertSame($order->total()->minor, $restored->total()->minor);
        self::assertSame('pending', $restored->status()->value);
        self::assertSame('تهران', $restored->shippingAddress()?->city);
    }

    // ─── Cart ─────────────────────────────────────────────────────────────

    public function testCartMergesDuplicateItemsAndRefreshesPrice(): void
    {
        $cart = Cart::create(userId: 7);
        $cart->addItem(CartItem::of('p1', null, 2, Money::of(100_000), 'محصول یک'));
        $cart->addItem(CartItem::of('p1', null, 3, Money::of(90_000), 'محصول یک'));

        self::assertSame(1, $cart->count());
        self::assertSame(5, $cart->totalQuantity());
        // قیمت با آخرین افزودن تازه شد: ۵ × ۹۰٬۰۰۰
        self::assertSame(450_000, $cart->subtotal()->minor);
    }

    public function testCartVariantsAreSeparateLines(): void
    {
        $cart = Cart::create(sessionId: 'sess-1');
        $cart->addItem(CartItem::of('p1', 'v1', 1, Money::of(100_000)));
        $cart->addItem(CartItem::of('p1', 'v2', 1, Money::of(120_000)));

        self::assertSame(2, $cart->count());
        self::assertSame(220_000, $cart->subtotal()->minor);
    }

    public function testCartUpdateAndRemove(): void
    {
        $cart = Cart::create(userId: 1);
        $cart->addItem($first = CartItem::of('p1', null, 1, Money::of(50_000)));
        $cart->addItem(CartItem::of('p2', null, 2, Money::of(70_000)));

        self::assertTrue($cart->updateQuantity($first->id(), 4));
        self::assertSame(340_000, $cart->subtotal()->minor);   // 4×50k + 2×70k

        self::assertTrue($cart->removeItem($first->id()));
        self::assertSame(140_000, $cart->subtotal()->minor);
        self::assertFalse($cart->removeItem('missing'));

        $cart->clear();
        self::assertTrue($cart->isEmpty());
    }

    public function testCartOwnership(): void
    {
        $userCart = Cart::create(userId: 9);
        self::assertTrue($userCart->belongsTo(9, 'any'));
        self::assertFalse($userCart->belongsTo(8, 'any'));

        $guestCart = Cart::create(sessionId: 'sess-2');
        self::assertTrue($guestCart->belongsTo(null, 'sess-2'));
        self::assertFalse($guestCart->belongsTo(null, 'other'));
    }

    public function testCartEmitsUpdatedEvents(): void
    {
        $cart = Cart::create(userId: 2);
        $cart->addItem(CartItem::of('p1', null, 1, Money::of(1_000)));
        $cart->pullEvents();

        $cart->clear();
        $events = $cart->pullEvents();
        self::assertCount(1, $events);
        self::assertSame('shop.cart.updated', $events[0]->eventName());
    }

    // ─── Coupon ───────────────────────────────────────────────────────────

    public function testCouponPercentWithCap(): void
    {
        $coupon = Coupon::create('TAVAAN10', 'percent', 10, maxDiscount: 50_000_000, minCart: 1_000_000);
        $subtotal = Money::of(2_000_000);

        self::assertSame(200_000, $coupon->discountFor($subtotal)->minor);
        self::assertTrue($coupon->isValidFor($subtotal));

        // سقف تخفیف اعمال می‌شود
        $big = Money::of(900_000_000);
        self::assertSame(50_000_000, $coupon->discountFor($big)->minor);

        // زیر حداقل سبد → نامعتبر
        self::assertFalse($coupon->isValidFor(Money::of(500_000)));
    }

    public function testCouponFixedNeverExceedsSubtotal(): void
    {
        $coupon = Coupon::create('OFF500K', 'fixed', 500_000, minCart: 0);

        self::assertSame(500_000, $coupon->discountFor(Money::of(2_000_000))->minor);
        self::assertSame(300_000, $coupon->discountFor(Money::of(300_000))->minor);
    }

    public function testCouponExpiryAndUsageLimit(): void
    {
        $expired = Coupon::create('OLD', 'percent', 10, expiresAt: new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC')));
        self::assertFalse($expired->isValidFor(Money::of(100_000)));

        $limited = Coupon::create('LIMIT1', 'percent', 10, usageLimit: 1);
        self::assertTrue($limited->isValidFor(Money::of(100_000)));
        $limited->markUsed();
        self::assertFalse($limited->isValidFor(Money::of(100_000)));

        $off = Coupon::create('OFF', 'percent', 10);
        $off->deactivate();
        self::assertFalse($off->isValidFor(Money::of(100_000)));
    }

    public function testCouponRejectsInvalidDefinitions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Coupon::create('BAD', 'percent', 120);
    }

    // ─── PricingService ───────────────────────────────────────────────────

    public function testPricingTaxAndShipping(): void
    {
        $pricing = new PricingService([
            'tax' => ['percent' => 9.0, 'shipping_taxable' => false],
            'shipping' => ['flat' => 500_000, 'free_threshold' => 50_000_000],
        ]);

        // ارسال رایگان بالای آستانه
        self::assertSame(0, $pricing->shippingQuote(Money::of(60_000_000))->minor);
        self::assertSame(500_000, $pricing->shippingQuote(Money::of(10_000_000))->minor);

        // مالیات ۹٪ روی (subtotal − discount)
        $order = $this->makeOrder(items: 1, unitPrice: 10_000_000);
        $order->applyDiscount(Money::of(1_000_000));
        $order->applyShipping($pricing->shippingQuote(Money::of(9_000_000)));
        $order->applyTax($pricing->tax($order));

        self::assertSame(810_000, $order->tax()->minor);           // 9٪ × 9M
        self::assertSame(10_310_000, $order->total()->minor);      // 9M + 500k + 810k
    }

    public function testPricingShippingTaxableOption(): void
    {
        $pricing = new PricingService([
            'tax' => ['percent' => 10.0, 'shipping_taxable' => true],
            'shipping' => ['flat' => 100_000, 'free_threshold' => null],
        ]);

        $order = $this->makeOrder(items: 1, unitPrice: 1_000_000);
        $order->applyShipping($pricing->shippingQuote($order->subtotal()));
        $order->applyTax($pricing->tax($order));

        // ۱۰٪ × ۱M + ۱۰٪ × ۱۰۰k = ۱۱۰٬۰۰۰
        self::assertSame(110_000, $order->tax()->minor);
    }

    public function testVariantPriceAddsDelta(): void
    {
        $product = $this->makeProduct(price: 1_000_000, sale: 800_000);
        $variant = ProductVariant::create($product->id(), 'آبی', Money::of(200_000), 5, 'SKU-BLU', ['color' => 'آبی']);
        $pricing = new PricingService();

        // قیمت پایهٔ مؤثر = sale (800k) + delta (200k)
        self::assertSame(1_000_000, $pricing->variantPrice($product, $variant)->minor);
        self::assertSame(800_000, $pricing->unitPrice($product)->minor);
    }

    // ─── Product ──────────────────────────────────────────────────────────

    public function testProductLifecycleAndEvents(): void
    {
        $product = $this->makeProduct(status: 'draft');
        self::assertSame(ProductStatus::Draft, $product->status());
        self::assertFalse($product->isPurchasable());

        $product->activate();
        $product->pullEvents();
        self::assertTrue($product->status()->isPublic());
        self::assertNotNull($product->publishedAt());
        self::assertTrue($product->isPurchasable());

        $product->revise(
            title: 'عنوان تازه',
            summary: null,
            description: null,
            categoryId: 1,
            price: Money::of(500_000),
            salePrice: null,
        );
        $events = $product->pullEvents();
        self::assertCount(1, $events);
        self::assertSame('shop.product.updated', $events[0]->eventName());
        self::assertSame(500_000, $events[0]->toPayload()['price_minor']);
    }

    public function testProductStockAndPurchasability(): void
    {
        $product = $this->makeProduct(stock: 3, lowThreshold: 5);
        $product->activate();

        self::assertTrue($product->isLowStock());
        self::assertFalse($product->isOutOfStock());

        $product->applyStockDelta(-3);
        self::assertSame(0, $product->stock());
        self::assertTrue($product->isOutOfStock());
        self::assertFalse($product->isPurchasable());

        $product->softDelete();
        self::assertTrue($product->isTrashed());
        self::assertFalse($product->commentsOpen());
    }

    public function testProductEffectivePricePrefersSale(): void
    {
        $regular = $this->makeProduct(price: 1_000_000);
        self::assertSame(1_000_000, $regular->effectivePrice()->minor);
        self::assertFalse($regular->isOnSale());

        $onSale = $this->makeProduct(price: 1_000_000, sale: 750_000);
        self::assertSame(750_000, $onSale->effectivePrice()->minor);
        self::assertTrue($onSale->isOnSale());

        // sale بالاتر از قیمت پایه → نادیده گرفته می‌شود
        $weird = $this->makeProduct(price: 1_000_000, sale: 1_200_000);
        self::assertSame(1_000_000, $weird->effectivePrice()->minor);
        self::assertFalse($weird->isOnSale());
    }

    public function testAddressCompletenessAndJson(): void
    {
        $address = Address::fromArray([
            'receiver' => 'سارا', 'phone' => '09121234567', 'province' => 'تهران',
            'city' => 'تهران', 'address' => 'خیابان ولیعصر', 'postal_code' => '1234567890',
        ]);
        self::assertTrue($address->isComplete());
        self::assertSame('سارا', $address->toArray()['receiver']);

        $incomplete = Address::fromArray(['receiver' => 'رضا', 'city' => 'شیراز']);
        self::assertFalse($incomplete->isComplete());
    }

    // ─── کمکی ─────────────────────────────────────────────────────────────

    private function makeOrder(int $items = 1, int $unitPrice = 1_000_000): Order
    {
        $orderItems = [];
        for ($i = 0; $i < $items; $i++) {
            $orderItems[] = $this->makeItem($i + 1, $unitPrice);
        }

        return Order::place(
            orderNumber: 'SH-TEST-' . \bin2hex(\random_bytes(3)),
            customerId: 42,
            items: $orderItems,
            shippingAddress: new Address('گیرنده', '09120000000', 'تهران', 'تهران', 'آدرس کامل ۱', '1234567890'),
        );
    }

    private function makeItem(int $n, int $unitPrice = 500_000): OrderItem
    {
        return OrderItem::of('p-' . $n, null, 'محصول ' . $n, 'SKU-' . $n, $n, Money::of($unitPrice));
    }

    private function makeProduct(int $price = 1_000_000, ?int $sale = null, int $stock = 10, string $status = 'active', int $lowThreshold = 5): Product
    {
        $product = Product::draft(
            slug: 'test-' . \bin2hex(\random_bytes(4)),
            title: 'محصول آزمایشی',
            categoryId: 1,
            price: Money::of($price),
            sku: null,
            stock: $stock,
        );
        if ($sale !== null) {
            $product->revise(
                title: 'محصول آزمایشی',
                summary: null,
                description: null,
                categoryId: 1,
                price: Money::of($price),
                salePrice: Money::of($sale),
            );
        }
        if ($status === 'active') {
            $product->activate();
        }
        if ($lowThreshold !== 5) {
            $product->revise(
                title: 'محصول آزمایشی',
                summary: null,
                description: null,
                categoryId: 1,
                price: Money::of($price),
                salePrice: $sale !== null ? Money::of($sale) : null,
                lowStockThreshold: $lowThreshold,
            );
        }
        $product->pullEvents();

        return $product;
    }
}
