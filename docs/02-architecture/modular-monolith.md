# DOC-022 — معماری ماژولار مونولیت (Modular Monolith) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-022 |
| وضعیت | Approved v1.0 |
| والد | DOC-020، DOC-021 |
| موتور مسئول | `engines/module` (بخشی از `optimizer`/`core`) |

---

## ۱. چرا ماژولار مونولیت؟

| گزینه | مزیت | عیب | انتخاب تَوان |
|---|---|---|---|
| Big Ball of Mud | سریع در ابتدا | غیرقابل نگهداشت پس از ۶ ماه | ❌ |
| Monolith لایه‌ای | ساده، Deploy آسان | مرز دامنه ضعیف | ⚠️ برای `mvc` mode |
| **Modular Monolith** | مرز دامنهٔ قوی + Deploy ساده + تست آسان | نیازمند انضباط | ✅ **پیش‌فرض** |
| Microservices از روز اول | مقیاس مستقل | پیچیدگی عملیاتی، Distributed Monolith | ❌ (مگر پس از S2) |

**نتیجه:** ماژولار مونولیت با **مرزهای سخت** و **قابلیت استخراج** (Extractability) به سرویس.

---

## ۲. آناتومی یک ماژول

```
modules/shop/
├── module.json                       ← Manifest (الزامی)
├── composer.json                     ← اختیاری (فقط انتشار)
├── README.md
├── src/
│   ├── Contracts/                    ← 🔓 تنها سطح عمومی ماژول
│   │   ├── ProductReaderInterface.php
│   │   ├── CartServiceInterface.php
│   │   ├── Events/                   ← رویدادهای عمومی (Public API)
│   │   │   ├── OrderWasPlaced.php
│   │   │   └── OrderWasPaid.php
│   │   └── Dtos/                     ← DTOهای عمومی
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   ├── Requests/                 ← FormRequest
│   │   ├── Resources/                ← API Resource
│   │   └── Widgets/                  ← HMVC Widgets
│   ├── Console/Commands/
│   ├── Application/
│   │   ├── Commands/                 ← CQRS Command
│   │   ├── Queries/
│   │   ├── Handlers/
│   │   ├── UseCases/
│   │   ├── Dtos/
│   │   └── Listeners/
│   ├── Domain/
│   │   ├── Entities/                 ← Aggregate Roots + Entities
│   │   ├── ValueObjects/
│   │   ├── Services/                 ← Domain Services
│   │   ├── Events/                   ← Domain Events (داخلی)
│   │   ├── Exceptions/
│   │   ├── Policies/
│   │   └── Repositories/             ← 🔑 Interface فقط (Impl در Infrastructure)
│   ├── Infrastructure/
│   │   ├── Persistence/              ← Repository Impl + Mapper
│   │   ├── Cache/
│   │   ├── Search/
│   │   ├── Gateways/                 ← Payment, Shipping, SMS
│   │   └── External/
│   ├── Providers/
│   │   ├── ShopServiceProvider.php
│   │   ├── RouteServiceProvider.php
│   │   └── EventServiceProvider.php
│   └── Support/
├── config/shop.php
├── routes/
│   ├── web.php
│   ├── api.php
│   ├── admin.php
│   └── console.php
├── views/                            ← namespace: shop::
├── lang/{fa,en}/shop.php
├── migrations/
├── seeds/
├── tests/{Unit,Feature,Benchmark}/
├── docs/
│   ├── README.md
│   ├── domain-model.md
│   └── api.md
└── public/                           ← assetهای ماژول (در build به public/ کپی می‌شود)
```

---

## ۳. Manifest ماژول (`module.json`) — Schema کامل

