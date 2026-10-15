# DOC-026 — کانتینر و تزریق وابستگی (Container & DI) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-026 |
| موتور | `engines/container` (`Tavaan\Engine\Container`) |
| والد | DOC-025 |
| وابستگی | فقط `core` (Autoloader, Reflection) |

---

## ۱. مسئولیت کانتینر

1. نگهداری Bindingها (abstract → concrete/factory)
2. Resolve خودکار وابستگی‌ها با Reflection
3. مدیریت عمر (Singleton / Scoped / Transient / Factory)
4. Contextual Binding (وابستگی به مصرف‌کننده)
5. Tagging و Batch Resolve
6. Lazy Proxy برای سرویس‌های سنگین
7. Method/Callback Injection (`call()`)
8. Extender/Decorator
9. ارائهٔ سرویس به Facade

---

## ۲. مدل دادهٔ داخلی

```php
final class Container implements ContainerInterface, \ArrayAccess
{
    /** @var array<string, Binding> */
    private array $bindings = [];

    /** @var array<string, object> نمونه‌های ساخته‌شده (singleton/scoped) */
    private array $instances = [];

    /** @var array<string, string> abstract → alias */
    private array $aliases = [];

    /** @var array<string, list<string>> tag → abstracts */
    private array $tags = [];

    /** @var array<string, array<string, string|Closure>> consumer → (need → give) */
    private array $contextual = [];

    /** @var array<string, list<Closure>> abstract → extenders */
    private array $extenders = [];

    /** @var array<string, true> در حال Resolve — برای تشخیص چرخه */
    private array $buildStack = [];

    /** @var array<string, ResolvedParameter[]> کش Reflection */
    private array $reflectionCache = [];
}

final readonly class Binding
{
    public function __construct(
        public string $abstract,
        public \Closure|string|null $concrete,
        public Lifetime $lifetime,          // Singleton | Scoped | Transient | Factory
        public bool $shared,
        public ?string $scopeId = null,
    ) {}
}

enum Lifetime: string
{
    case Singleton = 'singleton';   // یک نمونه در کل فرایند
    case Scoped    = 'scoped';      // یک نمونه در هر Request/Job
    case Transient = 'transient';   // نمونهٔ جدید در هر make
    case Factory   = 'factory';     // همیشه از Closure
}
```

---

## ۳. API عمومی

### ۳٫۱ ثبت (Registration)

| متد | امضا | رفتار |
|---|---|---|
| `bind` | `bind(string $abstract, Closure\|string\|null $concrete = null, bool $shared = false): void` | ثبت عمومی |
| `singleton` | `singleton(string $abstract, Closure\|string\|null $concrete = null): void` | Lifetime::Singleton |
| `scoped` | `scoped(string $abstract, Closure\|string\|null $concrete = null): void` | Lifetime::Scoped |
| `factory` | `factory(string $abstract, Closure $factory): void` | همیشه نمونهٔ جدید |
| `instance` | `instance(string $abstract, object $instance): object` | ثبت نمونهٔ آماده |
| `alias` | `alias(string $abstract, string $alias): void` | نام مستعار |
| `tag` | `tag(array\|string $abstracts, string ...$tags): void` | برچسب‌گذاری |
| `when` | `when(string $consumer): ContextualBindingBuilder` | وابستگی زمینه‌ای |
| `extend` | `extend(string $abstract, Closure $extender): void` | تزئین |
| `bindIf` | `bindIf(...): void` | فقط اگر ثبت نشده |
| `singletonIf` | `singletonIf(...): void` | فقط اگر ثبت نشده |

### ۳٫۲ بازیابی (Resolution)

