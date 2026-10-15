# DOC-011 — سبک کد تَوان (Coding Style) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-011 |
| مبنا | PSR-1، PSR-12 + افزودنی‌های اختصاصی تَوان |
| ابزار | `tvn style:check` / `tvn style:fix` (PHP-CS-Fixer Ruleset در همین سند) |

---

## ۱. قالب‌بندی پایه

| قلم | مقدار |
|---|---|
| ایندنت | ۴ فاصله (Tab ممنوع) |
| طول خط | ≤ ۱۲۰ کاراکتر (soft) / ۱۴۰ (hard) |
| پایان خط | `LF` فقط |
| Encoding | UTF-8 بدون BOM |
| فاصلهٔ انتهای فایل | یک خط خالی |
| `?>` پایانی | حذف |
| Trailing Comma | اجباری در آرگومان چندخطی (8.0+) |
| `{` کلاس/متد | خط بعد |
| `{` ساختار کنترل | همان خط |

```php
final class OrderService
{
    private const int MAX_ITEMS = 100;

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly EventDispatcherInterface $events,
        private readonly MoneyFactory $money,
    ) {
    }

    public function place(PlaceOrderCommand $command): Order
    {
        if ($command->items === []) {
            throw new EmptyCartException(messageId: 'shop.cart.empty');
        }

        $order = Order::create(
            customerId: $command->customerId,
            items: $command->items,
            currency: $command->currency,
        );

        $this->orders->save($order);
        $this->events->dispatch(new OrderPlaced($order->id(), $order->total()));

        return $order;
    }
}
```

---

## ۲. ترتیب اعضای کلاس (Member Order) 🔒

```
1.  use (traits)
2.  constants: public → protected → private
3.  properties: public readonly → protected → private (با تفکیک static در انتها)
4.  __construct()
5.  static factories (named constructors): fromArray(), create(), of()
6.  public methods (به ترتیب اهمیت دامنه‌ای)
7.  protected methods
8.  private methods
9.  magic methods (__toString, __clone, __serialize, jsonSerialize) در انتها
```

قواعد اضافی:
- ⚠️ یک خط خالی بین هر گروه.
- 🔒 Constructor Promotion ترجیح داده شود؛ اگر بدنهٔ Constructor بیش از ۵ خط است، property صریح.
- 🔒 هیچ property عمومی mutable (فقط `public readonly`).
- ⚠️ Getter/Setter خودکار تولید نشود مگر برای Value Object با دلیل.

---

## ۳. قواعد نام‌گذاری (خلاصه؛ جزئیات در DOC-012)

| عنصر | سبک | مثال |
|---|---|---|
| Namespace | PascalCase بخش‌ها | `Tavaan\Engine\Http\Routing` |
| Class/Interface/Trait/Enum | PascalCase | `RouteCompiler` |
| Interface | بدون پیشوند `I`؛ پسوند معنایی | `CacheStoreInterface` (در قراردادها) / `Cacheable` (قابلیت) |
| Method | camelCase + فعل | `resolveRoute()` |
| Property/Variable | camelCase | `$requestId` |
| Constant / Enum Case | CONSTANT_CASE / PascalCase | `MAX_RETRY`, `OrderStatus::Paid` |
| File (غیرکلاس) | kebab-case | `route-cache.php` |
| Config Key | dot.snake | `app.cache.driver` |
| DB Table/Column | snake_case | `order_items.discount_percent` |
| Route Path | kebab-case | `/blog/posts/{slug}` |
| Event Class | اسم مصدر گذشته | `OrderWasPlaced` / `OrderPlaced` |
| Command/Job | فعل + اسم | `SendOrderInvoiceJob` |
| Test | `test<Behavior>_<Condition>_<Expectation>` | `testPlaceOrder_WithEmptyCart_Throws` |

---

## ۴. قواعد متد و تابع

| # | قاعده |
|---|---|
| M-01 | 🔒 طول متد ≤ ۵۰ خط (بدون PHPDoc) |
| M-02 | 🔒 تعداد پارامتر ≤ ۵؛ بیشتر → Parameter Object |
| M-03 | 🔒 Cyclomatic Complexity ≤ ۱۰ |
| M-04 | 🔒 Nesting Depth ≤ ۴؛ استفاده از Guard Clause و Early Return |
| M-05 | 🔒 یک نقطهٔ خروج ترجیحی (نه اجباری اگر خوانایی بهتر شود) |
| M-06 | ⚠️ Boolean Parameter ممنوع → Enum یا دو متد مجزا |
| M-07 | 🔒 Side Effect در Getter ممنوع |
| M-08 | ⚠️ متدهای `static` فقط برای Factory/Helper Stateless |
| M-09 | 🔒 هر متد عمومی دارای PHPDoc با `@throws` و `@api` |
| M-10 | ⚠️ متدهای private → private؛ اگر برای تست لازم است، طراحی را اصلاح کنید نه Visibility را |

