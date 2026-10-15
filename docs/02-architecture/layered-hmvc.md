# DOC-021 — معماری لایه‌ای + HMVC (Layered & Hierarchical MVC) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-021 |
| وضعیت | Approved v1.0 |
| والد | DOC-020 |
| اعمال بر | همهٔ ماژول‌ها و کیت‌ها |

---

## ۱. شش لایهٔ مرجع

```
┌──────────────────────────────────────────────────────────────────┐
│ 6. Presentation  │ Controller • View • FormRequest • Resource    │
│                  │ CLI Command • Widget (HMVC)                   │
├──────────────────────────────────────────────────────────────────┤
│ 5. Application   │ UseCase • CommandHandler • QueryHandler       │
│                  │ DTO • Transaction Boundary • Orchestration    │
├──────────────────────────────────────────────────────────────────┤
│ 4. Domain        │ Entity • Aggregate • ValueObject              │
│                  │ DomainService • DomainEvent • Policy • FSM    │
├──────────────────────────────────────────────────────────────────┤
│ 3. Infrastructure│ Repository(DB) • Cache • Search • Mail • Pay  │
│                  │ Filesystem • Queue • HttpClient • Metrics     │
├──────────────────────────────────────────────────────────────────┤
│ 2. Engine        │ ۵۳ موتور خودنوشته با Contract                │
├──────────────────────────────────────────────────────────────────┤
│ 1. Kernel        │ Container • Events • Config • Errors • Loader │
└──────────────────────────────────────────────────────────────────┘
```

### ۱٫۱ قوانین وابستگی 🔒
| # | قانون |
|---|---|
| L1 | وابستگی فقط از لایهٔ بالاتر به پایین‌تر |
| L2 | لایهٔ ۴ (Domain) **هرگز** به ۳/۲/۱ `use` نمی‌کند؛ فقط Interfaceهای خودش |
| L3 | لایهٔ ۳ با **Dependency Inversion** به لایهٔ ۴ وصل می‌شود (Interface در Domain، Impl در Infra) |
| L4 | لایهٔ ۶ فقط ترجمه می‌کند؛ منطق کسب‌وکار در Controller ممنوع |
| L5 | لایهٔ ۵ تنها نقطهٔ مجاز `beginTransaction` |
| L6 | DTO در مرز ۶→۵ و ۵→۴ تبدیل می‌شود؛ Entity به بیرون نشت نمی‌کند |
| L7 | Kernel (۱) هرگز به ماژول/اپلیکیشن وابسته نمی‌شود |

### ۱٫۲ آزمون سریع (Lakmus Test)
> اگر فردا بخواهید DB را از MySQL به Postgres یا View را از `.tv.php` به JSON API تغییر دهید،
> **چند فایل در لایهٔ Domain تغییر می‌کند؟** پاسخ صحیح: **صفر**.

---

## ۲. مسئولیت دقیق هر لایه

### ۲٫۱ Presentation
**مجاز:** دریافت ورودی، Validate شکل (Form)، فراخوانی UseCase، ساخت Response/View، مجوز سطح روت.
**ممنوع:** SQL، منطق قیمت‌گذاری، تراکنش، تغییر وضعیت دامنه.

```php
final class OrderController extends Controller
{
    public function __construct(private readonly PlaceOrderUseCase $placeOrder) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $result = $this->placeOrder->execute(PlaceOrderDto::fromRequest($request));

        return OrderResource::make($result->order)->withStatus(201)->toResponse();
    }
}
```

### ۲٫۲ Application
**مجاز:** Orchestration، Transaction، Dispatch رویداد، تبدیل DTO↔Entity، فراخوانی چند DomainService.
**ممنوع:** قانون کسب‌وکار (مثلاً «تخفیف بیش از ۳۰٪ مجاز نیست»)، SQL مستقیم.

```php
final class PlaceOrderUseCase
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly InventoryServiceInterface $inventory,
        private readonly TransactionManagerInterface $tx,
        private readonly EventDispatcherInterface $events,
    ) {}

    /** @throws OutOfStockException, PaymentRequiredException */
    public function execute(PlaceOrderDto $dto): OrderResult
    {
        return $this->tx->run(function () use ($dto): OrderResult {
            $cart   = Cart::reconstitute($dto->items);          // Domain
            $order  = Order::place($cart, $dto->customer);      // قوانین در Domain
            $this->inventory->reserve($order->items());          // Contract
            $this->orders->save($order);
            $this->events->dispatch(OrderWasPlaced::from($order));
            return new OrderResult($order, $order->total());
        }, attempts: 2);
    }
}
```