```json
{
  "$schema": "https://docs.tavaan.dev/schemas/module.v1.json",
  "id": "tavaan.shop",
  "name": "Shop",
  "title_fa": "فروشگاه",
  "title_en": "Shop",
  "version": "1.0.0",
  "type": "module",
  "description": "ماژول فروشگاه اینترنتی: محصول، سبد، سفارش، پرداخت، موجودی",
  "authors": [{"name": "Tavaan Team", "email": "team@tavaan.dev"}],
  "license": "MIT",
  "homepage": "https://docs.tavaan.dev/modules/shop",

  "requires": {
    "tavaan": ">=1.0.0",
    "php": ">=8.2.0",
    "engines": ["http", "router", "database", "orm", "validation", "auth", "rbac",
                "cache", "queue", "event", "view", "i18n", "payment", "media", "search"],
    "extensions": {"pdo": "*", "gd": "*"},
    "modules": ["tavaan.core-user", "tavaan.tax"]
  },
  "conflicts": {"modules": ["tavaan.shop-legacy"]},
  "replaces":  {"modules": []},

  "autoload": {
    "psr-4": {"Modules\\Shop\\": "src/"},
    "files": ["src/Support/helpers.php"]
  },

  "providers": [
    "Modules\\Shop\\Providers\\ShopServiceProvider",
    "Modules\\Shop\\Providers\\RouteServiceProvider",
    "Modules\\Shop\\Providers\\EventServiceProvider"
  ],

  "deferred": false,
  "priority": 100,

  "routes": {
    "web":     "routes/web.php",
    "api":     "routes/api.php",
    "admin":   "routes/admin.php",
    "console": "routes/console.php"
  },

  "views":   {"namespace": "shop", "path": "views"},
  "lang":    {"path": "lang", "namespaces": {"shop": "lang"}},
  "migrations": {"path": "migrations"},
  "seeds":      {"path": "seeds", "classes": ["ShopDatabaseSeeder"]},
  "config":     {"path": "config", "merge": true},
  "assets":     {"path": "public", "publish_to": "modules/shop"},

  "widgets": {
    "orders/recent": "Modules\\Shop\\Http\\Widgets\\RecentOrdersWidget",
    "cart/summary":  "Modules\\Shop\\Http\\Widgets\\CartSummaryWidget"
  },

  "commands": [
    "Modules\\Shop\\Console\\Commands\\RecalculateStockCommand",
    "Modules\\Shop\\Console\\Commands\\ExportOrdersCommand"
  ],

  "jobs": [
    "Modules\\Shop\\Application\\Listeners\\SendOrderInvoiceJob"
  ],

  "events": {
    "published": [
      {"name": "shop.order.placed",  "class": "Modules\\Shop\\Contracts\\Events\\OrderWasPlaced",  "version": 1},
      {"name": "shop.order.paid",    "class": "Modules\\Shop\\Contracts\\Events\\OrderWasPaid",    "version": 1},
      {"name": "shop.product.updated","class": "Modules\\Shop\\Contracts\\Events\\ProductWasUpdated","version": 1}
    ],
    "subscribed": [
      {"name": "payment.transaction.succeeded", "listener": "Modules\\Shop\\Application\\Listeners\\MarkOrderPaidListener"},
      {"name": "auth.user.deleted",             "listener": "Modules\\Shop\\Application\\Listeners\\AnonymizeOrdersListener"}
    ]
  },

  "permissions": {
    "group": {"id": "shop", "title_fa": "فروشگاه"},
    "items": [
      {"key": "shop.product.view",   "title_fa": "مشاهدهٔ محصول"},
      {"key": "shop.product.create", "title_fa": "ایجاد محصول"},
      {"key": "shop.product.update", "title_fa": "ویرایش محصول"},
      {"key": "shop.product.delete", "title_fa": "حذف محصول"},
      {"key": "shop.order.view",     "title_fa": "مشاهدهٔ سفارش"},
      {"key": "shop.order.refund",   "title_fa": "استرداد سفارش"}
    ]
  },

  "roles": [
    {"key": "shop-manager", "title_fa": "مدیر فروشگاه", "permissions": ["shop.*"]},
    {"key": "shop-operator","title_fa": "اپراتور فروشگاه", "permissions": ["shop.product.view","shop.order.view"]}
  ],

  "navigation": [
    {"id": "shop", "title_fa": "فروشگاه", "icon": "cart", "order": 20,
     "children": [
       {"id": "shop.products", "title_fa": "محصولات", "route": "shop.admin.product.index", "permission": "shop.product.view"},
       {"id": "shop.orders",   "title_fa": "سفارش‌ها", "route": "shop.admin.order.index",   "permission": "shop.order.view"}
     ]}
  ],

  "settings": {
    "group": "shop",
    "items": [
      {"key": "shop.currency.default", "type": "string", "default": "IRR", "title_fa": "واحد پول پیش‌فرض"},
      {"key": "shop.stock.negative",   "type": "bool",   "default": false, "title_fa": "اجازهٔ موجودی منفی"}
    ]
  },

  "database": {
    "table_prefix": "shop_",
    "tables": ["shop_products","shop_categories","shop_orders","shop_order_items",
               "shop_carts","shop_cart_items","shop_payments","shop_inventories"],
    "seed_on_install": true
  },

  "health": {"endpoint": "/health/shop", "checks": ["db","cache","queue"]},

  "extractable": {
    "enabled": true,
    "service_name": "shop-service",
    "api_prefix": "/api/v1/shop",
    "events_transport": "outbox"
  },

  "install": {
    "steps": ["migrate", "seed", "publish:assets", "cache:clear"],
    "post_install_command": "shop:install"
  }
}
```