### ۴٫۱ Guard Clause (اجباری)

```php
// ❌ بد
public function register(array $data): User
{
    if (isset($data['email'])) {
        if (filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            // ...
        }
    }
}

// ✅ خوب
public function register(RegisterData $data): User
{
    if ($this->users->existsByEmail($data->email)) {
        throw new EmailAlreadyTakenException(messageId: 'auth.email.taken');
    }

    return $this->users->create($data);
}
```

---

## ۵. قواعد کامنت و PHPDoc

### ۵٫۱ چه زمانی کامنت بنویسیم؟
- 🔒 **چرا** نه **چه**. کد باید «چه» را روشن کند.
- ⚠️ کامنت `// TODO` ممنوع بدون Issue ID: `// TODO(TVN-1234): ...`
- 🔒 هر Class عمومی: Summary + Description + `@package` + `@api` + `@since`.
- 🔒 هر متد عمومی: `@param` با تایپ، `@return`، `@throws`.
- ⚠️ PHPDoc نباید با تایپ واقعی تناقض داشته باشد (PHPStan خطا می‌دهد).

### ۵٫۲ قالب استاندارد

```php
/**
 * کامپایل روت‌ها به ساختار Trie برای تطبیق سریع.
 *
 * این کامپایلر مسیرها را بر اساس Segment تفکیک کرده و برای هر Segment
 * یک گره استاتیک/پویا/Wildcard می‌سازد. خروجی قابل Cache است.
 *
 * @param  list<RouteDefinition> $routes مجموعهٔ روت‌های ثبت‌شده
 * @param  string|null           $prefix پیشوند سراسری (اختیاری)
 * @return CompiledRouteCollection ساختار کامپایل‌شده
 *
 * @throws RouteCompilationException اگر الگوی پارامتر نامعتبر باشد
 *
 * @api 1.0
 * @since 1.0.0
 * @see docs/03-engines/router.md#compilation
 */
public function compile(array $routes, ?string $prefix = null): CompiledRouteCollection
```

### ۵٫۳ Generic PHPDoc (برای PHPStan)

```php
/**
 * @template TKey of array-key
 * @template TValue
 * @implements \IteratorAggregate<TKey, TValue>
 */
final class Collection implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /** @param array<TKey, TValue> $items */
    public function __construct(private readonly array $items = []) {}

    /** @return TValue|null */
    public function first(): mixed { /* … */ }

    /**
     * @template TNew
     * @param callable(TValue): TNew $callback
     * @return Collection<TKey, TNew>
     */
    public function map(callable $callback): self { /* … */ }
}
```

---

## ۶. کنترل‌های جریان

```php
// ✅ match ترجیح داده شود
$statusLabel = match ($status) {
    OrderStatus::Paid      => 'پرداخت‌شده',
    OrderStatus::Canceled  => 'لغوشده',
    default                => 'در انتظار',
};

// ✅ foreach با کلید صریح
foreach ($users as $id => $user) { /* … */ }

// ❌ ممنوع: switch با fallthrough بدون // no break
// ❌ ممنوع: حلقهٔ بی‌نهایت بدون شرط خروج صریح
// ❌ ممنوع: try داخل حلقه برای کنترل جریان
```

---

## ۷. Import و Use

| # | قاعده |
|---|---|
| U-01 | 🔒 همیشه `use` برای کلاس‌ها؛ ممنوعیت Fully-Qualified در بدنه (مگر برای توابع داخلی) |
| U-02 | 🔒 ممنوعیت `use function` با نام مبهم؛ ترجیح `\array_map` برای توابع داخلی (Performance) |
| U-03 | ⚠️ Alias فقط برای رفع تداخل نام |
| U-04 | 🔒 ترتیب use: الفبایی، بدون خط خالی بین گروه‌ها |
| U-05 | 🔒 `use` بدون استفاده = خطا (CS Fixer حذف می‌کند) |

---

## ۸. تست‌نویسی — سبک

