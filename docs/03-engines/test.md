# DOC-084E — موتور Test | چارچوب تست خودنوشته

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-084E |
| Engine ID | `tavaan.engine.test` |
| Namespace | `Tavaan\Testing` |
| اولویت | **P0** — فاز M8 (اما از M1 استفاده می‌شود) |
| وابستگی | همهٔ موتورها |
| LOC | ۲۵۰۰ |

> ⚠️ این موتور خودش ابزار تست است؛ برای تست خودِ آن از یک Assertion حداقلی + bootstrap جدا استفاده می‌شود.

## ۱. هدف
چارچوب تست **خودنوشته و صفر‌وابستگی** (بدون PHPUnit در زمان اجرا؛ هرچند با PHPUnit
سازگار است تا در dev قابل استفاده باشد). شامل Assertion، Mock/Fake، HttpTest،
DatabaseTest (SQLite in-memory)، و Runner.

## ۲. دو حالت
| حالت | توضیح |
|---|---|
| **Native Runner** | `tvn test` — خودنوشته، بدون PHPUnit |
| **PHPUnit Bridge** | سازگاری با PHPUnit اگر نصب باشد (dev) |

🔒 هسته باید با Native Runner تست شود (اصل صفر‌وابستگی).

## ۳. Assertion API
```php
final class TestCase
{
    protected function setUp(): void {}
    protected function tearDown(): void {}

    // Assertions
    self::assertTrue($cond); self::assertFalse($cond);
    self::assertSame($expected, $actual); self::assertEquals($expected, $actual);
    self::assertNotSame($a, $b);
    self::assertNull($v); self::assertNotNull($v);
    self::assertCount(3, $array);
    self::assertContains($needle, $haystack);
    self::assertArrayHasKey('k', $arr);
    self::assertInstanceOf(Foo::class, $obj);
    self::assertStringContainsString('x', $s);
    self::assertMatchesRegex('/^\d+$/', $s);
    self::assertGreaterThan(5, $n);
    self::assertEqualsWithDelta(1.0, $actual, 0.001);
    self::assertEmpty($v); self::assertNotEmpty($v);

    // Exception
    $this->expectException(FooException::class);
    $this->expectExceptionMessageId('foo.bar');
    $this->expectExceptionCode(123);

    // Fail
    self::fail('پیام');
}
```

## ۴. Mock/Fake
```php
// Mock ساده (بدون Mockery)
$mock = Mock::of(CacheRepository::class)
    ->shouldReceive('get')->with('key')->andReturn('value')
    ->shouldReceive('put')->once()
    ->getMock();

// Fakeهای داخلی هر موتور
Event::fake();
Mail::fake();
Queue::fake();
Http::fake();
Storage::fake('public');       // filesystem مجازی در حافظه
Cache::fake();
Auth::fake($user);
```

### ۴٫۱ Mock Implementation
```php
final class Mock
{
    /** @param class-string $interface */
    public static function of(string $interface): MockBuilder
    {
        return new MockBuilder($interface);
    }
}

final class MockBuilder
{
    /** @var array<string, list<Expectation>> */
    private array $expectations = [];

    public function shouldReceive(string $method): Expectation
    {
        return $this->expectations[$method][] = new Expectation($method);
    }

    public function getMock(): object
    {
        // 🔒 ساخت anonymous class بدون eval
        $expectations = $this->expectations;
        return new class ($this->interface, $expectations) implements MockProxy {
            public function __construct(private string $interface, private array $expectations) {}
            public function __call(string $method, array $args): mixed {
                return $this->resolve($method, $args);
            }
        };
    }
}
```
⚠️ Mock بدون `eval` با `__call` + anonymous class. برای Interfaceهای تایپ‌شده،
کلاس Proxy از پیش‌نوشته یا تولید فایل (مثل LazyProxy).

## ۵. HttpTest
```php
final class ProductTest extends TestCase
{
    public function testProductIndex_ReturnsProducts(): void
    {
        $response = $this->get('/api/v1/products');
        $response->assertOk()
                 ->assertJsonStructure(['data' => [['id','title','price']]])
                 ->assertJsonCount(10, 'data');
    }

    public function testStoreProduct_WithInvalidData_Returns422(): void
    {
        $response = $this->postJson('/api/v1/products', ['title' => '']);
        $response->assertStatus(422)->assertJsonValidationErrors(['title']);
    }

    public function testGuest_CannotAccessAdmin(): void
    {
        $this->get('/admin')->assertUnauthorized();
    }

    public function testAuthedUser_CanCreateOrder(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/orders', [...])->assertCreated();
    }
}
```
```php
trait MakesHttpRequests
{
    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, headers: $headers);
    }
    protected function postJson(string $uri, array $data, array $headers = []): TestResponse
    {
        return $this->json('POST', $uri, $data, $headers);
    }
    protected function call(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $request = Request::create($uri, $method, $data, [], [], $this->transformHeaders($headers));
        $response = $this->kernel->handle($request);
        return new TestResponse($response);
    }
    protected function actingAs(User $user, string $guard = 'web'): static
    {
        $this->app->make(AuthManager::class)->guard($guard)->setUser($user);
        return $this;
    }
}
```

