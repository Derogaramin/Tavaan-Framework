<?php

declare(strict_types=1);

namespace Modules\Shop\Tests\Feature;

use Modules\CoreUser\Domain\Entities\User;
use Modules\CoreUser\Infrastructure\Persistence\DatabaseUserRepository;
use Modules\Shop\Application\UseCases\AdminProductService;
use Modules\Shop\Application\UseCases\CheckoutService;
use Modules\Shop\Domain\Entities\Coupon;
use Modules\Shop\Domain\Entities\Product;
use Modules\Shop\Domain\Enums\OrderStatus;
use Modules\Shop\Domain\Repositories\CouponRepositoryInterface;
use Modules\Shop\Domain\Repositories\OrderRepositoryInterface;
use Modules\Shop\Domain\Repositories\ProductRepositoryInterface;
use Modules\Shop\Domain\Services\InventoryException;
use Modules\Shop\Domain\Services\InventoryService;
use Modules\Shop\Domain\ValueObjects\Money;
use Modules\Shop\Infrastructure\Persistence\DatabaseCartRepository;
use Tavaan\Core\Facade\Facades\DB;
use Tavaan\Engine\Rbac\RoleManager;
use Tavaan\Testing\Database\RefreshDatabase;
use Tavaan\Testing\Http\MakesHttpRequests;
use Tavaan\Testing\TestCase;

/**
 * تست E2E ماژول فروشگاه — سناریوهای DOC-093 §10 (E2E-01..08) + API §7 + پنل.
 *
 * مسیر واقعی HTTP (MakesHttpRequests) + SQLite in-memory + RBAC واقعی +
 * درگاه sandbox قطعی — بدون I/O خارجی.
 */
final class ShopE2ETest extends TestCase
{
    use MakesHttpRequests;
    use RefreshDatabase;

    private DatabaseUserRepository $users;

    private RoleManager $roles;

    protected function setUp(): void
    {
        $this->setUpDatabase();

        $app = $this->app();
        $config = $app->make('config');
        if ((string) $config->get('app.key', '') === '') {
            $config->set('app.key', 'base64:' . \base64_encode(\str_repeat('k', 32)));
        }
        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('session.csrf.except', ['admin/*', 'shop/*', 'api/*']);
        $config->set('app.url', 'http://localhost');
        // پرداخت sandbox قطعی (بدون شبکه)
        $config->set('shop.payment.driver', 'sandbox');
        // مالیات/ارسال پیش‌فرض صفر — سناریوهای مالی خودشون config می‌کنند
        $config->set('shop.tax.percent', 0.0);
        $config->set('shop.shipping.flat', 0);
        $config->set('shop.shipping.free_threshold', null);

        $this->users = $app->make(DatabaseUserRepository::class);
        $this->roles = $app->make(RoleManager::class);

        $this->seedRbac();
    }

    // ─── E2E-01: مرور → سبد → checkout → پرداخت موفق → تأیید ──────────────

    public function testE2E01_BrowseCartCheckoutPayConfirm(): void
    {
        $product = $this->createActiveProduct('لپ‌تاپ پارس ۱۵', price: 4_000_000, stock: 5);
        $customer = $this->user('customer1@test.dev', 'مشتری یک', role: 'customer');

        // ۱) مرور فروشگاه — محصول در فهرست و صفحهٔ جزئیات visible
        $listing = $this->get('/shop');
        $listing->assertOk();
        $listing->assertSee('لپ‌تاپ پارس ۱۵');

        $detail = $this->get('/shop/products/' . $product->slug());
        $detail->assertOk();
        $detail->assertSee('لپ‌تاپ پارس ۱۵');

        // ۲) افزودن به سبد (۲ عدد)
        $this->actingAs($customer)->post('/shop/cart/items', [
            'product_id' => $product->id(),
            'quantity' => 2,
        ])->assertRedirect();

        $cart = $this->get('/shop/cart');
        $cart->assertOk();
        $cart->assertSee('لپ‌تاپ پارس ۱۵');
        $cart->assertSee(trans_number(8_000_000));

        // ۳) checkout → ثبت سفارش → redirect به صفحهٔ پرداخت
        $response = $this->post('/shop/checkout', $this->addressData());
        $response->assertRedirect();
        $location = (string) $response->header('Location');
        self::assertStringContainsString('/shop/pay/SH-', $location);

        // در محیط تست host خالی است (http:///) — مسیر با regex استخراج می‌شود
        \preg_match('#/shop/pay/(SH-[0-9A-Za-z-]+)#', $location, $mNum);
        $orderNumber = $mNum[1];
        $orders = $this->app()->make(OrderRepositoryInterface::class);
        $order = $orders->findByNumber($orderNumber);
        self::assertNotNull($order);
        self::assertSame(OrderStatus::Pending, $order->status());
        self::assertSame(8_000_000, $order->total()->minor);

        // موجودی در ثبت سفارش رزرو شد: 5 → 3
        self::assertSame(3, $this->stockOf($product->id()));

        // سبد تخلیه شد
        $freshCart = $this->app()->make(DatabaseCartRepository::class)->findActive($customer->id, null);
        self::assertTrue($freshCart === null || $freshCart->isEmpty());

        // ۴) شروع پرداخت → redirect به شبیه‌ساز sandbox
        $payResponse = $this->get('/shop/pay/' . $orderNumber);
        $payResponse->assertRedirect();
        \preg_match('#(/shop/pay/sandbox/sbx-[^"?]+)#', (string) $payResponse->header('Location'), $mSbx);
        self::assertSame('sbx-', \substr($mSbx[1] ?? '', \strlen('/shop/pay/sandbox/'), 4), 'redirect به sandbox انتظار می‌رفت: ' . $payResponse->header('Location'));
        $sandboxUrl = $mSbx[1];

        $sandboxPage = $this->get($sandboxUrl);
        $sandboxPage->assertOk();
        $sandboxPage->assertSee('درگاه آزمایشی');

        // ۵) بازگشت موفق از درگاه → verify → paid (authority = بخش آخر مسیر sandbox)
        $authority = \rawurldecode(\basename($sandboxUrl));

        $result = $this->get('/shop/pay/callback?authority=' . \rawurlencode($authority) . '&status=ok');
        $result->assertOk();
        $result->assertSee('پرداخت با موفقیت انجام شد');

        $paidOrder = $orders->findByNumber($orderNumber);
        self::assertNotNull($paidOrder);
        self::assertSame(OrderStatus::Paid, $paidOrder->status());
        self::assertNotNull($paidOrder->paidAt());

        // sold_count افزایش یافت
        $freshProduct = $this->app()->make(ProductRepositoryInterface::class)->findById($product->id());
        self::assertSame(2, $freshProduct?->soldCount());

        // اعلان database برای مشتری ثبت شد
        self::assertTrue(DB::table('tvn_notifications')->where('notifiable_id', (string) $customer->id)->exists());
    }