```php
<?php
declare(strict_types=1);

namespace Tavaan\Tests\Unit\Engine\Http\Routing;

use Tavaan\Engine\Http\Routing\Route;
use Tavaan\Engine\Http\Routing\RouteCollection;
use Tavaan\Engine\Http\Routing\RouteNotFoundException;
use Tavaan\Testing\TestCase;

final class RouteCollectionTest extends TestCase
{
    public function testMatch_WithStaticRoute_ReturnsRoute(): void
    {
        $routes = new RouteCollection([Route::get('/users', 'UserController@index')]);

        $matched = $routes->match(method: 'GET', path: '/users');

        self::assertSame('UserController@index', $matched->getAction());
    }

    public function testMatch_WithUnknownPath_ThrowsNotFound(): void
    {
        $routes = new RouteCollection();

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessageId('http.route.not_found');

        $routes->match(method: 'GET', path: '/nope');
    }

    /** @return list<array{0: string, 1: string, 2: bool}> */
    public static function matchProvider(): array
    {
        return [
            'static exact'    => ['GET', '/users', true],
            'method mismatch' => ['POST', '/users', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('matchProvider')]
    public function testMatch_Various(string $method, string $path, bool $expected): void { /* … */ }
}
```

قواعد تست:
- 🔒 نام تست: `test<Subject>_<Condition>_<Expectation>`
- 🔒 ساختار AAA: Arrange / Act / Assert با خط خالی جدا
- 🔒 یک Assertion منطقی در هر تست (چند `assert` مرتبط مجاز)
- 🔒 بدون وابستگی به ترتیب اجرا، بدون I/O واقعی، بدون Sleep
- ⚠️ DataProvider برای حالت‌های متعدد

---

## ۹. PHP-CS-Fixer Ruleset (مرجع ابزار)

```php
<?php
// .php-cs-fixer.dist.php  — فقط در dev (وابستگی خارجی، در core ممنوع نیست چون dev است)
$finder = PhpCsFixer\Finder::create()->in([__DIR__.'/core', __DIR__.'/engines', __DIR__.'/modules', __DIR__.'/tests']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                                   => true,
        'strict_param'                             => true,
        'declare_strict_types'                     => true,
        'no_unused_imports'                        => true,
        'ordered_imports'                          => ['sort_algorithm' => 'alpha'],
        'final_class'                              => false,   // دستی کنترل می‌شود
        'final_internal_class'                     => true,
        'readonly_promoted_properties'             => true,
        'phpdoc_to_param_type'                     => false,
        'nullable_type_declaration_for_default_null_value' => true,
        'no_superfluous_phpdoc_tags'               => ['allow_mixed' => true],
        'phpdoc_align'                             => true,
        'phpdoc_order'                             => true,
        'trailing_comma_in_multiline'              => ['elements' => ['arguments', 'parameters', 'array_destructuring', 'match']],
        'array_syntax'                             => ['syntax' => 'short'],
        'binary_operator_spaces'                   => ['default' => 'single_space'],
        'concat_space'                             => ['spacing' => 'one'],
        'method_argument_space'                    => ['on_multiline' => 'ensure_fully_multiline'],
        'single_line_throw'                        => false,
        'void_return'                              => true,
        'yoda_style'                               => false,
        'global_namespace_import'                  => ['import_classes' => true, 'import_functions' => null],
        'native_function_invocation'               => ['include' => ['@compiler_optimized'], 'strict' => true],
        'class_attributes_separation'              => ['elements' => ['method' => 'one', 'property' => 'one']],
        'no_empty_statement'                       => true,
        'no_trailing_whitespace'                   => true,
        'line_ending'                              => true,
        'encoding'                                 => true,
    ])
    ->setFinder($finder);
```

---

## ۱۰. معیارهای اندازه (Metrics Thresholds)

| معیار | حد | ابزار |
|---|---|---|
| طول فایل | ≤ ۴۰۰ خط | custom |
| طول کلاس | ≤ ۳۰۰ خط | phpmd |
| طول متد | ≤ ۵۰ خط | phpmd |
| پارامتر متد | ≤ ۵ | phpmd |
| Cyclomatic Complexity | ≤ ۱۰ | phpmd |
| NPath Complexity | ≤ ۲۰۰ | phpmd |
| Coupling Between Objects (CBO) | ≤ ۱۲ | phpmd |
| Depth of Inheritance Tree | ≤ ۲ | custom |
| Efferent Coupling per Module | ≤ ۶ ماژول دیگر | `tvn arch:check` |
| تعداد Case در Enum | ≤ ۲۰ | custom |
| تعداد کلید در آرایه Config یک موتور | ≤ ۴۰ | review |

> عبور از هر حد → یا Refactor، یا ثبت ADR توجیهی با شماره (`ADR-XXXX`) در PHPDoc.