## ۶. DatabaseTest (SQLite in-memory)
```php
trait RefreshDatabase
{
    protected function setUpDatabase(): void
    {
        $this->app->make('config')->set('db.default', 'sqlite_memory');
        $this->migrate();                    // اجرای همهٔ migrationها در حافظه
    }
}

// Factory
final class UserFactory extends Factory
{
    protected string $model = User::class;
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->email(),
            'password' => Hash::make('password'),
        ];
    }
    public function admin(): self { return $this->state(['role' => 'admin']); }
}
// User::factory()->count(5)->admin()->create();
```

### ۶٫۱ Faker خودنوشته (بدون fzaninotto/faker)
```php
final class Faker
{
    public function __construct(private ?int $seed = null) { /* Deterministic با seed */ }
    public function name(): string { /* از فهرست نام‌های فارسی/انگلیسی */ }
    public function email(): string { /* … */ }
    public function mobile(): string { return '09' . \random_int(100000000, 999999999); }
    public function nationalCode(): string { /* تولید کد ملی معتبر با checksum */ }
    public function numberBetween(int $min, int $max): int;
    public function text(int $maxChars = 200): string;
    public function dateTime(): \DateTimeImmutable;
    public function jalaliDate(): string;
    public function unique(): self { /* بدون تکرار */ }
}
```
🔒 با `seed` → **Deterministic** (برای تست تکرارپذیر).

## ۷. Test Runner
```bash
tvn test                          # همه
tvn test --group=cache
tvn test --filter=ProductTest
tvn test engines/cache            # یک موتور
tvn test --coverage               # با Xdebug/PCOV (اختیاری)
tvn test --parallel=4
```

```php
final class TestRunner
{
    public function run(TestSuite $suite): TestResult
    {
        $result = new TestResult();
        foreach ($suite->tests() as $test) {
            $reflection = new \ReflectionClass($test);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!\str_starts_with($method->getName(), 'test')) { continue; }
                $this->runTest($test, $method, $result);
            }
        }
        return $result;
    }

    private function runTest(object $test, \ReflectionMethod $method, TestResult $result): void
    {
        $instance = new ($test::class)();
        $this->callIf($instance, 'setUp');
        $start = \microtime(true);
        try {
            $method->invoke($instance);
            $result->passed($method->getName(), (\microtime(true) - $start) * 1000);
        } catch (AssertionFailedError $e) {
            $result->failed($method->getName(), $e);
        } catch (\Throwable $e) {
            $result->errored($method->getName(), $e);
        } finally {
            $this->callIf($instance, 'tearDown');
        }
    }
}
```
خروجی:
```
✓ ContainerTest::testMake_Singleton (2.1ms)
✓ ContainerTest::testCircularDependency_Throws (1.3ms)
✗ RouterTest::testMatch_Wildcard
  Failed asserting that '/shop/x' matches route.
  at engines/router/tests/Unit/RouterTest.php:142

Tests: 152, Passed: 151, Failed: 1, Errors: 0, Time: 342ms
```

## ۸. TestResponse Assertions
```php
$response->assertOk();                    // 200
$response->assertStatus(404);
$response->assertCreated();               // 201
$response->assertUnauthorized();          // 401
$response->assertForbidden();             // 403
$response->assertNotFound();              // 404
$response->assertJson(['key'=>'value']);
$response->assertJsonStructure([...]);
$response->assertJsonCount(5, 'data');
$response->assertJsonValidationErrors(['email']);
$response->assertRedirect('/login');
$response->assertSee('متن');
$response->assertDontSee('متن');
$response->assertCookie('name');
$response->assertHeader('X-Tavaan-Ref');
```

## ۹. Coverage (با Xdebug/PCOV اختیاری)
```php
// tvn test --coverage
// اگر ext-xdebug/pcov → محاسبهٔ پوشش خط
// اگر نبود → هشدار و ادامه بدون coverage
```

## ۱۰. امنیت
| نکته |
|---|
| تست‌ها بدون I/O واقعی (CON-11) |
| SQLite in-memory یا Fake drivers |
| بدون شبکهٔ واقعی (Http::fake) |
| Deterministic (بدون وابستگی به زمان/random بدون seed) |

## ۱۱. معیار پذیرش 🧪
- AC-TEST-01: Native Runner همهٔ تست‌های هسته را بدون PHPUnit اجرا می‌کند.
- AC-TEST-02: HttpTest یک درخواست کامل را درون‌فرایندی اجرا می‌کند.
- AC-TEST-03: DatabaseTest با SQLite in-memory و migrate.
- AC-TEST-04: Faker Deterministic با seed.
- AC-TEST-05: خروجی رنگی + گزارش شکست با فایل:خط.

## ۱۲. مراجع
| منبع | تفاوت |
|---|---|
| PHPUnit | Assertion/Runner | خودنوشته، سازگار (bridge) |
| Laravel TestCase | HttpTest/Factory | بدون وابستگی به PHPUnit |
| Mockery | Mock | Mock ساده با `__call` |
| FakerPHP | Faker | خودنوشته با دادهٔ فارسی |

## ۱۳. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.test.v1
engine:
  id: tavaan.engine.test
  namespace: Tavaan\Testing
  path: engines/test
  priority: P0
  phase: M8
  depends_on: [all]
  facade: null
  public_api:
    - class: TestCase
      methods: [assertTrue, assertSame, assertEquals, expectException, get, post, postJson, actingAs, ...]
    - class: TestRunner
      methods: [run, suite, result]
    - class: Mock
      methods: [of]
    - class: Factory
      methods: [definition, create, make, count, state]
  loc_estimate: 2500
```