    // ─── E2E-02: موجودی ناکافی → خطا در reserve ───────────────────────────

    public function testE2E02_InsufficientStockBlocksReserve(): void
    {
        $product = $this->createActiveProduct('کتاب کمیاب', price: 1_000_000, stock: 2);
        $customer = $this->user('c2@test.dev', 'مشتری دو', role: 'customer');

        // افزودن بیش از موجودی → خطای اعتبارسنجی، سبد تغییری نمی‌کند
        $this->actingAs($customer)->post('/shop/cart/items', [
            'product_id' => $product->id(),
            'quantity' => 5,
        ])->assertRedirect();
        self::assertSame(2, $this->stockOf($product->id()));

        // سناریوی reserve: ۲ قلم در سبد، موجودی به ۱ کاهش یافته (فروش همزمان)
        $this->post('/shop/cart/items', ['product_id' => $product->id(), 'quantity' => 2])->assertRedirect();
        DB::table('shop_products')->where('id', $product->id())->update(['stock' => 1]);

        $response = $this->post('/shop/checkout', $this->addressData());
        $response->assertRedirect();   // برگشت به checkout با خطا

        // سفارشی ساخته نشد و موجودی دست‌نخورده ماند (rollback تراکنش)
        self::assertSame(0, (int) DB::table('shop_orders')->count());
        self::assertSame(1, $this->stockOf($product->id()));
    }

    // ─── E2E-03: پرداخت ناموفق → pending + retry ──────────────────────────

    public function testE2E03_FailedPaymentStaysPendingThenRetrySucceeds(): void
    {
        $product = $this->createActiveProduct('هدفون آوا', price: 2_000_000, stock: 10);
        $customer = $this->user('c3@test.dev', 'مشتری سه', role: 'customer');
        $orders = $this->app()->make(OrderRepositoryInterface::class);

        $this->actingAs($customer)->post('/shop/cart/items', ['product_id' => $product->id(), 'quantity' => 1])->assertRedirect();
        $this->post('/shop/checkout', $this->addressData())->assertRedirect();

        $orderRow = DB::table('shop_orders')->first();
        self::assertNotNull($orderRow);
        $orderNumber = (string) $orderRow['order_number'];

        // پرداخت با شبیه‌سازی شکست
        $pay = $this->get('/shop/pay/' . $orderNumber . '?simulate_failure=1');
        $pay->assertRedirect();
        \preg_match('#(/shop/pay/sandbox/[^"?]+)#', (string) $pay->header('Location'), $mSbx);
        self::assertStringContainsString('sbx-fail-', $mSbx[1] ?? '');
        $sandboxUrl = $mSbx[1];

        \preg_match('#/shop/pay/sandbox/([^"?]+)#', $sandboxUrl, $m);
        $authority = \rawurldecode($m[1]);

        $result = $this->get('/shop/pay/callback?authority=' . \rawurlencode($authority) . '&status=fail');
        $result->assertOk();
        $result->assertSee('پرداخت تأیید نشد');

        // سفارش pending ماند — retry مجاز
        $fresh = $orders->findByNumber($orderNumber);
        self::assertSame(OrderStatus::Pending, $fresh->status());

        // تلاش دوباره → موفق
        $retry = $this->get('/shop/pay/' . $orderNumber);
        $retry->assertRedirect();
        \preg_match('#(/shop/pay/sandbox/[^"?]+)#', (string) $retry->header('Location'), $m2Sbx);
        \preg_match('#/shop/pay/sandbox/([^"?]+)#', $m2Sbx[1], $m2);
        $authority2 = \rawurldecode($m2[1]);

        $okResult = $this->get('/shop/pay/callback?authority=' . \rawurlencode($authority2) . '&status=ok');
        $okResult->assertOk();
        $okResult->assertSee('پرداخت با موفقیت انجام شد');
        self::assertSame(OrderStatus::Paid, $orders->findByNumber($orderNumber)->status());
    }

    // ─── E2E-04: لغو سفارش → بازگشت موجودی + refund ─────────────────────

    public function testE2E04_CancelReleasesStockAndRefunds(): void
    {
        $product = $this->createActiveProduct('قهوه‌ساز رها', price: 5_000_000, stock: 4);
        $customer = $this->user('c4@test.dev', 'مشتری چهار', role: 'customer');
        $manager = $this->user('manager4@test.dev', 'مدیر', role: 'shop-manager');

        $order = $this->placePaidOrder($customer, $product, quantity: 2);
        self::assertSame(2, $this->stockOf($product->id()));   // 4 − 2 رزرو

        // مدیر لغو می‌کند → موجودی بازمی‌گردد + refund درگاه (sandbox)
        $this->actingAs($manager)
            ->post('/admin/shop/orders/' . $order->id() . '/status', ['action' => 'cancel', 'reason' => 'اتمام موجودی انبار'])
            ->assertRedirect();

        $fresh = $this->app()->make(OrderRepositoryInterface::class)->findById($order->id());
        self::assertSame(OrderStatus::Canceled, $fresh->status());
        self::assertSame(4, $this->stockOf($product->id()));   // بازگشت کامل

        // حرکت‌نامهٔ موجودی: reserve(−2) و release(+2) ثبت شده
        $reasons = \array_column(DB::table('shop_stock_movements')->where('product_id', $product->id())->get(), 'reason');
        self::assertContains('reserve', $reasons);
        self::assertContains('release', $reasons);

        // لغو مجدد → خطا (پایانی)
        $this->actingAs($manager)
            ->post('/admin/shop/orders/' . $order->id() . '/status', ['action' => 'cancel'])
            ->assertRedirect();   // با flash error برمی‌گردد
        $stillCanceled = $this->app()->make(OrderRepositoryInterface::class)->findById($order->id());
        self::assertSame(OrderStatus::Canceled, $stillCanceled->status());
    }

