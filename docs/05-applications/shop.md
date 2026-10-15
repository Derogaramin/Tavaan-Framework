# DOC-093 — کاربرد فروشگاه (E-Commerce / Shop) 🛒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-093 |
| سطح | متوسط → حرفه‌ای |
| معماری | `modular` (قابل ارتقا به `hexagonal`) |
| ماژول‌ها | core-user, shop, payment, inventory, shipping, tax, crm-lite |
| موتورها | media, search, queue, mail, notification, report, audit |

---

## ۱. دامنهٔ کسب‌وکار (Domain)

### ۱٫۱ Bounded Contextها
```
┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│  Catalog    │  │   Cart &    │  │  Payment &  │  │  Inventory  │
│ (محصول/دسته)│  │   Order     │  │  Checkout   │  │  (موجودی)    │
└─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│  Shipping   │  │    Tax      │  │  Customer   │
│ (ارسال)     │  │  (مالیات)   │  │  (CRM-lite) │
└─────────────┘  └─────────────┘  └─────────────┘
```

### ۱٫۲ Aggregateها
| Aggregate | Root | Entities/VO | رویدادها |
|---|---|---|---|
| Product | `Product` | Variant, Price, Attribute, Media | ProductWasCreated/Updated |
| Category | `Category` | — | — |
| Cart | `Cart` | CartItem, Quantity | CartWasUpdated |
| Order | `Order` | OrderItem, Address, Money | OrderWasPlaced/Paid/Shipped/Canceled |
| Payment | `PaymentTransaction` | Amount, Gateway | PaymentSucceeded/Failed |
| Inventory | `Stock` | Quantity, Warehouse | StockWasAdjusted |

---

## ۲. ماژول `shop` — ساختار

```
modules/shop/
├── module.json
├── src/
│   ├── Contracts/
│   │   ├── ProductReaderInterface.php
│   │   ├── CartServiceInterface.php
│   │   ├── OrderRepositoryInterface.php
│   │   ├── InventoryServiceInterface.php
│   │   └── Events/{OrderWasPlaced,OrderWasPaid,ProductWasUpdated}.php
│   ├── Domain/
│   │   ├── Entities/{Product,ProductVariant,Category,Cart,CartItem,Order,OrderItem}.php
│   │   ├── ValueObjects/{Money,Price,Sku,Quantity,Address,OrderStatus}.php
│   │   ├── Services/{PricingService,CartService,OrderService,InventoryService}.php
│   │   ├── Events/{OrderWasPlaced,OrderWasPaid,...}.php
│   │   ├── Policies/{OrderPolicy,ProductPolicy}.php
│   │   └── Repositories/{OrderRepositoryInterface,ProductRepositoryInterface}.php
│   ├── Application/
│   │   ├── UseCases/{PlaceOrderUseCase,AddToCartUseCase,CheckoutUseCase}.php
│   │   ├── Commands/{PlaceOrderCommand,ReserveStockCommand}.php
│   │   ├── Queries/{ProductListQuery,OrderListQuery}.php
│   │   ├── Handlers/{PlaceOrderHandler,...}.php
│   │   ├── Dtos/{PlaceOrderDto,CartDto}.php
│   │   └── Listeners/{SendOrderInvoiceJob,UpdateInventoryListener,IndexProductListener}.php
│   ├── Http/
│   │   ├── Controllers/{ProductController,CartController,CheckoutController,OrderController}.php
│   │   ├── Controllers/Admin/{ProductAdminController,OrderAdminController}.php
│   │   ├── Requests/{StoreOrderRequest,AddToCartRequest}.php
│   │   ├── Resources/{ProductResource,OrderResource,CartResource}.php
│   │   └── Widgets/{RecentOrdersWidget,CartSummaryWidget,SalesChartWidget}.php
│   ├── Infrastructure/
│   │   ├── Persistence/{DatabaseOrderRepository,DatabaseProductRepository,OrderMapper}.php
│   │   ├── Gateways/{ShippingGateway,TaxGateway}.php
│   │   └── Search/{ProductSearchIndexer}.php
│   └── Providers/{ShopServiceProvider,RouteServiceProvider,EventServiceProvider}.php
├── config/shop.php
├── routes/{web,api,admin}.php
├── views/{front,admin,widgets,mails}/
├── lang/{fa,en}/shop.php
├── migrations/
└── seeds/
```

---

## ۳. مدل داده (Schema)