### ۳٫۱ قواعد Manifest 🔒
| # | قاعده |
|---|---|
| M1 | `id` یکتا و با فرمت `<vendor>.<name>` |
| M2 | `version` حتماً SemVer |
| M3 | همهٔ وابستگی‌ها در `requires` اعلام شود (کشف خودکار ممنوع) |
| M4 | رویدادهای `published` باید Schema + Version داشته باشند |
| M5 | مجوزها باید ثبت شوند تا RBAC بتواند Sync کند |
| M6 | `autoload.psr-4` باید با `docs/04-structure/composer-mapping.md` هم‌خوان باشد |
| M7 | Manifest در زمان نصب Validate می‌شود (`tvn module:validate`) |

---

## ۴. کشف و بارگذاری ماژول‌ها (Discovery & Boot)

```
tvn boot
  │
  ├─ 1. Scan: modules/*, kits/*, plugins/*  → خواندن module.json
  ├─ 2. Validate: Schema + Version Constraint (SemVer)
  ├─ 3. Resolve: ساخت گراف وابستگی → Topological Sort
  │        ├── تشخیص چرخه → CircularModuleDependencyException
  │        └── ماژول غیرفعال (config) → حذف از گراف + حذف وابسته‌ها
  ├─ 4. Register: Provider->register() به ترتیب
  ├─ 5. Merge Config: config/<module>.php → Config Repository
  ├─ 6. Routes: ثبت روت‌ها با prefix و namespace ماژول
  ├─ 7. Views: ثبت namespace ویو (`shop::`)
  ├─ 8. Lang: ثبت namespace ترجمه
  ├─ 9. Permissions: Sync با RBAC (افزودن/حذف نرم)
  ├─10. Boot: Provider->boot()
  ├─11. Widgets/Commands/Jobs/Listeners ثبت
  └─12. Cache Manifest (در PROD) → `storage/cache/modules.php`
```

### ۴٫۱ کلاس `ModuleRegistry`

```php
final class ModuleRegistry
{
    /** @var array<string, Module> */
    private array $modules = [];

    public function discover(string $basePath): void
    {
        foreach ($this->finder->directories($basePath) as $dir) {
            $manifest = $dir . '/module.json';
            if (!\is_file($manifest)) {
                continue;
            }
            $this->register(Module::fromManifest(
                ManifestParser::parse($manifest),   // با Schema Validation
                path: $dir,
            ));
        }
    }

    /** @return list<Module> ترتیب‌شده بر اساس وابستگی */
    public function sorted(): array
    {
        return (new TopologicalSorter($this->modules))->sort();
    }

    public function get(string $id): Module
    {
        return $this->modules[$id] ?? throw ModuleNotFoundException::forId($id);
    }

    public function enabled(string $id): bool { /* … */ }
    /** @return list<string> */
    public function dependentsOf(string $id): array { /* گراف معکوس */ }
}
```

### ۴٫۲ Topological Sort (الگوریتم)