| متد | امضا | رفتار |
|---|---|---|
| `make` | `make(string $abstract, array $parameters = []): mixed` | ساخت با تزریق |
| `get` | `get(string $id): mixed` | معادل `make` (سازگاری با PSR-11) |
| `has` | `has(string $id): bool` | بررسی وجود |
| `call` | `call(callable\|array\|string $callback, array $parameters = []): mixed` | تزریق در متد |
| `tagged` | `tagged(string $tag): iterable` | همهٔ members تگ (Generator) |
| `makeAll` | `makeAll(array $abstracts): array` | Resolve دسته‌ای |
| `lazy` | `lazy(string $abstract): object` | Proxy تنبل |
| `flush` | `flush(): void` | پاک‌کردن همه |
| `flushScoped` | `flushScoped(): void` | فقط Scoped (برای Resident) |
| `forgetInstance` | `forgetInstance(string $abstract): void` | حذف یک نمونه |

### ۳٫۳ introspection (برای ابزار/دیباگ)

| متد | خروجی |
|---|---|
| `bindings(): array` | فهرست همهٔ bindingها |
| `resolved(): array` | چه چیزهایی Resolve شده |
| `instances(): array` | نمونه‌های زنده |
| `dependencyGraph(): array` | گراف وابستگی (برای `tvn container:graph`) |
| `stats(): ContainerStats` | تعداد، زمان Resolve، حافظه |

---

## ۴. الگوریتم Resolve (Auto-Wiring)

```
function make(abstract, parameters):
  1. abstract = resolveAlias(abstract)                     # alias → real
  2. if instances[abstract] exists → return it             # shared
  3. detectCycle(abstract)                                  # buildStack
  4. concrete = bindings[abstract].concrete ?? abstract
  5. if concrete is Closure:
        object = concrete(this, parameters)
     elif concrete is string and is a class:
        object = build(concrete, parameters)
     elif is_callable(concrete) and class-string:
        object = build(concrete, parameters)
     else:
        throw BindingResolutionException
  6. object = applyExtenders(abstract, object)
  7. fire resolving callbacks (observers)
  8. if shared → instances[abstract] = object
  9. popBuildStack
 10. return object
```

### ۴٫۱ الگوریتم `build()` (Reflection Injection)

```
function build(className, parameters):
  1. ref = ReflectionClass(className)                      # کش‌شده
  2. if not ref.isInstantiable() → throw BindingResolutionException
  3. ctor = ref.getConstructor()
  4. if ctor === null → return new className()
  5. deps = []
     for each param in ctor.getParameters():
        name = param.getName()
        if array_key_exists(name, parameters):              # override صریح
            deps[] = parameters[name]; continue
        if contextual binding exists for (className, paramType):
            deps[] = make(contextualTarget); continue
        type = param.getType()
        if type === null:
            if param.isDefaultValueAvailable() → deps[] = default
            else → throw BindingResolutionException("untyped parameter")
        if type is BuiltinType:
            → default value / parameters[name] / throw
        if type is Union/Intersection:
            → اولین گزینهٔ قابل Resolve (با ترتیب قطعی) یا throw
        if type is NamedType(class):
            → deps[] = make(className, )                      # بازگشتی
        if type allowsNull and no default → deps[] = null
  6. return ref.newInstanceArgs(deps)
```

### ۴٫۲ تشخیص چرخه (Circular Dependency)
```
detectCycle(abstract):
  if abstract in buildStack:
     throw CircularDependencyException(
        messageId: 'container.circular_dependency',
        context: ['path' => [...buildStack, abstract]],
        hint: 'وابستگی چرخه‌ای را با Interface یا Lazy Proxy بشکنید.'
     )
  buildStack[] = abstract
```
🔒 پیام خطا باید **مسیر کامل چرخه** را چاپ کند:
```
A → B → C → A
```

### ۴٫۳ کش Reflection
- 🔒 نتایج `ReflectionClass`/`ReflectionParameter` باید کش شود (بسیار گران).
- در PROD از `storage/cache/container-reflection.php` (ClassMap + Parameter Signature) استفاده شود.
- ساختار کش: `array<class-string, list<{name, type, builtin, nullable, hasDefault, default, isVariadic, isPromoted}>>`

---

## ۵. Contextual Binding