### ۳٫۱ جداول اصلی
```sql
-- محصولات
CREATE TABLE shop_products (
  id CHAR(26) PRIMARY KEY,
  slug VARCHAR(160) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  summary TEXT, description LONGTEXT,
  category_id BIGINT UNSIGNED NOT NULL,
  brand_id BIGINT UNSIGNED NULL,
  price DECIMAL(18,4) NOT NULL DEFAULT 0,
  sale_price DECIMAL(18,4) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'IRR',
  sku VARCHAR(64) UNIQUE,
  stock INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',   -- draft|active|archived
  is_featured TINYINT(1) DEFAULT 0,
  attributes JSON,                                -- ویژگی‌های پویا
  weight DECIMAL(10,3) NULL,                      -- برای ارسال
  view_count INT DEFAULT 0,
  sold_count INT DEFAULT 0,
  rating_avg DECIMAL(3,2) DEFAULT 0,
  published_at DATETIME(3) NULL,
  created_by BIGINT UNSIGNED, updated_by BIGINT UNSIGNED,
  created_at DATETIME(3) NOT NULL, updated_at DATETIME(3),
  deleted_at DATETIME(3),
  KEY idx_status_created (status, created_at),
  KEY idx_category (category_id, status),
  FULLTEXT KEY ft_search (title, summary)
) ENGINE=InnoDB;

-- تنوع محصول (سایز/رنگ)
CREATE TABLE shop_product_variants (
  id CHAR(26) PRIMARY KEY, product_id CHAR(26) NOT NULL,
  sku VARCHAR(64), title VARCHAR(190),
  price_delta DECIMAL(18,4) DEFAULT 0, stock INT DEFAULT 0,
  options JSON,                                   -- {"color":"قرمز","size":"L"}
  KEY idx_product (product_id)
) ENGINE=InnoDB;

-- سبد خرید
CREATE TABLE shop_carts (
  id CHAR(26) PRIMARY KEY, user_id BIGINT UNSIGNED NULL,
  session_id VARCHAR(64) NULL, coupon_code VARCHAR(50) NULL,
  total DECIMAL(18,4) DEFAULT 0, expires_at DATETIME(3),
  created_at DATETIME(3) NOT NULL, updated_at DATETIME(3),
  KEY idx_user (user_id), KEY idx_session (session_id)
) ENGINE=InnoDB;

CREATE TABLE shop_cart_items (
  id CHAR(26) PRIMARY KEY, cart_id CHAR(26) NOT NULL,
  product_id CHAR(26) NOT NULL, variant_id CHAR(26) NULL,
  quantity INT NOT NULL DEFAULT 1, unit_price DECIMAL(18,4) NOT NULL,
  KEY idx_cart (cart_id)
) ENGINE=InnoDB;

-- سفارش
CREATE TABLE shop_orders (
  id CHAR(26) PRIMARY KEY, order_number VARCHAR(32) UNIQUE,
  customer_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  subtotal DECIMAL(18,4), discount DECIMAL(18,4) DEFAULT 0,
  tax DECIMAL(18,4) DEFAULT 0, shipping_cost DECIMAL(18,4) DEFAULT 0,
  total DECIMAL(18,4) NOT NULL, currency CHAR(3) DEFAULT 'IRR',
  shipping_address JSON, billing_address JSON,
  payment_method VARCHAR(50), tracking_code VARCHAR(100),
  notes TEXT, placed_at DATETIME(3), paid_at DATETIME(3),
  shipped_at DATETIME(3), delivered_at DATETIME(3), canceled_at DATETIME(3),
  created_at DATETIME(3) NOT NULL, updated_at DATETIME(3),
  KEY idx_customer (customer_id, created_at), KEY idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE shop_order_items (
  id CHAR(26) PRIMARY KEY, order_id CHAR(26) NOT NULL,
  product_id CHAR(26), variant_id CHAR(26) NULL,
  title VARCHAR(255), sku VARCHAR(64),
  quantity INT NOT NULL, unit_price DECIMAL(18,4) NOT NULL,
  discount DECIMAL(18,4) DEFAULT 0, total DECIMAL(18,4) NOT NULL,
  KEY idx_order (order_id)
) ENGINE=InnoDB;
```

---

## ۴. جریان‌های اصلی (User Journeys)

### ۴٫۱ افزودن به سبد
```
کاربر → POST /shop/cart/items {product_id, qty}
  → AddToCartUseCase
    → بررسی موجودی (InventoryService.reserve)
    → محاسبهٔ قیمت (PricingService)
    → Cart.addItem()
    → Event: CartWasUpdated
  → پاسخ: سبد به‌روزشده
```

### ۴٫۲ ثبت سفارش (PlaceOrder)
```
کاربر → POST /shop/checkout
  → PlaceOrderUseCase (در تراکنش)
    1. Cart → Order (Domain)
    2. محاسبهٔ مالی: subtotal + tax + shipping - discount = total
    3. InventoryService.reserve() (کاهش موجودی)
    4. OrderRepository.save()
    5. Outbox: OrderWasPlaced
  → redirect به درگاه پرداخت
```

```php
final class PlaceOrderUseCase
{
    public function execute(PlaceOrderDto $dto): OrderResult
    {
        return $this->tx->run(function () use ($dto): OrderResult {
            $cart = $this->carts->findActive($dto->userId);
            if ($cart->isEmpty()) {
                throw new EmptyCartException(messageId: 'shop.cart.empty');
            }

            $order = Order::place(
                cart: $cart,
                customer: $dto->customer,
                shippingAddress: $dto->shippingAddress,
                paymentMethod: $dto->paymentMethod,
            );

            // مالیات و ارسال
            $order->applyTax($this->taxService->calculate($order));
            $order->applyShipping($this->shippingService->quote($order));

            // موجودی
            $this->inventory->reserve($order->items());   // اگر نبود → OutOfStockException

            $this->orders->save($order);
            $this->carts->clear($cart);
            $this->events->dispatch(OrderWasPlaced::from($order));

            return new OrderResult($order, $order->total());
        }, attempts: 2);   // retry در deadlock
    }
}
```