### ۲٫۳ Domain
**مجاز:** Invariant، State Machine، محاسبات، Value Object، Domain Event، Policy.
**ممنوع:** هر `use` از `Tavaan\Engine\*` یا `Modules\*\Infrastructure\*`، هر I/O، هر `$_*`.

```php
final class Order extends AggregateRoot
{
    /** @var list<OrderItem> */
    private array $items = [];

    private function __construct(
        private readonly OrderId $id,
        private readonly CustomerId $customer,
        private OrderStatus $status,
        private Money $total,
    ) {}

    public static function place(Cart $cart, CustomerId $customer): self
    {
        if ($cart->isEmpty()) {
            throw new EmptyCartException(messageId: 'shop.cart.empty');
        }
        if ($cart->itemCount() > self::MAX_ITEMS) {
            throw new TooManyItemsException(messageId: 'shop.order.too_many_items');
        }

        $order = new self(OrderId::generate(), $customer, OrderStatus::Pending, $cart->total());
        foreach ($cart->items() as $item) {
            $order->addItem($item);
        }
        $order->recordThat(OrderWasPlaced::from($order));
        return $order;
    }

    public function pay(PaymentReference $ref): void
    {
        $this->assertTransition(OrderStatus::Paid);
        $this->status = OrderStatus::Paid;
        $this->recordThat(OrderWasPaid::of($this->id, $ref, $this->total));
    }

    public function cancel(string $reason): void { /* … */ }
}
```

### ۲٫۴ Infrastructure
**مجاز:** پیاده‌سازی Contractهای Domain، SQL، Cache، HTTP خارجی، Serialization.
**ممنوع:** تصمیم کسب‌وکاری.

```php
final class EloquentLikeOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly OrderMapper $mapper,
    ) {}

    public function save(Order $order): void
    {
        $this->db->transaction(function () use ($order): void {
            $row = $this->mapper->toRow($order);
            if ($order->isNew()) {
                $this->db->table('shop_orders')->insert($row);
            } else {
                $this->db->table('shop_orders')->where('id', $order->id()->toString())->update($row);
            }
            $this->persistEvents($order->pullRecordedEvents());
        });
    }
}
```

---

## ۳. HMVC — سلسله‌مراتب کنترل‌کننده‌ها

### ۳٫۱ مسئله
صفحهٔ داشبورد شامل ۸ قطعهٔ مستقل است. در MVC کلاسیک:
```php
// ❌ God Controller
public function dashboard() {
    $stats   = ...; $orders = ...; $users = ...; $tickets = ...; $news = ...;
    return view('dashboard', compact('stats','orders','users','tickets','news'));
}
```
نتیجه: Controller بزرگ، View غیرقابل استفادهٔ مجدد، تست سخت، ماژول‌ها در هم.

### ۳٫۲ مدل تَوان
```
Request: GET /admin/dashboard
└── DashboardController@index                    (Level 0)
    ├── view: admin/dashboard/index.tv.php
    │     ├── @module('stats:summary')           (Level 1) → StatsController@summary → View
    │     ├── @module('shop:orders/recent')      (Level 1) → OrderController@recent   → View
    │     │     └── @module('shop:orders/row')   (Level 2) → PartialView
    │     ├── @module('crm:tickets/open')        (Level 1)
    │     └── @module('notification:bell')       (Level 1)
```

### ۳٫۳ مکانیزم `Module::run()`

```php
final class ModuleRunner
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly RequestStack $stack,
        private readonly ModuleRegistry $registry,
    ) {}

    /**
     * اجرای یک Sub-Request درون‌فرایندی (بدون I/O شبکه).
     *
     * @param array<string, mixed> $params
     * @throws HmvcDepthException اگر عمق از maxDepth بیشتر شود
     * @throws ModuleNotFoundException
     */
    public function run(string $target, array $params = [], int $status = 200): string
    {
        $depth = $this->stack->depth();
        if ($depth >= $this->maxDepth) {
            throw new HmvcDepthException(
                messageId: 'hmvc.depth_exceeded',
                context: ['target' => $target, 'depth' => $depth],
                hint: 'احتمالاً حلقهٔ بازگشتی در فراخوانی ماژول وجود دارد.',
            );
        }

        $parent  = $this->stack->current();
        $sub     = SubRequest::fromParent($parent, target: $target, params: $params, depth: $depth + 1);

        $this->stack->push($sub);
        try {
            $response = $this->kernel->handleSubRequest($sub);
            return $response->isSuccessful() ? $response->body() : $this->renderError($target, $response);
        } catch (\Throwable $e) {
            return $this->isolate ? $this->renderFallback($target, $e) : throw $e;
        } finally {
            $this->stack->pop();
        }
    }
}
```