```php
// همهٔ NotificationChannelهایی که در Mailer لازم است، SmtpChannel باشد
$container->when(MailerService::class)
    ->needs(NotificationChannelInterface::class)
    ->give(SmtpChannel::class);

// با Closure
$container->when(SmsService::class)
    ->needs(GatewayInterface::class)
    ->give(fn ($app) => $app->make(GatewayManager::class)->driver('kavenegar'));

// برای پارامتر نامی خاص
$container->when(ReportExporter::class)
    ->needs('$delimiter')
    ->give(';');
```

**ساختار داخلی:**
```php
$this->contextual['Modules\Shop\Services\MailerService'][NotificationChannelInterface::class] = SmtpChannel::class;
$this->contextual['App\Exporter']['$delimiter'] = ';';
```

---

## ۶. Tagging

```php
$container->tag([RedisStore::class, FileStore::class, ArrayStore::class], 'cache.store');
$container->tag(SmsChannel::class, 'notification.channel', 'sms.channel');

foreach ($container->tagged('cache.store') as $store) { /* … */ }
```
🔒 `tagged()` یک **Generator** برمی‌گرداند (Lazy) تا همه نمونه‌ها یکجا ساخته نشوند.

---

## ۷. Lazy Proxy

برای سرویس‌های سنگین (DB Connection، Search Client) که ممکن است در هر درخواست لازم نشوند:

```php
final class LazyProxyFactory
{
    /**
     * ساخت Proxy که در اولین دسترسی، هدف را Resolve می‌کند.
     *
     * @param class-string<T> $abstract
     * @return T
     * @template T
     */
    public function make(ContainerInterface $container, string $abstract): object
    {
        // بدون eval: استفاده از __call در کلاس Proxy عمومی + Interface-specific subclass
        return new LazyProxy(
            resolver: static fn (): object => $container->make($abstract),
            interfaces: \class_implements($abstract) ?: [],
        );
    }
}

final class LazyProxy
{
    private ?object $resolved = null;

    /** @param Closure(): object $resolver */
    public function __construct(private readonly \Closure $resolver, private readonly array $interfaces = []) {}

    public function __call(string $method, array $args): mixed
    {
        return ($this->target())->{$method}(...$args);
    }

    public function __get(string $name): mixed   { return $this->target()->{$name}; }
    public function __set(string $name, mixed $v): void { $this->target()->{$name} = $v; }
    public function __isset(string $name): bool  { return isset($this->target()->{$name}); }
    public function instance(): object           { return $this->target(); }

    private function target(): object
    {
        return $this->resolved ??= ($this->resolver)();
    }
}
```
> 🔒 برای Interfaceهای مشخص، Proxy باید `implements` همان Interface را داشته باشد تا Type Check پاس شود.
> راه‌حل: تولید کلاس Proxy با `eval` **ممنوع** → به‌جای آن، از قبل برای Interfaceهای پرکاربرد
> کلاس Proxy نوشته شود (`LazyCacheStore implements CacheStoreInterface`) یا از `Container::lazy()` با
> Closure استفاده شود. این یک ADR است (ADR-0016).

---

## ۸. Facade

```php
abstract class Facade
{
    protected static ?ContainerInterface $container = null;
    /** @var array<string, object> */
    protected static array $resolvedInstance = [];

    abstract protected static function accessor(): string;   // نام binding

    public static function setFacadeContainer(ContainerInterface $c): void { static::$container = $c; }

    public static function getFacadeRoot(): object
    {
        $key = static::accessor();
        return static::$resolvedInstance[$key] ??= static::$container->make($key);
    }

    public static function clearResolvedInstance(string $name): void { unset(static::$resolvedInstance[$name]); }
    public static function clearResolvedInstances(): void { static::$resolvedInstance = []; }

    /** @param list<mixed> $args */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();
        if (!$instance->can($method)) { /* … */ }
        return $instance->{$method}(...$args);
    }

    /** برای تست */
    public static function swap(object $instance): void
    {
        static::$resolvedInstance[static::accessor()] = $instance;
        static::$container?->instance(static::accessor(), $instance);
    }

    public static function spy(): FacadeSpy { /* ضبط فراخوانی‌ها */ }
    public static function shouldReceive(string ...$methods): MockExpectation { /* … */ }
}

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @mixin \Tavaan\Engine\Config\Repository
 */
final class Config extends Facade
{
    protected static function accessor(): string { return 'config'; }
}
```