```
ورودی: گراف G(V,E) که V = ماژول‌ها، E = "depends on"
خروجی: ترتیب خطی یا خطای چرخه

1. محاسبهٔ in-degree برای هر گره
2. صف از گره‌های با in-degree = 0 (مرتب بر اساس priority سپس id برای قطعیت)
3. تا وقتی صف خالی نیست:
     n = pop()
     result.append(n)
     برای هر یال n → m:  in_degree[m]-- ; اگر صفر شد → push(m)
4. اگر |result| ≠ |V| → چرخه وجود دارد:
     گره‌های باقی‌مانده را پیدا کن و با DFS مسیر چرخه را گزارش کن
```
🔒 خروجی باید **قطعی** باشد (Deterministic) — ترتیب یکسان در هر اجرا.

---

## ۵. مرز ماژول (Module Boundary) 🔒

### ۵٫۱ چه چیزی عمومی است؟
| عمومی ✅ | خصوصی ❌ |
|---|---|
| `Modules\X\Contracts\*` (Interface، DTO، Public Events) | `Modules\X\Domain\Entities\*` |
| Widgetهای اعلام‌شده در manifest | `Modules\X\Infrastructure\*` |
| روت‌های نام‌دار (از طریق UrlGenerator) | `Modules\X\Application\*` |
| رویدادهای `published` | جدول‌های DB ماژول |
| تنظیمات عمومی ماژول | کلاس‌های `Providers\*` |

### ۵٫۲ سه راه مجاز برای ارتباط بین ماژول

**راه ۱ — Contract (همزمان، خواندن):**
```php
// در ماژول Blog
final class PostController
{
    public function __construct(private readonly \Modules\Shop\Contracts\ProductReaderInterface $products) {}

    public function showRelated(Post $post): View
    {
        $products = $this->products->findByIds($post->relatedProductIds());
        return view('blog::post.related', ['products' => $products]);
    }
}
```
🔒 فقط Interface از `Contracts`؛ پیاده‌سازی توسط Container تزریق می‌شود.

**راه ۲ — Event (ناهمزمان/همزمان، نوشتن):**
```php
// ماژول Shop منتشر می‌کند
$this->events->dispatch(new OrderWasPaid(orderId: $id, amount: $total, customerId: $cid));

// ماژول Loyalty گوش می‌دهد
final class AwardPointsOnOrderPaid
{
    public function handle(OrderWasPaid $event): void { /* … */ }
}
```

**راه ۳ — Public API (در حالت سرویس):**
```php
$response = $this->serviceClient->call('shop-service', 'GET', '/api/v1/shop/products/' . $id);
```

### ۵٫۳ ممنوعیت‌های مطلق
```
❌ use Modules\Shop\Domain\Entities\Product;      (از ماژول دیگر)
❌ use Modules\Shop\Infrastructure\Persistence\*;
❌ SELECT ... JOIN shop_orders ON ...             (در کوئری ماژول دیگر)
❌ Config::get('shop.internal.secret_key')        (کلیدهای internal)
❌ Filesystem خواندن مسیر ماژول دیگر
❌ Session/Cache Key مشترک بدون namespace ماژول
```

### ۵٫۴ دادهٔ مشترک
| نیاز | راه‌حل |
|---|---|
| نمایش نام کاربر در ماژول Shop | `UserReaderInterface` از ماژول `core-user` |
| گزارش ترکیبی (فروش + پشتیبانی) | موتور `report` با Read Model جدا (Projection) |
| جست‌وجوی سراسری | موتور `search` با Index ترکیبی از Eventها |
| داشبورد | HMVC Widget از هر ماژول |

---

## ۶. دیتابیس در حالت ماژولار

| قاعده | جزئیات |
|---|---|
| 🔒 Prefix | هر ماژول prefix خودش: `shop_`, `blog_`, `crm_` |
| 🔒 مالکیت جدول | یک جدول فقط یک ماژول مالک است (در manifest ثبت) |
| 🔒 ممنوعیت FK بین‌ماژولی | به‌جای FK، `user_id` بدون Constraint + Integrity در Application |
| ⚠️ Denormalization | فیلدهای کپی‌شده (مثل `customer_name` در سفارش) با Event همگام |
| 🔒 Migration جدا | `modules/shop/migrations/` با `tvn migrate --module=shop` |
| 💡 Schema-per-module | در Multi-Tenancy پیشرفته |

