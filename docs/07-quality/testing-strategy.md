# DOC-110 — استراتژی تست (Testing Strategy) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-110 |
| موتور تست | `engines/test` (DOC-084E) |
| مرتبط | DOC-113 (DoD)، DOC-031..084 (معیار پذیرش هر موتور) |

---

## ۱. هرم تست

```
                    /\
                   /  \        E2E (۵٪)   — سناریوی کامل کسب‌وکار
                  /────\                   (کیفیت بیرونی)
                 /      \      Integration (۲۰٪) — چند موتور/ماژول با هم
                /────────\                  (DB/Cache/Queue واقعی: SQLite in-mem)
               /          \    Feature (۲۵٪) — یک جریان کاربری
              /────────────\                (HTTP request → response)
             /              \   Unit (۵۰٪) — یک کلاس/متد
            /────────────────\              (ایزوله، بدون I/O)
```

| سطح | تعداد | سرعت | ابزار | پوشش هدف |
|---|---|---|---|---|
| Unit | زیاد (~۱۵۰۰) | µs–ms | Native Runner | ≥ ۹۵٪ خط |
| Feature | متوسط (~۳۰۰) | ms | HttpTest | جریان‌های اصلی |
| Integration | کم (~۱۵۰) | ms–s | SQLite in-memory | مرز موتورها |
| E2E | خیلی کم (~۵۰) | s | Full app + DB | سناریوی کسب‌وکار |

---

## ۲. انواع تست

### ۲٫۱ Unit Test
```php
final class MoneyTest extends TestCase
{
    public function testAdd_SameCurrency_Sums(): void
    {
        $a = Money::of(1000, Currency::IRR);
        $b = Money::of(2500, Currency::IRR);
        self::assertSame(3500, $a->add($b)->amount());
    }

    public function testAdd_DifferentCurrency_Throws(): void
    {
        $this->expectException(CurrencyMismatchException::class);
        Money::of(100, Currency::IRR)->add(Money::of(100, Currency::USD));
    }
}
```
🔒 بدون I/O، بدون DB، بدون شبکه، بدون زمان واقعی (Clock Fake).

### ۲٫۲ Feature Test (HTTP)
```php
final class ProductApiTest extends TestCase
{
    use RefreshDatabase, MakesHttpRequests;

    public function testGuest_CannotCreateProduct(): void
    {
        $this->postJson('/api/v1/shop/products', ['title' => 'x'])
             ->assertUnauthorized();
    }

    public function testManager_CanCreateProduct(): void
    {
        $user = User::factory()->role('shop-manager')->create();
        $this->actingAs($user)
             ->postJson('/api/v1/shop/products', $this->validData())
             ->assertCreated()
             ->assertJsonStructure(['data' => ['id', 'title']]);
    }
}
```

### ۲٫۳ Integration Test
```php
final class OrderPlacementIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function testPlaceOrder_DecrementsStock_AndDispatchesEvent(): void
    {
        Event::fake([OrderWasPlaced::class]);
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs(User::factory()->create())
             ->post('/shop/checkout', ['items' => [['product_id' => $product->id, 'qty' => 2]]])
             ->assertOk();

        self::assertSame(3, $product->fresh()->stock);          // DB واقعی
        Event::assertDispatched(OrderWasPlaced::class);         // Outbox
    }
}
```

### ۲٫۴ E2E Test (سناریوی کسب‌وکار)
```php
final class ShopCheckoutE2ETest extends TestCase
{
    public function testFullPurchaseJourney(): void
    {
        // ۱. مشتری ثبت‌نام می‌کند
        // ۲. محصول را می‌بیند و به سبد اضافه می‌کند
        // ۳. checkout می‌کند
        // ۴. پرداخت (sandbox gateway) موفق
        // ۵. سفارش تأیید، موجودی کم، ایمیل ارسال
        // ۶. در پنل مدیر، سفارش دیده می‌شود
        // → assertions در هر گام
    }
}
```

---

## ۳. ابزارهای تست

### ۳٫۱ Fakeها (جایگزین واقعی‌ها)
| Fake | جایگزین |
|---|---|
| `Event::fake()` | ضبط رویدادها |
| `Mail::fake()` | بدون ارسال واقعی |
| `Queue::fake()` | بدون push واقعی |
| `Http::fake()` | پاسخ ساختگی |
| `Storage::fake()` | filesystem در حافظه |
| `Cache::fake()` / ArrayStore | حافظه |
| `Auth::fake($user)` | کاربر ثابت |
| `Clock::fake()` | زمان ثابت |

### ۳٫۲ Database
- SQLite in-memory برای Unit/Feature (سریع)
- MySQL/PostgreSQL واقعی در CI برای Integration (سازگاری درایور)

### ۳٫۳ Fixture و Factory
```php
Product::factory()->count(10)->active()->create();
Order::factory()->withItems(3)->paid()->create();
User::factory()->role('shop-manager')->create();
```

---