### ۸٫۱ قواعد Facade
| # | قاعده |
|---|---|
| F1 | 🔒 Facade فقط برای سرویس‌های پرکاربرد (Config, Cache, Event, Log, Auth, DB, View, Route, Lang) |
| F2 | 🔒 هر Facade باید `@mixin` و `@method` دقیق داشته باشد (IDE + Static Analysis) |
| F3 | 🔒 در Domain Layer استفاده از Facade **ممنوع** |
| F4 | ⚠️ در تست حتماً `Facade::swap()` یا `Facade::clearResolvedInstances()` |
| F5 | 🔒 Facade نباید State داشته باشد |

---

## ۹. Service Provider

```php
abstract class ServiceProvider
{
    public function __construct(protected ContainerInterface $app) {}

    /** فقط Binding — بدون side effect، بدون resolve سرویس دیگر */
    abstract public function register(): void;

    /** پس از ثبت همهٔ Providerها */
    public function boot(): void {}

    /** در پایان فرایند (فقط Resident/CLI) */
    public function shutdown(): void {}

    /** برای Providerهای تنبل */
    public function provides(): array { return []; }
    public function isDeferred(): bool { return false; }

    // ── هلپرهای ثبت ─────────────────────────────────────────────
    protected function bindConfig(string $file, string $key): void { /* … */ }
    protected function mergeConfigFrom(string $path, string $key): void { /* … */ }
    protected function loadRoutesFrom(string $path, string $group = 'web'): void { /* … */ }
    protected function loadViewsFrom(string $path, string $namespace): void { /* … */ }
    protected function loadTranslationsFrom(string $path, string $namespace): void { /* … */ }
    protected function loadMigrationsFrom(string|array $paths): void { /* … */ }
    protected function commands(array|string $commands): void { /* … */ }
    protected function publishes(array $paths, ?string $group = null): void { /* … */ }
}
```

### ۹٫۱ نمونهٔ کامل Provider یک موتور

```php
<?php
declare(strict_types=1);

namespace Tavaan\Engine\Cache;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepositoryContract;
use Tavaan\Core\Contracts\Cache\StoreInterface;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Provider\ServiceProvider;
use Tavaan\Engine\Cache\Drivers\ArrayStore;
use Tavaan\Engine\Cache\Drivers\FileStore;
use Tavaan\Engine\Cache\Drivers\RedisStore;

final class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheManager::class, static fn ($app): CacheManager
            => new CacheManager($app->make('config')));

        $this->app->singleton(CacheRepositoryContract::class, static function ($app): CacheRepository {
            /** @var CacheManager $manager */
            $manager = $app->make(CacheManager::class);
            return new CacheRepository($manager->store(), $app->make(DispatcherInterface::class));
        });

        $this->app->alias(CacheRepositoryContract::class, 'cache');

        $this->app->extend(CacheRepositoryContract::class, static function (CacheRepository $repo, $app): CacheRepository {
            return $app->make('config')->get('cache.tags.enabled', false)
                ? new TaggedCacheRepository($repo)
                : $repo;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CacheClearCommand::class, CacheStatsCommand::class]);
        }
        $this->mergeConfigFrom(__DIR__ . '/../config/cache.php', 'cache');
    }
}
```

---

## ۱۰. Deferrable Provider (بارگذاری تنبل)