    public function testE2E04b_CustomerCancelsOwnPendingOrder(): void
    {
        $product = $this->createActiveProduct('دفترچه هفت‌جلدی', price: 500_000, stock: 6);
        $customer = $this->user('c4b@test.dev', 'مشتری', role: 'customer');

        $order = $this->placeOrder($customer, $product, quantity: 3);
        self::assertSame(3, $this->stockOf($product->id()));

        $this->actingAs($customer)->post('/shop/orders/' . $order->orderNumber() . '/cancel')->assertRedirect();

        $fresh = $this->app()->make(OrderRepositoryInterface::class)->findById($order->id());
        self::assertSame(OrderStatus::Canceled, $fresh->status());
        self::assertSame(6, $this->stockOf($product->id()));   // موجودی بازگشت
    }

    // ─── E2E-05: کوپن تخفیف → محاسبهٔ درست ───────────────────────────────

    public function testE2E05_CouponDiscountComputedCorrectly(): void
    {
        $product = $this->createActiveProduct('گوشی سیمرغ', price: 2_000_000, stock: 10);
        $customer = $this->user('c5@test.dev', 'مشتری پنج', role: 'customer');

        $coupons = $this->app()->make(CouponRepositoryInterface::class);
        $coupons->save(Coupon::create('TAVAAN10', 'percent', 10, maxDiscount: 50_000_000, minCart: 1_000_000));

        $this->actingAs($customer)->post('/shop/cart/items', ['product_id' => $product->id(), 'quantity' => 1])->assertRedirect();

        // اعمال کوپن
        $this->post('/shop/cart/coupon', ['coupon_code' => 'tavaan10'])->assertRedirect();

        // کوپن نامعتبر → خطا
        $bad = $this->post('/shop/cart/coupon', ['coupon_code' => 'NOPE123']);
        $bad->assertRedirect();

        // کوپن درست دوباره (چون قبلی پاک کرد؟ خیر — کد نامعتبر فقط پاک می‌کند اگر error شود)
        $this->post('/shop/cart/coupon', ['coupon_code' => 'TAVAAN10'])->assertRedirect();

        $this->post('/shop/checkout', $this->addressData())->assertRedirect();

        $order = $this->latestOrder();
        self::assertSame(2_000_000, $order->subtotal()->minor);
        self::assertSame(200_000, $order->discount()->minor);      // ۱۰٪ × ۲M
        self::assertSame(1_800_000, $order->total()->minor);

        // مصرف کوپن ثبت شد
        $fresh = $coupons->findByCode('TAVAAN10');
        self::assertSame(1, $fresh->usedCount());
    }