```php
// ✅ مجاز
SELECT * FROM shop_orders WHERE customer_id = ?

// ❌ ممنوع (Join بین‌ماژولی)
SELECT o.*, u.name FROM shop_orders o JOIN users u ON u.id = o.customer_id

// ✅ جایگزین: دو کوئری + ترکیب در Application
$orders = $this->db->table('shop_orders')->where('customer_id', $id)->get();
$users  = $this->userReader->findByIds(array_column($orders, 'customer_id'));
```

---

## ۷. چرخهٔ عمر ماژول

```
        ┌──────────┐   install    ┌──────────┐   enable    ┌──────────┐
        │ Created  │ ───────────▶ │ Installed│ ──────────▶ │  Active  │
        └──────────┘              └──────────┘             └────┬─────┘
              ▲                        │                        │ disable
              │ uninstall              │                        ▼
              └────────────────────────┴─────────────────── ┌──────────┐
                                                            │ Disabled │
                                                            └────┬─────┘
                                                                 │ upgrade
                                                                 ▼
                                                            ┌──────────┐
                                                            │ Upgrading│
                                                            └──────────┘
```

| دستور CLI | عملیات |
|---|---|
| `tvn module:list` | فهرست ماژول‌ها + وضعیت + نسخه |
| `tvn module:install <id>` | migrate + seed + publish + enable |
| `tvn module:uninstall <id>` | غیرفعال + حذف asset (داده با `--purge`) |
| `tvn module:enable/disable <id>` | تغییر وضعیت + Cache Clear |
| `tvn module:update <id>` | Backup → migrate → seed → cache |
| `tvn module:validate <id>` | بررسی manifest + مرزها |
| `tvn module:deps <id>` | گراف وابستگی |
| `tvn module:extract <id>` | تولید scaffold سرویس مستقل |

---

## ۸. کیت (Kit) — مجموعهٔ ماژول‌ها برای یک کاربرد

```
kits/shop/
├── kit.json           ← فهرست ماژول‌ها + ترتیب + تنظیمات پیش‌فرض + Theme
├── modules/           ← symlink یا کپی ماژول‌ها
├── themes/default/
├── presets/           ← پیکربندی‌های آماده (small/medium/enterprise)
└── install/           ← اسکریپت نصب گام‌به‌گام
```

```json
{
  "id": "tavaan.kit.shop",
  "title_fa": "کیت فروشگاه",
  "modules": ["tavaan.core-user","tavaan.tax","tavaan.shop","tavaan.payment","tavaan.shipping","tavaan.crm-lite"],
  "theme": "themes/default",
  "preset": "presets/medium.json",
  "post_install": ["shop:seed-demo"]
}
```

---

## ۹. معیار پذیرش 🧪

- AC-MOD-01: `tvn module:list` همهٔ ماژول‌های `modules/` را با وضعیت درست نمایش می‌دهد.
- AC-MOD-02: وابستگی چرخه‌ای → `CircularModuleDependencyException` با گزارش مسیر چرخه.
- AC-MOD-03: `tvn arch:check` هر `use` بین‌ماژولی خارج از `Contracts` را گزارش می‌کند.
- AC-MOD-04: غیرفعال‌کردن یک ماژول، ماژول‌های وابسته را نیز غیرفعال و لاگ می‌کند.
- AC-MOD-05: نصب ماژول (migrate+seed+publish) با یک دستور و قابل rollback (`--dry-run`).
- AC-MOD-06: دو ماژول با جدول همنام → خطا در `module:validate`.
- AC-MOD-07: Boot کامل ۲۰ ماژول (کش‌شده) ≤ ۵ms.
- AC-MOD-08: `tvn module:extract shop` یک پروژهٔ سرویس مستقل تولید می‌کند که تست‌ها را پاس کند.