### ۳٫۴ قواعد HMVC 🔒
| # | قاعده |
|---|---|
| H1 | حداکثر عمق: ۴ سطح (`hmvc.max_depth`) |
| H2 | Sub-Request **هیچ‌گاه** Header/Cookie/Session والد را تغییر نمی‌دهد (فقط خواندن) |
| H3 | پاسخ Sub-Request فقط `string` (HTML) یا `array` (برای Fragment API) |
| H4 | خطا در Sub-Request نباید کل صفحه را بشکند → `hmvc.isolate_errors = true` (پیش‌فرض) + Fallback View |
| H5 | هر Sub-Request در Trace یک Span جدا دارد (`hmvc.run`) |
| H6 | Cache در سطح Widget مجاز با کلید `tvn:hmvc:<target>:<hash(params)>` |
| H7 | ممنوعیت اجرای Side Effect (نوشتن DB) در Widget نمایشی → فقط خواندن |
| H8 | Widgetها باید در `module.json` با `"widgets": [...]` اعلام شوند |
| H9 | Lazy Render: ویجت‌های سنگین با `@defer` → Placeholder + SSE/AJAX |
| H10 | در حالت مایکروسرویس، `Module::run()` به `RemoteModuleProxy` تبدیل می‌شود (بدون تغییر View) |

### ۳٫۵ دستورهای View برای HMVC
```blade
@module('shop:orders/recent', ['limit' => 5])            {{-- همزمان --}}
@defer('stats:heavy-chart')                               {{-- با Placeholder + SSE --}}
@cache('crm:tickets/open', ttl: 60, key: 'open-tickets')  {{-- کش ویجت --}}
@can('shop.order.view') ... @endcan                       {{-- مجوز --}}
```

---

## ۴. الگوی Widget (قطعهٔ قابل استفادهٔ مجدد)

```php
<?php
declare(strict_types=1);

namespace Modules\Shop\Http\Widgets;

use Modules\Shop\Application\Queries\RecentOrdersQuery;
use Tavaan\Core\Contracts\View\WidgetInterface;
use Tavaan\Engine\View\Widget;

final class RecentOrdersWidget extends Widget implements WidgetInterface
{
    /** @param array{limit?: int} $params */
    public function handle(RecentOrdersQuery $query, array $params = []): string
    {
        $orders = $query->handle(limit: $params['limit'] ?? 5);

        return $this->view('shop::widgets.recent-orders', ['orders' => $orders]);
    }

    public function cacheKey(array $params): string
    {
        return 'shop.recent-orders.' . ($params['limit'] ?? 5);
    }

    public function cacheTtl(): int
    {
        return 30;
    }
}
```

**ثبت در `module.json`:**
```json
{
  "id": "tavaan.shop",
  "widgets": {
    "orders/recent": "Modules\\Shop\\Http\\Widgets\\RecentOrdersWidget",
    "cart/summary":  "Modules\\Shop\\Http\\Widgets\\CartSummaryWidget"
  }
}
```

---

## ۵. نگاشت MVC کلاسیک (برای پروژه‌های ساده)

اگر پروژه کوچک است (وبلاگ شخصی)، لایه‌های ۴/۵ قابل ادغام‌اند:

```
app/
├── Http/Controllers/     ← Controller (M)
├── Models/               ← Model = Entity + Repository (C+M ادغام‌شده)
├── Views/                ← V
└── Services/             ← منطق ساده
```
این حالت **«Simple MVC Mode»** نام دارد و با `app.architecture = 'mvc'` فعال می‌شود.

| مقدار `app.architecture` | لایه‌ها | کاربرد |
|---|---|---|
| `mvc` | ۳ لایه | پروژهٔ کوچک، پروتوتایپ |
| `hmvc` | ۴ لایه + Widget | سایت متوسط، پرتال |
| `modular` | ۵ لایه + ماژول | محصول چند‌دامنه‌ای |
| `hexagonal` | ۶ لایه کامل | ERP/BPMS/مقیاس بزرگ |