    public function testE2E05b_CouponCapAndExpiryRespected(): void
    {
        $product = $this->createActiveProduct('لپ‌تاپ گران', price: 900_000_000, stock: 3);
        $customer = $this->user('c5b@test.dev', 'مشتری', role: 'customer');

        $coupons = $this->app()->make(CouponRepositoryInterface::class);
        $coupons->save(Coupon::create('BIG10', 'percent', 10, maxDiscount: 50_000_000, minCart: 0));
        $coupons->save(Coupon::create('EXPIRED1', 'percent', 50, expiresAt: new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC'))));

        $this->actingAs($customer)->post('/shop/cart/items', ['product_id' => $product->id(), 'quantity' => 1])->assertRedirect();

        // کوپن منقضی → بدون تخفیف
        $this->post('/shop/cart/coupon', ['coupon_code' => 'EXPIRED1'])->assertRedirect();
        $order1 = $this->placeOrderFromCurrentCart($customer);
        self::assertSame(0, $order1->discount()->minor);

        // کوپن با سقف → تخفیف حداکثر ۵۰M
        $this->actingAs($customer)->post('/shop/cart/items', ['product_id' => $product->id(), 'quantity' => 1])->assertRedirect();
        $this->post('/shop/cart/coupon', ['coupon_code' => 'BIG10'])->assertRedirect();
        $order2 = $this->placeOrderFromCurrentCart($customer);
        self::assertSame(50_000_000, $order2->discount()->minor);   // نه ۹۰M — سقف
        self::assertSame(850_000_000, $order2->total()->minor);
    }

    // ─── E2E-06: مالیات و ارسال در total ──────────────────────────────────

    public function testE2E06_TaxAndShippingInTotal(): void
    {
        // پیکربندی مالی: ۹٪ مالیات + ارسال تخت ۵۰۰٬۰۰۰ (بدون آستانهٔ رایگان)
        $config = $this->app()->make('config');
        $config->set('shop.tax.percent', 9.0);
        $config->set('shop.shipping.flat', 500_000);
        $config->set('shop.shipping.free_threshold', null);

        $product = $this->createActiveProduct('اسپرسوساز', price: 10_000_000, stock: 5);
        $customer = $this->user('c6@test.dev', 'مشتری شش', role: 'customer');

        $order = $this->placeOrder($customer, $product, quantity: 2);

        // subtotal=20M • tax=9٪×20M=1.8M • shipping=500k • total=22.3M
        self::assertSame(20_000_000, $order->subtotal()->minor);
        self::assertSame(1_800_000, $order->tax()->minor);
        self::assertSame(500_000, $order->shippingCost()->minor);
        self::assertSame(22_300_000, $order->total()->minor);
    }

    public function testE2E06b_FreeShippingAboveThreshold(): void
    {
        $config = $this->app()->make('config');
        $config->set('shop.tax.percent', 0.0);
        $config->set('shop.shipping.flat', 500_000);
        $config->set('shop.shipping.free_threshold', 50_000_000);

        $cheap = $this->createActiveProduct('ارزان', price: 1_000_000, stock: 5);
        $pricey = $this->createActiveProduct('گران‌قیمت', price: 60_000_000, stock: 5);
        $customer = $this->user('c6b@test.dev', 'مشتری', role: 'customer');

        $orderCheap = $this->placeOrder($customer, $cheap, quantity: 1);
        self::assertSame(500_000, $orderCheap->shippingCost()->minor);
        self::assertSame(1_500_000, $orderCheap->total()->minor);

        $orderPricey = $this->placeOrder($customer, $pricey, quantity: 1);
        self::assertSame(0, $orderPricey->shippingCost()->minor);   // ارسال رایگان
        self::assertSame(60_000_000, $orderPricey->total()->minor);
    }

    // ─── E2E-07: جست‌وجوی فارسی محصول ─────────────────────────────────────

    public function testE2E07_PersianProductSearch(): void
    {
        $this->createActiveProduct('شاهنامه فردوسی نسخه نفیس', price: 9_000_000, stock: 5);
        $this->createActiveProduct('لپ‌تاپ پارس ۱۵ اینچ', price: 40_000_000, stock: 5);

        // جست‌وجوی فارسی
        $result = $this->get('/shop/search?q=' . \rawurlencode('شاهنامه'));
        $result->assertOk();
        $result->assertSee('شاهنامه فردوسی نسخه نفیس');
        $result->assertDontSee('لپ‌تاپ پارس ۱۵ اینچ');

        // رقم فارسی در کوئری — «۱۵» باید «لپ‌تاپ پارس ۱۵ اینچ» را بیابد
        $persianDigits = $this->get('/shop/search?q=' . \rawurlencode('پارس ۱۵'));
        $persianDigits->assertOk();
        $persianDigits->assertSee('لپ‌تاپ پارس ۱۵ اینچ');

        // API جست‌وجو
        $api = $this->getJson('/api/v1/shop/products?q=' . \rawurlencode('شاهنامه'));
        $api->assertOk();
        self::assertStringContainsString('شاهنامه فردوسی', $api->content());
    }

    // ─── E2E-08: همزمانی — دو کاربر برای آخرین موجودی ─────────────────────

    public function testE2E08_ConcurrencyLastItemGoesToFirstReserver(): void
    {
        $product = $this->createActiveProduct('آخرین نسخه', price: 1_000_000, stock: 1);
        $customerA = $this->user('a@test.dev', 'الف', role: 'customer');
        $customerB = $this->user('b@test.dev', 'ب', role: 'customer');

        // هر دو سبد می‌سازند (افزودن به سبد موجودی را بررسی سطحی می‌کند)
        $this->actingAs($customerA)->post('/shop/cart/items', ['product_id' => $product->id(), 'quantity' => 1])->assertRedirect();
        $this->actingAs($customerB)->post('/shop/cart/items', ['product_id' => $product->id(), 'quantity' => 1])->assertRedirect();

        // A زودتر checkout می‌کند → موفق
        $this->actingAs($customerA)->post('/shop/checkout', $this->addressData())->assertRedirect();
        self::assertSame(0, $this->stockOf($product->id()));
        self::assertSame(1, (int) DB::table('shop_orders')->count());

        // B → reserve شکست می‌خورد (UPDATE اتمیک شرطی) و سفارشی ثبت نمی‌شود
        $this->actingAs($customerB)->post('/shop/checkout', $this->addressData())->assertRedirect();
        self::assertSame(1, (int) DB::table('shop_orders')->count());   // فقط سفارش A
        self::assertSame(0, $this->stockOf($product->id()));           // منفی نشد!
    }

    public function testE2E08b_InventoryServiceReserveIsAtomic(): void
    {
        $product = $this->createActiveProduct('کالای race', price: 100_000, stock: 1);
        $inventory = $this->app()->make(InventoryService::class);

        // رزرو ۲ عدد با موجودی ۱ → خطا و موجودی دست‌نخورده
        $item = \Modules\Shop\Domain\Entities\OrderItem::of($product->id(), null, 'کالا', null, 2, Money::of(100_000));

        $thrown = false;
        try {
            $inventory->reserve([$item]);
        } catch (InventoryException $e) {
            $thrown = true;
            self::assertSame('shop::messages.out_of_stock', $e->messageId);
        }
        self::assertTrue($thrown, 'باید InventoryException پرتاب شود');
        self::assertSame(1, $this->stockOf($product->id()));

        // همه-یا-هیچ: قلم اول موفق، قلم دوم ناموفق → rollback قلم اول
        $second = $this->createActiveProduct('کالای دوم', price: 100_000, stock: 0);
        $item2 = \Modules\Shop\Domain\Entities\OrderItem::of($second->id(), null, 'دوم', null, 1, Money::of(100_000));
        try {
            $inventory->reserve([$item, \Modules\Shop\Domain\Entities\OrderItem::of($product->id(), null, 'یک', null, 1, Money::of(100_000)), $item2]);
        } catch (InventoryException) {
            // انتظار می‌رود
        }
        self::assertSame(1, $this->stockOf($product->id()), 'rollback داخلی باید رزرو قلم اول را برگرداند');
        self::assertSame(0, $this->stockOf($second->id()));
    }

    // ─── variantها ────────────────────────────────────────────────────────

    public function testVariantPricingAndStock(): void
    {
        $product = $this->createActiveProduct('تی‌شرت ورزشی', price: 1_000_000, stock: 0);
        $customer = $this->user('cv@test.dev', 'مشتری', role: 'customer');

        // variant با delta + موجودی مستقل
        DB::table('shop_product_variants')->insert([
            'id' => 'V1TESTVARIANTAAAAAAAAAAAAAA',
            'product_id' => $product->id(),
            'sku' => 'TSH-L',
            'title' => 'سایز L',
            'price_delta' => 200_000,
            'stock' => 3,
            'options' => (string) \json_encode(['size' => 'L'], JSON_UNESCAPED_UNICODE),
        ]);

        $this->actingAs($customer)->post('/shop/cart/items', [
            'product_id' => $product->id(),
            'variant_id' => 'V1TESTVARIANTAAAAAAAAAAAAAA',
            'quantity' => 2,
        ])->assertRedirect();

        $order = $this->placeOrderFromCurrentCart($customer);
        // قیمت variant = 1M + 200k = 1.2M × 2
        self::assertSame(2_400_000, $order->subtotal()->minor);
        self::assertStringContainsString('سایز L', $order->items()[0]->title);

        // موجودی variant کم شد
        $variantRow = DB::table('shop_product_variants')->where('id', 'V1TESTVARIANTAAAAAAAAAAAAAA')->first();
        self::assertSame(1, (int) $variantRow['stock']);
    }

    // ─── API (DOC-093 §7) ─────────────────────────────────────────────────

    public function testApiProductEndpoints(): void
    {
        $product = $this->createActiveProduct('ماوس بی‌سیم', price: 800_000, stock: 12);
        $this->createCategory('لوازم جانبی', 'accessories');

        $list = $this->getJson('/api/v1/shop/products');
        $list->assertOk();
        $decoded = \json_decode($list->content(), true);
        self::assertIsArray($decoded['data'] ?? null);
        self::assertSame('ماوس بی‌سیم', $decoded['data'][0]['title']);
        self::assertSame(800_000, $decoded['data'][0]['effective_price']);
        self::assertTrue($decoded['data'][0]['in_stock']);

        $detail = $this->getJson('/api/v1/shop/products/' . $product->slug());
        $detail->assertOk();
        $detailJson = \json_decode($detail->content(), true);
        self::assertSame($product->id(), $detailJson['data']['id']);
        self::assertSame(12, $detailJson['data']['stock']);

        $categories = $this->getJson('/api/v1/shop/categories');
        $categories->assertOk();
        self::assertStringContainsString('لوازم جانبی', $categories->content());

        // محصول draft در API دیده نمی‌شود
        $draft = $this->createProduct('پیش‌نویس مخفی', price: 100_000, stock: 1, active: false);
        $this->getJson('/api/v1/shop/products/' . $draft->slug())->assertNotFound();
    }

    public function testApiCartAndCheckoutFlow(): void
    {
        $product = $this->createActiveProduct('کیبورد مکانیکی', price: 3_500_000, stock: 7);
        $customer = $this->user('api@test.dev', 'کاربر API', role: 'customer');

        // مهمان: افزودن به سبد
        $this->actingAs($customer);
        $add = $this->postJson('/api/v1/shop/cart/items', [
            'product_id' => $product->id(),
            'quantity' => 2,
        ]);
        $add->assertOk();
        $addJson = \json_decode($add->content(), true);
        self::assertSame(1, $addJson['data']['count']);            // یک قلم…
        self::assertSame(2, $addJson['data']['items'][0]['quantity']); // …با تعداد ۲
        self::assertSame(7_000_000, $addJson['summary']['subtotal']);

        $cart = $this->getJson('/api/v1/shop/cart');
        $cart->assertOk();
        self::assertSame(1, \json_decode($cart->content(), true)['data']['count']);

        // checkout
        $checkout = $this->postJson('/api/v1/shop/checkout', $this->addressData());
        $checkout->assertOk();
        $orderJson = \json_decode($checkout->content(), true)['data'];
        self::assertSame('pending', $orderJson['status']);
        self::assertSame(7_000_000, $orderJson['total']);

        // سفارش‌های من
        $orders = $this->getJson('/api/v1/shop/orders');
        $orders->assertOk();
        self::assertCount(1, \json_decode($orders->content(), true)['data']);

        // لغو از API
        $cancel = $this->postJson('/api/v1/shop/orders/' . $orderJson['order_id'] . '/cancel', []);
        $cancel->assertOk();
        self::assertSame(7, $this->stockOf($product->id()));   // موجودی بازگشت

        // دسترسی به سفارش دیگران → 403
        $other = $this->user('other@test.dev', 'دیگری', role: 'customer');
        $this->actingAs($other)->getJson('/api/v1/shop/orders/' . $orderJson['order_id'])->assertForbidden();
    }

    public function testApiGuestCanBrowseButNotCheckout(): void
    {
        $this->createActiveProduct('مانیتور ۲۴', price: 12_000_000, stock: 3);

        $this->getJson('/api/v1/shop/products')->assertOk();
        $this->postJson('/api/v1/shop/checkout', $this->addressData())->assertForbidden();
    }

    // ─── پنل مدیریت + RBAC ────────────────────────────────────────────────

    public function testAdminProductCrudAndRbac(): void
    {
        $this->createCategory('دیجیتال', 'digital');
        $categoryId = (int) DB::table('shop_categories')->where('slug', 'digital')->first()['id'];

        $operator = $this->user('op@test.dev', 'اپراتور', role: 'shop-operator');
        $customer = $this->user('cust@test.dev', 'مشتری', role: 'customer');

        // مشتری → 403
        $this->actingAs($customer)->get('/admin/shop/products')->assertForbidden();

        // اپراتور → فهرست + ساخت محصول
        $this->actingAs($operator)->get('/admin/shop/products')->assertOk();

        $this->post('/admin/shop/products', [
            'title' => 'پاوربانک ۲۰۰۰۰',
            'category_id' => $categoryId,
            'price' => 1_800_000,
            'sale_price' => 1_500_000,
            'stock' => 20,
            'sku' => 'PB-20K',
            'status' => 'active',
            'summary' => 'پاوربانک **۲۰۰۰۰** میلی‌آمپر',
            'attributes' => '{"ظرفیت":"۲۰۰۰۰mAh"}',
        ])->assertRedirect();

        $repo = $this->app()->make(ProductRepositoryInterface::class);
        $product = $repo->findBySku('PB-20K');
        self::assertNotNull($product, 'محصول با SKU ساخته نشد');
        self::assertSame('active', $product->status()->value);
        self::assertSame(1_500_000, $product->effectivePrice()->minor);
        self::assertSame('۲۰۰۰۰mAh', $product->attributes()['ظرفیت'] ?? null);

        // ویرایش
        $this->actingAs($operator)->put('/admin/shop/products/' . $product->id(), [
            'title' => 'پاوربانک ۲۰۰۰۰ ویژه',
            'category_id' => $categoryId,
            'price' => 1_700_000,
            'stock' => 18,
            'status' => 'active',
        ])->assertRedirect();
        $fresh = $repo->findById($product->id());
        self::assertSame('پاوربانک ۲۰۰۰۰ ویژه', $fresh?->title());
        self::assertSame(18, $fresh?->stock());

        // حذف (soft) — اپراتور مجوز delete ندارد → 403
        $this->actingAs($operator)->delete('/admin/shop/products/' . $product->id())->assertForbidden();

        // مدیر حذف می‌کند
        $admin = $this->user('admin@test.dev', 'مدیر', role: 'admin');
        $this->actingAs($admin)->delete('/admin/shop/products/' . $product->id())->assertRedirect();
        self::assertTrue($repo->findById($product->id())?->isTrashed() ?? false);
    }

    public function testAdminOrderWorkflow(): void
    {
        $product = $this->createActiveProduct('هارد اکسترنال', price: 6_000_000, stock: 9);
        $customer = $this->user('oc@test.dev', 'مشتری', role: 'customer');
        $operator = $this->user('oo@test.dev', 'اپراتور', role: 'shop-operator');
        $manager = $this->user('om@test.dev', 'مدیر', role: 'shop-manager');

        $order = $this->placePaidOrder($customer, $product, quantity: 2);

        // اپراتور فقط مشاهده
        $this->actingAs($operator)->get('/admin/shop/orders')->assertOk();
        $this->actingAs($operator)->get('/admin/shop/orders/' . $order->id())->assertOk();
        $this->actingAs($operator)
            ->post('/admin/shop/orders/' . $order->id() . '/status', ['action' => 'ship'])
            ->assertForbidden();   // تغییر وضعیت برای اپراتور مجاز نیست

        // مدیر ارسال → تحویل
        $this->actingAs($manager)
            ->post('/admin/shop/orders/' . $order->id() . '/status', ['action' => 'ship', 'tracking_code' => 'POST-123'])
            ->assertRedirect();
        $orders = $this->app()->make(OrderRepositoryInterface::class);
        self::assertSame(OrderStatus::Shipped, $orders->findById($order->id())->status());
        self::assertSame('POST-123', $orders->findById($order->id())->trackingCode());

        $this->actingAs($manager)
            ->post('/admin/shop/orders/' . $order->id() . '/status', ['action' => 'deliver'])
            ->assertRedirect();
        self::assertSame(OrderStatus::Delivered, $orders->findById($order->id())->status());

        // استرداد مدیرتی
        $this->actingAs($manager)
            ->post('/admin/shop/orders/' . $order->id() . '/refund', ['reason' => 'خرابی کالا'])
            ->assertRedirect();
        self::assertSame(OrderStatus::Refunded, $orders->findById($order->id())->status());
    }

    public function testInventoryKeeperAdjustsStock(): void
    {
        $product = $this->createActiveProduct('کالای انبار', price: 100_000, stock: 10);
        $keeper = $this->user('keeper@test.dev', 'انباردار', role: 'inventory-keeper');
        $operator = $this->user('op2@test.dev', 'اپراتور', role: 'shop-operator');

        // اپراتور بدون مجوز انبار → 403 (policy: update هم ندارد؟ operator دارد — پس انباردار تست شود)
        $this->actingAs($keeper)
            ->post('/admin/shop/products/' . $product->id() . '/inventory', ['delta' => -4, 'note' => 'انبارگردانی'])
            ->assertRedirect();
        self::assertSame(6, $this->stockOf($product->id()));

        $movement = DB::table('shop_stock_movements')->where('product_id', $product->id())->where('reason', 'adjust')->first();
        self::assertNotNull($movement);
        self::assertSame(-4, (int) $movement['delta']);
        self::assertSame('انبارگردانی', $movement['note']);

        // اپراتور بدون shop.inventory.manage و بدون update روی محصول؟ operator مجوز product.update دارد
        // → inventory-keeper بدون مجوز محصول، صفحهٔ ویرایش را نمی‌بیند
        $this->actingAs($keeper)->get('/admin/shop/products/' . $product->id() . '/edit')->assertForbidden();
        $this->actingAs($operator)->get('/admin/shop/products/' . $product->id() . '/edit')->assertOk();
    }

    public function testAdminCouponManagement(): void
    {
        $manager = $this->user('cm@test.dev', 'مدیر', role: 'shop-manager');

        $this->actingAs($manager)->get('/admin/shop/coupons')->assertOk();

        $this->post('/admin/shop/coupons', [
            'code' => 'nowruz',
            'type' => 'percent',
            'value' => 15,
            'max_discount' => 30_000_000,
            'min_cart' => 500_000,
            'usage_limit' => 100,
            'expires_in_days' => 30,
        ])->assertRedirect();

        $coupons = $this->app()->make(CouponRepositoryInterface::class);
        $coupon = $coupons->findByCode('NOWRUZ');   // نرمال به بزرگ
        self::assertNotNull($coupon);
        self::assertSame(15, $coupon->value());
        self::assertNotNull($coupon->expiresAt());

        // کد تکراری → خطا
        $this->actingAs($manager)->post('/admin/shop/coupons', ['code' => 'NOWRUZ', 'type' => 'fixed', 'value' => 1000])->assertRedirect();
        self::assertCount(1, $coupons->all());

        // غیرفعال‌سازی
        $this->actingAs($manager)->post('/admin/shop/coupons/' . $coupon->id() . '/deactivate')->assertRedirect();
        self::assertFalse($coupons->findById($coupon->id())->isActive());
    }

    public function testSalesCsvExport(): void
    {
        $product = $this->createActiveProduct('کالای گزارش', price: 1_000_000, stock: 5);
        $customer = $this->user('csv@test.dev', 'مشتری', role: 'customer');
        $manager = $this->user('csvm@test.dev', 'مدیر', role: 'shop-manager');
        $operator = $this->user('csvo@test.dev', 'اپراتور', role: 'shop-operator');

        $this->placePaidOrder($customer, $product, quantity: 1);

        // اپراتور بدون shop.report.view → 403
        $this->actingAs($operator)->get('/admin/shop/orders/sales.csv')->assertForbidden();

        $response = $this->actingAs($manager)->get('/admin/shop/orders/sales.csv');
        $response->assertOk();
        $response->assertHeader('Content-Type');
        self::assertStringContainsString('order_number', $response->content());
        self::assertStringContainsString('paid', $response->content());
    }

    // ─── نقد و بررسی ──────────────────────────────────────────────────────

    public function testReviewsFlowGuestSubmitAndModerate(): void
    {
        $product = $this->createActiveProduct('کتاب نقدشونده', price: 500_000, stock: 5);
        $moderator = $this->user('mod@test.dev', 'ناظر', role: 'shop-manager');

        // مهمان نقد می‌نویسد → pending
        $this->post('/shop/products/' . $product->slug() . '/reviews', [
            'author_name' => 'مهمان ناشناس',
            'author_email' => 'guest@test.dev',
            'body' => 'کیفیت چاپ عالی بود، پیشنهاد می‌کنم.',
        ])->assertRedirect();

        $comment = DB::table('shop_comments')->where('body', 'like', '%کیفیت چاپ%')->first();
        self::assertNotNull($comment);
        self::assertSame('pending', $comment['status']);

        // در ویترین دیده نمی‌شود (pending)
        $this->get('/shop/products/' . $product->slug())->assertDontSee('کیفیت چاپ عالی بود');

        // مدیر تأیید می‌کند
        $this->actingAs($moderator)->get('/admin/shop/comments')->assertOk();
        $this->post('/admin/shop/comments/' . $comment['id'] . '/approve')->assertRedirect();

        self::assertSame('approved', DB::table('shop_comments')->where('id', $comment['id'])->first()['status']);
        $this->get('/shop/products/' . $product->slug())->assertSee('کیفیت چاپ عالی بود');

        // XSS در ذخیره خنثی می‌شود (نقد دوم با هویت moderator — actingAs پایدار است)
        $this->post('/shop/products/' . $product->slug() . '/reviews', [
            'body' => 'عالی <script>alert(1)</script>',
        ])->assertRedirect();
        $xss = DB::table('shop_comments')->where('author_email', 'mod@test.dev')->first();
        self::assertNotNull($xss, 'نقد دوم ذخیره نشد');
        self::assertStringNotContainsString('<script>', (string) $xss['body']);
    }

    // ─── رویدادها و کش ────────────────────────────────────────────────────

    public function testLowStockEventAndNotificationOnPaid(): void
    {
        // آستانهٔ کم: stock=3, threshold=5 → پس از رزرو ۲ عدد، stock=1 ≤ 5 → رویداد
        $product = $this->createActiveProduct('کالای رو به اتمام', price: 300_000, stock: 3, lowThreshold: 5);
        $customer = $this->user('low@test.dev', 'مشتری', role: 'customer');

        $captured = [];
        \Tavaan\Core\Facade\Facades\Event::listen('shop.stock.low', static function (string $event, mixed $payload) use (&$captured): void {
            $captured[] = $payload;
        });

        $this->placeOrder($customer, $product, quantity: 2);

        self::assertCount(1, $captured);
        self::assertSame(1, $captured[0]['stock']);
        self::assertSame('کالای رو به اتمام', $captured[0]['title']);
    }

    public function testProductCacheInvalidatedOnUpdate(): void
    {
        $product = $this->createActiveProduct('کالای کش', price: 1_000_000, stock: 5);

        // فهرست کش می‌شود
        $this->get('/shop')->assertOk()->assertSee('کالای کش');

        // عنوان عوض می‌شود (از راه سرویس ادمین → invalidate)
        $service = $this->app()->make(AdminProductService::class);
        $service->update($product, [
            'title' => 'کالای کش به‌روز',
            'category_id' => $product->categoryId(),
            'price' => 1_000_000,
            'status' => 'active',
        ]);

        $this->get('/shop')->assertOk()->assertSee('کالای کش به‌روز')->assertDontSee('کالای کش</a>');
    }

    public function testProductUpdatedEventPublished(): void
    {
        $captured = [];
        \Tavaan\Core\Facade\Facades\Event::listen('shop.product.updated', static function (string $event, mixed $payload) use (&$captured): void {
            $captured[] = $payload;
        });

        $product = $this->createActiveProduct('رویداد محصول', price: 10_000, stock: 1);
        $service = $this->app()->make(AdminProductService::class);
        $service->setStatus($product, 'archived');

        self::assertNotEmpty($captured);
        $last = $captured[\count($captured) - 1];
        self::assertSame('archived', $last['status']);
        self::assertSame($product->id(), $last['product_id']);
    }

    // ─── RBAC و مرزها ─────────────────────────────────────────────────────

    public function testCustomerCannotSeeOthersOrdersInStorefront(): void
    {
        $product = $this->createActiveProduct('کالای حریم', price: 100_000, stock: 5);
        $customerA = $this->user('pa@test.dev', 'الف', role: 'customer');
        $customerB = $this->user('pb@test.dev', 'ب', role: 'customer');

        $order = $this->placeOrder($customerA, $product, quantity: 1);

        $this->actingAs($customerB)->get('/shop/orders/' . $order->orderNumber())->assertForbidden();
        $this->actingAs($customerB)->post('/shop/orders/' . $order->orderNumber() . '/cancel')->assertForbidden();
        $this->actingAs($customerA)->get('/shop/orders/' . $order->orderNumber())->assertOk();
    }

    public function testGuestCheckoutRedirectsToForbidden(): void
    {
        $product = $this->createActiveProduct('کالای مهمان', price: 100_000, stock: 2);
        $this->post('/shop/cart/items', ['product_id' => $product->id(), 'quantity' => 1])->assertRedirect();
        $this->post('/shop/checkout', $this->addressData())->assertForbidden();
    }

    public function testOrderStateMachineRejectsImpossibleTransitions(): void
    {
        $product = $this->createActiveProduct('کالای ماشین حالت', price: 100_000, stock: 5);
        $customer = $this->user('sm@test.dev', 'مشتری', role: 'customer');
        $manager = $this->user('smm@test.dev', 'مدیر', role: 'shop-manager');

        $order = $this->placeOrder($customer, $product, quantity: 1);   // pending

        // ship از pending → نامجاز؛ flash error و وضعیت ثابت
        $this->actingAs($manager)
            ->post('/admin/shop/orders/' . $order->id() . '/status', ['action' => 'ship'])
            ->assertRedirect();
        $orders = $this->app()->make(OrderRepositoryInterface::class);
        self::assertSame(OrderStatus::Pending, $orders->findById($order->id())->status());

        // refund از pending → نامجاز (پرداخت نشده)
        $this->actingAs($manager)
            ->post('/admin/shop/orders/' . $order->id() . '/refund')
            ->assertRedirect();
        self::assertSame(OrderStatus::Pending, $orders->findById($order->id())->status());
    }

    // ─── کمکی‌ها ──────────────────────────────────────────────────────────

    private function seedRbac(): void
    {
        $permissions = [
            'shop.product.view', 'shop.product.create', 'shop.product.update', 'shop.product.delete',
            'shop.order.view', 'shop.order.update', 'shop.order.refund', 'shop.order.delete',
            'shop.inventory.manage', 'shop.discount.manage', 'shop.report.view',
            'shop.comment.view', 'shop.comment.approve', 'shop.comment.delete',
        ];
        foreach ($permissions as $key) {
            if (!$this->permissionExists($key)) {
                $this->roles->createPermission($key, 'tavaan.shop');
            }
        }

        $rolePerms = [
            'customer' => [],
            'shop-operator' => ['shop.product.view', 'shop.product.create', 'shop.product.update', 'shop.order.view', 'shop.comment.view'],
            'shop-manager' => [
                'shop.product.view', 'shop.product.create', 'shop.product.update', 'shop.product.delete',
                'shop.order.view', 'shop.order.update', 'shop.order.refund', 'shop.order.delete',
                'shop.discount.manage', 'shop.report.view',
                'shop.comment.view', 'shop.comment.approve', 'shop.comment.delete',
            ],
            'inventory-keeper' => ['shop.product.view', 'shop.inventory.manage'],
            'admin' => $permissions,
        ];
        foreach ($rolePerms as $roleKey => $perms) {
            if ($this->roles->findRole($roleKey) === null) {
                $this->roles->create($roleKey, $roleKey);
            }
            foreach ($perms as $perm) {
                $this->roles->grantPermissionToRole($roleKey, $perm);
            }
        }
    }

    private function permissionExists(string $key): bool
    {
        $row = DB::table('tvn_permissions')->where('key', $key)->first();

        return $row !== null;
    }

    private function user(string $email, string $name, string $role = 'customer'): User
    {
        $user = $this->users->create(name: $name, email: $email, plainPassword: 'test-12345');
        $this->roles->assign($user->id, $role);

        return $user;
    }

    private function createCategory(string $title, string $slug): int
    {
        $exists = DB::table('shop_categories')->where('slug', $slug)->first();
        if ($exists !== null) {
            return (int) $exists['id'];
        }

        return (int) DB::table('shop_categories')->insertGetId([
            'title' => $title,
            'slug' => $slug,
            'parent_id' => null,
            'description' => null,
            'created_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v'),
            'updated_at' => null,
        ]);
    }

    private function createProduct(string $title, int $price, int $stock, bool $active = true, ?int $salePrice = null, int $lowThreshold = 5): Product
    {
        $categoryId = $this->createCategory('عمومی', 'general');
        $repo = $this->app()->make(ProductRepositoryInterface::class);

        $product = Product::draft(
            slug: \Modules\Content\Domain\ValueObjects\Slug::of($title)->toString(),
            title: $title,
            categoryId: $categoryId,
            price: Money::of($price, 'IRR'),
            summary: '<p>خلاصهٔ ' . $title . '</p>',
            description: '<p>توضیحات ' . $title . '</p>',
            stock: $stock,
        );
        if ($salePrice !== null) {
            $product->revise(
                title: $title,
                summary: '<p>خلاصهٔ ' . $title . '</p>',
                description: '<p>توضیحات ' . $title . '</p>',
                categoryId: $categoryId,
                price: Money::of($price, 'IRR'),
                salePrice: Money::of($salePrice, 'IRR'),
                lowStockThreshold: $lowThreshold,
            );
        } elseif ($lowThreshold !== 5) {
            $product->revise(
                title: $title,
                summary: '<p>خلاصهٔ ' . $title . '</p>',
                description: '<p>توضیحات ' . $title . '</p>',
                categoryId: $categoryId,
                price: Money::of($price, 'IRR'),
                salePrice: null,
                lowStockThreshold: $lowThreshold,
            );
        }
        if ($active) {
            $product->activate();
        }
        $product->pullEvents();

        $repo->save($product);

        return $product;
    }

    private function createActiveProduct(string $title, int $price, int $stock, ?int $salePrice = null, int $lowThreshold = 5): Product
    {
        return $this->createProduct($title, $price, $stock, active: true, salePrice: $salePrice, lowThreshold: $lowThreshold);
    }

    private function stockOf(string $productId): int
    {
        return (int) (DB::table('shop_products')->where('id', $productId)->first()['stock'] ?? 0);
    }

    /** @return array<string, string> */
    private function addressData(): array
    {
        return [
            'receiver' => 'سارا احمدی',
            'phone' => '09121234567',
            'province' => 'تهران',
            'city' => 'تهران',
            'address' => 'خیابان ولیعصر، کوچهٔ بهار، پلاک ۱۲',
            'postal_code' => '1435863911',
        ];
    }

    private function placeOrder(User $customer, Product $product, int $quantity): \Modules\Shop\Domain\Entities\Order
    {
        $this->actingAs($customer)->post('/shop/cart/items', [
            'product_id' => $product->id(),
            'quantity' => $quantity,
        ])->assertRedirect();

        $this->post('/shop/checkout', $this->addressData())->assertRedirect();

        return $this->latestOrder();
    }

    private function placeOrderFromCurrentCart(User $customer): \Modules\Shop\Domain\Entities\Order
    {
        $this->actingAs($customer)->post('/shop/checkout', $this->addressData())->assertRedirect();

        return $this->latestOrder();
    }

    private function placePaidOrder(User $customer, Product $product, int $quantity): \Modules\Shop\Domain\Entities\Order
    {
        $order = $this->placeOrder($customer, $product, $quantity);

        // پرداخت از راه خود سرویس checkout (sandbox — مسیر HTTP کامل در E2E-01 پوشش داده شد)
        $checkout = $this->app()->make(CheckoutService::class);
        $result = $checkout->startPayment($order, 'http://localhost/shop/pay/callback');
        $checkout->handleReturn($result['authority'], []);

        $paid = $this->app()->make(OrderRepositoryInterface::class)->findById($order->id());
        self::assertNotNull($paid);
        self::assertSame(OrderStatus::Paid, $paid->status());

        return $paid;
    }

    private function latestOrder(): \Modules\Shop\Domain\Entities\Order
    {
        $row = DB::table('shop_orders')->orderByDesc('created_at')->orderByDesc('id')->first();
        self::assertNotNull($row, 'سفارشی ساخته نشد');

        $order = $this->app()->make(OrderRepositoryInterface::class)->findById((string) $row['id']);
        self::assertNotNull($order);

        return $order;
    }
}