```php
final class MailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function provides(): array
    {
        return [MailerInterface::class, 'mailer', TransportManager::class];
    }

    public function register(): void { /* … */ }
}
```
- در `config/providers.php` ثبت می‌شود اما `register()` فقط وقتی صدا زده می‌شود که
  یکی از `provides()` درخواست شود.
- 🔒 مزیت: کاهش زمان بوت تا ۴۰٪ در درخواست‌های ساده.
- 🔒 پیاده‌سازی: `DeferredProviderRegistry` که نگاشت `abstract → providerClass` را نگه می‌دارد و
  در `Container::make()` hook می‌شود.

---

## ۱۱. Method Injection (`call`)

```php
$container->call([$controller, 'show'], ['product' => $product]);
$container->call('Modules\Shop\Http\Controllers\OrderController@store');
$container->call(fn (CacheRepository $cache, Config $config) => /* … */);
$container->call(SomeClass::class . '@handle');
```
الگوریتم همان `build()` است اما برای `ReflectionMethod`/`ReflectionFunction`.

---

## ۱۲. کارایی کانتینر

| تکنیک | اثر |
|---|---|
| Reflection Cache در حافظه | ~۶۰٪ کاهش زمان اولین Resolve |
| ClassMap Reflection در `storage/cache/` | حذف کامل Reflection در PROD |
| Compiled Container (تولید کد PHP برای bindingهای ثابت) | ~۸۰٪ کاهش (اختیاری) |
| Lazy Proxy برای سرویس‌های سنگین | کاهش حافظه تا ۲MB |
| Deferrable Provider | کاهش زمان بوت |
| `array_key_exists` به‌جای `isset` برای null-safe lookup | دقت |
| جلوگیری از `Closure::bind` غیرضروری | — |

### ۱۲٫۱ Compiled Container (اختیاری، فاز M9)
```php
// storage/cache/container.php
return [
    'config' => static fn ($app) => new \Tavaan\Engine\Config\Repository($app['config.loader']),
    'events' => static fn ($app) => new \Tavaan\Engine\Event\EventDispatcher(),
    // …
];
```
🔒 تولید با `tvn container:compile` و invalidate خودکار با Hash فایل‌های Provider.

---

## ۱۳. اهداف کارایی 🧪

| عملیات | هدف |
|---|---|
| `make()` برای کلاس بدون وابستگی | ≤ ۲µs |
| `make()` برای کلاس با ۵ وابستگی (کش‌شده) | ≤ ۲۰µs |
| `make()` اولین بار با Reflection کامل | ≤ ۳۰۰µs |
| `call()` با ۳ پارامتر | ≤ ۲۵µs |
| ۱۰۰۰ Resolve متوالی (singleton) | ≤ ۱ms |
| حافظهٔ کانتینر با ۱۵۰ binding | ≤ ۱٫۵MB |

---

## ۱۴. معیار پذیرش 🧪

- AC-DI-01: Resolve خودکار Constructor با ۵ سطح تو‌در‌تو بدون خطا.
- AC-DI-02: وابستگی چرخه‌ای → `CircularDependencyException` با مسیر کامل.
- AC-DI-03: Union Type `(A&B)|null` به‌درستی Resolve می‌شود.
- AC-DI-04: پارامتر با Default Value و بدون Type → مقدار پیش‌فرض.
- AC-DI-05: Contextual Binding برای دو مصرف‌کنندهٔ متفاوت، پیاده‌سازی متفاوت می‌دهد.
- AC-DI-06: `tagged()` Lazy است (تعداد `make` = تعداد iterate).
- AC-DI-07: Deferrable Provider تا زمان نیاز `register()` نمی‌شود.
- AC-DI-08: `flushScoped()` نمونه‌های Scoped را حذف و Singletonها را حفظ می‌کند.
- AC-DI-09: Facade `swap()` در تست کار می‌کند و پس از تست ریست می‌شود.
- AC-DI-10: اهداف بخش ۱۳ در بنچمارک پاس می‌شوند.
- AC-DI-11: در حالت Resident، Request دوم سرویس Request اول را نمی‌بیند.