> 🔒 انتخاب در `config/app.php` انجام می‌شود و Scaffold بر اساس آن تولید می‌کند.
> ⚠️ ارتقا از `mvc` به `hexagonal` با `tvn upgrade:layered` (مهاجرت تدریجی).

---

## ۶. قواعد جایگذاری فایل بر اساس لایه

| لایه | مسیر در ماژول | Namespace |
|---|---|---|
| Presentation | `src/Http/`, `src/Console/`, `src/View/` | `Modules\X\Http\*` |
| Application | `src/Application/` | `Modules\X\Application\*` |
| Domain | `src/Domain/` | `Modules\X\Domain\*` |
| Infrastructure | `src/Infrastructure/` | `Modules\X\Infrastructure\*` |
| Contracts (Public) | `src/Contracts/` | `Modules\X\Contracts\*` |
| Providers | `src/Providers/` | `Modules\X\Providers\*` |

---

## ۷. قوانین ArchUnit-like (اجرای خودکار)

```php
// config/architecture.php
return [
    'rules' => [
        ['id' => 'ARCH-001', 'desc' => 'Domain باید مستقل از Infrastructure باشد',
         'classes' => 'Modules\*\Domain\*',
         'must_not_depend_on' => ['Modules\*\Infrastructure\*', 'Tavaan\Engine\*', 'PDO', 'Redis']],

        ['id' => 'ARCH-002', 'desc' => 'Presentation نباید مستقیماً Repository صدا بزند',
         'classes' => 'Modules\*\Http\Controllers\*',
         'must_not_depend_on' => ['Modules\*\Infrastructure\Repositories\*']],

        ['id' => 'ARCH-003', 'desc' => 'عدم نفوذ بین ماژول‌ها',
         'classes' => 'Modules\*\*',
         'must_not_depend_on' => ['Modules\{other}\*'],
         'except' => ['Modules\*\Contracts\*']],

        ['id' => 'ARCH-004', 'desc' => 'Application تنها لایهٔ مجاز تراکنش',
         'classes' => 'Modules\*',
         'methods_using' => ['beginTransaction', 'transaction'],
         'only_in' => ['Modules\*\Application\*']],

        ['id' => 'ARCH-005', 'desc' => 'Engine نباید به Module وابسته شود',
         'classes' => 'Tavaan\Engine\*',
         'must_not_depend_on' => ['Modules\*', 'App\*']],

        ['id' => 'ARCH-006', 'desc' => 'Kernel باید صفر وابستگی داشته باشد',
         'classes' => 'Tavaan\Core\*',
         'must_not_depend_on' => ['Tavaan\Engine\*', 'Modules\*', 'App\*']],

        ['id' => 'ARCH-007', 'desc' => 'حداکثر عمق ارث',
         'classes' => '*', 'max_inheritance_depth' => 2],

        ['id' => 'ARCH-008', 'desc' => 'بدون چرخهٔ وابستگی',
         'classes' => '*', 'no_circular_dependencies' => true],
    ],
];
```

**خروجی `tvn arch:check`:**
```
✔ ARCH-001  Domain independence .................... 412 classes OK
✔ ARCH-002  No repository in controllers ........... 87 classes OK
✖ ARCH-003  Module boundary ......................... 1 violation
    Modules\Blog\Http\Controllers\PostController:14
      → use Modules\Shop\Models\Product;   (ممنوع)
      پیشنهاد: از Modules\Shop\Contracts\ProductReader استفاده کنید یا رویداد بفرستید.
```

---

## ۸. معیار پذیرش 🧪

- AC-HMVC-01: اجرای `Module::run()` تودرتو تا عمق ۴ موفق و در عمق ۵ → `HmvcDepthException`.
- AC-HMVC-02: خطا در یک Widget (با `isolate_errors=true`) بقیهٔ صفحه را نمی‌شکند.
- AC-HMVC-03: `tvn arch:check` همهٔ قوانین ARCH-001..008 را روی codebase نمونه پاس می‌کند.
- AC-HMVC-04: تغییر درایور DB در پروژهٔ نمونه، ۰ فایل Domain را تغییر می‌دهد.
- AC-HMVC-05: زمان اجرای یک Widget کش‌شده ≤ ۰٫۳ms.
- AC-HMVC-06: هر Sub-Request یک Span در Trace دارد.