### ۴٫۳ پرداخت و تأیید
```
→ Payment::purchase(order) → redirect به درگاه
→ بازگشت: /payment/callback
  → Verify از درگاه
  → اگر موفق:
      OrderWasPaid → Listenerها:
        - SendOrderInvoiceJob (mail)
        - UpdateInventoryListener (قطعی‌کردن موجودی)
        - NotifyCustomerListener (sms/mail)
        - IndexOrderListener (search)
        - LoyaltyPointsListener (crm-lite)
```

---

## ۵. نقش‌ها و مجوزها

| نقش | مجوزها |
|---|---|
| `customer` | مشاهدهٔ محصول، سبد، سفارش خود |
| `shop-operator` | مدیریت محصول، مشاهدهٔ سفارش |
| `shop-manager` | + تغییر وضعیت سفارش، استرداد، گزارش |
| `inventory-keeper` | مدیریت موجودی |
| `admin` | همه |

مجوزها (کلید): `shop.product.{view,create,update,delete}`, `shop.order.{view,update,refund,delete}`,
`shop.inventory.manage`, `shop.report.view`, `shop.discount.manage`.

---

## ۶. رویدادها

| رویداد | تولیدکننده | مصرف‌کنندگان |
|---|---|---|
| `shop.product.updated` | shop | search (reindex), cache (invalidate) |
| `shop.cart.updated` | shop | notification (abandoned cart reminder) |
| `shop.order.placed` | shop | inventory, notification |
| `shop.order.paid` | shop | mail, sms, inventory, crm, report, loyalty |
| `shop.order.shipped` | shop | notification, sms |
| `shop.order.canceled` | shop | inventory (release), refund |
| `shop.stock.low` | inventory | notification (به مدیر) |

---

## ۷. API (نمونه)
```
GET    /api/v1/shop/products              فهرست (با filter/sort/paginate)
GET    /api/v1/shop/products/{slug}       جزئیات
GET    /api/v1/shop/categories            دسته‌ها
GET    /api/v1/shop/cart                  سبد جاری
POST   /api/v1/shop/cart/items            افزودن
DELETE /api/v1/shop/cart/items/{id}       حذف
POST   /api/v1/shop/checkout              ثبت سفارش
GET    /api/v1/shop/orders                سفارش‌های کاربر
GET    /api/v1/shop/orders/{id}           جزئیات سفارش
POST   /api/v1/shop/orders/{id}/cancel    لغو
```

---

## ۸. ویژگی‌های پیشرفته

| ویژگی | موتور/ماژول |
|---|---|
| جست‌وجوی محصول (فارسی) | search (native index) |
| تخفیف/کوپن | shop (CouponService) |
| مالیات | tax |
| ارسال چند‌نرخی | shipping |
| نقد و بررسی | comment + media |
| محصولات مرتبط | shop (recommendation) |
| سبد رهاشده (Abandoned) | queue + notification |
| گزارش فروش | report |
| موجودی چند‌انبار | inventory |
| چند‌فروشندگی (Marketplace) | shop + vendor module |

---

## ۹. نکات کارایی

| چالش | راه‌حل |
|---|---|
| فهرست محصول با هزاران آیتم | Cache + Cursor pagination + search index |
| محاسبهٔ قیمت پویا | PricingService با cache |
| موجودی همزمان (race) | `SELECT FOR UPDATE` + optimistic lock |
| تصاویر محصول | media variants (thumbnail/webp) + CDN |
| گزارش فروش سنگین | read model (CQRS) + report engine |

---

## ۱۰. تست (سناریوهای E2E)

| ID | سناریو |
|---|---|
| E2E-01 | مرور محصول → افزودن به سبد → checkout → پرداخت موفق → تأیید سفارش |
| E2E-02 | موجودی ناکافی → خطا در reserve |
| E2E-03 | پرداخت ناموفق → سفارش pending + retry |
| E2E-04 | لغو سفارش → بازگشت موجودی + refund |
| E2E-05 | کوپن تخفیف → محاسبهٔ درست |
| E2E-06 | مالیات و ارسال در total |
| E2E-07 | جست‌وجوی فارسی محصول |
| E2E-08 | همزمانی: دو کاربر آخرین موجودی |

---

## ۱۱. معیار پذیرش 🧪
- AC-SHOP-01: سناریوهای E2E §10 سبز.
- AC-SHOP-02: پرداخت با درگاه واقعی (sandbox) کار می‌کند.
- AC-SHOP-03: موجودی در حالت همزمانی درست می‌ماند.
- AC-SHOP-04: جست‌وجوی فارسی با نرمال‌سازی.
- AC-SHOP-05: گزارش فروش با report engine.
- AC-SHOP-06: ۰ نقض مرز ماژول (`arch:check`).