## ۴. Data Providers و Property-Based
```php
// Data Provider برای حالت‌های متعدد
public static function nationalCodeProvider(): array
{
    return [
        'valid'   => ['0012345678', true],
        'invalid' => ['1234567890', false],
        'all_same'=> ['1111111111', false],
    ];
}
#[DataProvider('nationalCodeProvider')]
public function testNationalCode(string $code, bool $expected): void { /* … */ }

// Property-Based (برای Decimal، Jalali)
final class DecimalPropertyTest extends TestCase
{
    public function testAddIsCommutative(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $a = Decimal::random(); $b = Decimal::random();
            self::assertTrue($a->add($b)->equals($b->add($a)));
        }
    }
}
```

---

## ۵. تست امنیتی 🧪
→ سناریوها در DOC-016 §6 (SEC-T-01..15).
```
tests/Security/
├── SqlInjectionTest.php       (Fuzzing با payload set)
├── XssTest.php                (context-aware)
├── CsrfTest.php
├── PathTraversalTest.php
├── IdorTest.php
├── SessionFixationTest.php
├── SsrfTest.php
├── UploadTest.php
└── RateLimitTest.php
```

---

## ۶. تست معماری 🧪
```
tests/Architecture/
├── LayerDependencyTest.php    (Domain مستقل از Infra)
├── ModuleBoundaryTest.php     (بدون use بین‌ماژولی)
├── FinalClassTest.php         (≥ ۹۰٪ final)
├── StrictTypesTest.php        (همه فایل‌ها)
├── NoVendorTest.php           (بدون require خارجی)
└── NamingConventionTest.php
```
→ با `tvn arch:check` (DOC-021 §7).

---

## ۷. تست سازگاری نسخه 🧪
```yaml
# CI matrix
php: ['8.2', '8.3', '8.4']
db:  ['mysql:8.0', 'pgsql:15', 'sqlite:3']
```
🔒 همهٔ ترکیب‌ها باید سبز باشند. صفر `E_DEPRECATED`.

---

## ۸. Benchmark (تست کارایی) 🧪
```php
final class RouterBenchmark extends Benchmark
{
    public function benchMatchWith1000Routes(): void
    {
        $routes = $this->buildRoutes(1000);
        $this->assertDuration(
            fn () => $routes->match('GET', '/products/123'),
            maxMicroseconds: 5,
            iterations: 10000,
        );
    }
}
```
→ اهداف در DOC-112.

---

## ۹. Coverage و Mutation

### ۹٫۱ Coverage
| حوزه | هدف |
|---|---|
| هسته (core) | خط ≥ ۹۵٪، شاخه ≥ ۹۰٪ |
| موتورها | خط ≥ ۹۰٪ |
| ماژول‌های نمونه | خط ≥ ۸۰٪ |

### ۹٫۲ Mutation Testing
```
هدف: Mutation Score ≥ ۸۵٪ برای هسته
ابزار: Infection (dev) یا mutation-checker داخلی
```
🔒 هر mutant زنده = شکاف در تست.

---

## ۱۰. ساختار پوشهٔ تست
```
tests/
├── Unit/                 (mirrors src structure)
├── Feature/
├── Integration/
├── E2E/
├── Security/
├── Architecture/
├── Benchmark/
├── Fixtures/
├── Factories/
└── bootstrap.php
```
هر موتور/ماژول هم `tests/` خودش را دارد.

---

## ۱۱. قواعد تست‌نویسی 🔒
| # | قاعده |
|---|---|
| 1 | نام: `test<Subject>_<Condition>_<Expectation>` |
| 2 | AAA: Arrange / Act / Assert با خط خالی |
| 3 | یک مفهوم در هر تست |
| 4 | بدون وابستگی به ترتیب اجرا |
| 5 | بدون Sleep/زمان واقعی (Clock Fake) |
| 6 | بدون I/O واقعی در Unit |
| 7 | Deterministic (بدون random بدون seed) |
| 8 | هر تست مستقل setUp/tearDown |
| 9 | Assertion با پیام گویا |
| 10 | تست خطاها به‌اندازهٔ تست موفقیت |

---

## ۱۲. اجرای تست
```bash
tvn test                      # همه
tvn test --unit               # فقط unit
tvn test --group=cache
tvn test --filter=RouterTest
tvn test engines/http         # یک موتور
tvn test --coverage
tvn test --parallel=4
tvn bench:all                 # benchmarkها
```

---

## ۱۳. معیار پذیرش 🧪
- AC-TEST-01: Coverage هسته ≥ ۹۵٪ خط.
- AC-TEST-02: Mutation Score ≥ ۸۵٪.
- AC-TEST-03: همهٔ تست‌های امنیتی §5 سبز.
- AC-TEST-04: همهٔ تست‌های معماری §6 سبز.
- AC-TEST-05: صفر `E_DEPRECATED` در ماتریس نسخه §7.
- AC-TEST-06: همهٔ benchmarkها اهداف DOC-112 را پاس می‌کنند.
