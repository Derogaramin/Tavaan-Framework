# ماژول فروشگاه — `modules/shop` (DOC-093)

| متادیتا | مقدار |
|---|---|
| شناسه | `tavaan.shop` |
| فضای‌نام | `Modules\Shop\` |
| جداول | `shop_products` `shop_product_variants` `shop_categories` `shop_carts` `shop_cart_items` `shop_orders` `shop_order_items` `shop_coupons` `shop_comments` `shop_stock_movements` |
| کیت | `kits/shop` (DOC-090) |
| وضعیت | M9 — پیاده‌سازی گام ۳ |

## ۱. دامنه

پیاده‌سازی DOC-093 در ۷ محدودهٔ مفهومی: **Catalog** (محصول/variant/دسته)،
**Cart** (مهمان+کاربر با ادغام هنگام ورود)، **Order** (workflow شش‌وضعیتی)،
**Payment** (پورت + sandbox/engine)، **Inventory** (reserve/release اتمیک با
حرکت‌نامه)، **Tax/Shipping** (PricingService) و **Customer** (سفارش‌های من +
نقد و بررسی).

```
src/
├── Contracts/            PaymentGatewayPort • ProductReaderInterface (عمومی)
├── Domain/
│   ├── Entities/         Product • ProductVariant • ShopCategory • Cart • CartItem • Order • OrderItem • Coupon
│   ├── Enums/            ProductStatus (draft|active|archived) • OrderStatus (ماتریس انتقال)
│   ├── ValueObjects/     Money (ADR-0013 int minor-unit) • Address
│   ├── Events/           ۸ رویداد نام‌دار (shop.product.updated … shop.stock.low)
│   ├── Services/         PricingService • InventoryService • CouponService • InventoryException
│   └── Repositories/     ۵ پورت ماندگاری
├── Application/UseCases/ CatalogService • CartService • PlaceOrderService • CheckoutService • OrderWorkflowService • ReviewService • AdminProductService
├── Http/Controllers/     ویترین (۴) • Admin (۵) • Api (۱ — DOC-093 §7)
├── Infrastructure/       Persistence (۶ آداپتر) • Gateways (Sandbox/Engine) • Search • Notifications • ProductReaderAdapter
└── Policies/             ProductPolicy • OrderPolicy (اعلام از config/rbac.php)
```

## ۲. جریان‌های کلیدی

- **افزودن به سبد** (`POST /shop/cart/items`): بررسی فعال‌بودن محصول و
  موجودی (product یا variant) → snapshot قیمت → ادغام قلم تکراری →
  رویداد `shop.cart.updated`.
- **ثبت سفارش** (`POST /shop/checkout` → `PlaceOrderService`): در یک
  تراکنش DB — refresh قیمت‌ها از کاتالوگ معتبر، کوپن → discount،
  `shippingQuote` + `tax`، `Order::place`، **`InventoryService::reserve`
  با UPDATE اتمیک شرطی** (همه-یا-هیچ + rollback داخلی)، ذخیره، تخلیهٔ
  سبد، مصرف کوپن، رویداد `shop.order.placed`.
- **پرداخت** (`GET /shop/pay/{number}` → `CheckoutService`): purchase از
  پورت درگاه → ذخیرهٔ authority → redirect. بازگشت
  (`GET /shop/pay/callback`) → verify **با مبلغ سفارش محلی** → موفق:
  `markPaid` + رویدادها (فاکتور/notify) • ناموفق: سفارش pending می‌ماند
  و retry مجاز است (E2E-03).
- **لغو/استرداد**: `cancel` → آزادسازی موجودی + refund خودکار سفارش
  پرداخت‌شده؛ `refund` مدیرتی با مجوز `shop.order.refund`.

## ۳. درگاه پرداخت

| driver | آداپتر | کاربرد |
|---|---|---|
| `sandbox` (پیش‌فرض) | `SandboxPaymentGateway` | dev/CI/دمو — قطعی، بدون شبکه؛ صفحهٔ شبیه‌ساز `/shop/pay/sandbox/{authority}` با دو دکمهٔ موفق/ناموفق |
| `engine` | `EnginePaymentGateway` | درگاه‌های واقعی موتور payment (zarinpal/idpay/…) از راه Facade — `shop.payment.gateway` |

چرا پورت ماژولی؟ قرارداد `GatewayInterface` هسته به VOهای موتور تایپ شده
(TECH-DEBT-ARCH-01) و ماژول اجازهٔ ارجاع به `Tavaan\Engine\*` ندارد
(ARCH-003)؛ پس ماژول پورت خودش را با انواع ابتدایی تعریف می‌کند.

## ۴. همزمانی موجودی (AC-SHOP-03)

کاهش موجودی **هرگز** read-modify-write نیست:

```sql
UPDATE shop_products SET stock = stock - :q WHERE id = :id AND stock >= :q
```

`affected rows < 1` → `InventoryException::outOfStock` → کل تراکنش
rollback. روی SQLite/MySQL/PG یکسان کار می‌کند و نیاز به FOR UPDATE ندارد.
هر حرکت در `shop_stock_movements` با delta/stock_after/reason ثبت می‌شود.

## ۵. نقش‌ها و مجوزها (DOC-093 §5)

کلیدها در manifest — `shop.product.{view,create,update,delete}`،
`shop.order.{view,update,refund,delete}`، `shop.inventory.manage`،
`shop.discount.manage`، `shop.report.view`، `shop.comment.*`. نقش‌های
کیت (customer/shop-operator/shop-manager/inventory-keeper/admin) در
`kits/shop/seed/roles.json`.

## ۶. رویدادها

| رویداد | شنونده‌ها |
|---|---|
| `shop.product.updated` | ProductSearchIndexer + flush کش `shop` |
| `shop.order.placed` | (مصرف‌کنندهٔ خارجی: crm/report — آماده) |
| `shop.order.paid/shipped/canceled` | OrderNotifier (mail+database به مشتری) |
| `shop.stock.low` | notification به مدیر (از راه مصرف‌کنندهٔ رویداد) |

## ۷. مسیرها

- ویترین: `/shop` `/shop/search` `/shop/categories/{slug}` `/shop/products/{slug}` `/shop/cart` `/shop/checkout` `/shop/pay/*` `/shop/orders`
- API (DOC-093 §7): `/api/v1/shop/{products,categories,cart,checkout,orders}`
- پنل: `/admin/shop/{products,categories,orders,coupons,comments}` + `orders/sales.csv`

## ۸. نکتهٔ انطباق پول (ADR-0013)

DOC-093 §3 ستون‌های پول را `DECIMAL(18,4)` نوشته، ولی ADR-0013 (و الگوی
مثبت‌شدهٔ موتور ORM) پول را **int minor-unit** الزام می‌کند. پیاده‌سازی
ستون‌ها را `BIGINT` گرفت و `Money` ماژول تمام محاسبات را int انجام می‌دهد
(گرد درصد به پایین — به نفع مشتری). انطباق در گزارش M9 گام ۳ ثبت شد.

## ۹. تست

`modules/shop/tests/` — Unit (Money/OrderStatus/Coupon/Order math) و
Feature (ShopE2ETest: سناریوهای DOC-093 §10 — E2E-01..08).
