# DOC-031 — موتور Container | ظرف سرویس

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-031 |
| Engine ID | `tavaan.engine.container` |
| Namespace | `Tavaan\Engine\Container` |
| مسیر | `engines/container/` |
| اولویت | **P0** — فاز M1 (اولین موتور) |
| وابستگی | هیچ (فقط `core/Contracts`) |
| افزونه | هیچ |
| Facade | `App` → accessor `app` |
| LOC برآوردی | ۱۲۰۰ |
| مرتبط | DOC-026 (تحلیل معماری DI) |

---

## ۱. هدف و دامنه

**هدف:** فراهم‌کردن یک ظرف تزریق وابستگی کامل، سریع و صفر‌وابستگی که جایگزین
`illuminate/container` و `symfony/dependency-injection` شود.

- ✅ شامل: Bind/Singleton/Scoped/Factory، Auto-Wiring با Reflection، Contextual Binding،
  Tagging، Extender/Decorator، Lazy Proxy، Method Injection، تشخیص چرخه، کش Reflection،
  Compiled Container.
- ❌ خارج: Production DI با Cache گرم از YAML/XML (فقط PHP)، AOP عمومی، Proxy با `eval`.

**الزامات پوشش‌داده‌شده:** FR-CORE-02، FR-CORE-03، NFR-PERF-04، NFR-QUA-01.

### مقایسه با مراجع
| مرجع | قوت | تغییر تَوان |
|---|---|---|
| `illuminate/container` | ساده، Auto-Wiring خوب | افزودن `scoped`/`factory` صریح، کش Reflection پایدار، حذف `Closure::bind` گران |
| `symfony/DI` | کامپایل، قدرتمند | سادگی (بدون XML/YAML)، کامپایل اختیاری |
| `php-di` | Attribute-based | پشتیبانی از `#[Inject]` بدون Annotation Parser سنگین |

---

## ۲. مفاهیم

| واژه | تعریف |
|---|---|
| **Abstract** | کلید ثبت (معمولاً `class-string` یا نام کوتاه مانند `'config'`) |
| **Concrete** | پیاده‌سازی (کلاس یا Closure) |
| **Lifetime** | `Singleton` / `Scoped` / `Transient` / `Factory` |
| **Scope** | دامنهٔ عمر (Request، Job، Tenant) |
| **Contextual Binding** | وابستگی به مصرف‌کننده |
| **Tag** | برچسب گروهی برای Resolve دسته‌ای |
| **Extender** | Decorator روی نمونهٔ ساخته‌شده |

---

## ۳. معماری داخلی

```
                    ┌──────────────────────────────────┐
   make($abs) ────▶ │            Container             │
                    │                                  │
                    │  AliasResolver ─▶ BindingRegistry│
                    │        │               │         │
                    │        ▼               ▼         │
                    │  InstanceStore   BuilderPipeline  │
                    │  (singleton/     ┌────────────┐  │
                    │   scoped)        │ 1.Contextual│ │
                    │                  │ 2.Reflection│ │
                    │                  │ 3.Build     │  │
                    │                  │ 4.Extenders │  │
                    │                  │ 5.Store     │  │
                    │                  └────────────┘  │
                    │                                  │
                    │  CycleDetector (buildStack)      │
                    │  ReflectionCache (memory + file) │
                    │  StatsCollector                  │
                    └──────────────────────────────────┘
```

### ۳٫۲ فهرست کلاس‌ها

| کلاس | نوع | مسئولیت | LOC | عمومی |
|---|---|---|---|---|
| `Container` | class | پیاده‌سازی اصلی `ContainerInterface` | 650 | ✅ |
| `ContainerServiceProvider` | Provider | ثبت خود + bootstrap | 60 | ✅ |
| `Binding` | readonly class | رکورد یک binding | 40 | ✅ |
| `Lifetime` | enum | Singleton/Scoped/Transient/Factory | 30 | ✅ |
| `ContextualBindingBuilder` | class | fluent `when()->needs()->give()` | 70 | ✅ |
| `ReflectionCache` | class | کش پارامترهای Constructor | 180 | ✅ |
| `ParameterSignature` | readonly | توصیف یک پارامتر | 45 | ⚠️ |
| `LazyProxy` | class | Proxy تنبل | 90 | ✅ |
| `CompiledContainer` | class | بارگذاری binding کامپایل‌شده | 120 | ✅ |
| `ContainerStats` | readonly | آمار Resolve | 30 | ✅ |
| `ScopeManager` | class | مدیریت Scope (Request/Job) | 90 | ✅ |
| `Exceptions/*` | exceptions | ۴ استثنا | 100 | ✅ |

---

## ۴. API عمومی

→ امضای کامل در **DOC-014 §2.1**. در اینجا نکات رفتاری:

```php
$app = new Container();

// ثبت
$app->singleton(LoggerInterface::class, StreamLogger::class);
$app->scoped(RequestContext::class);
$app->factory(Ulid::class, static fn () => Ulid::generate());
$app->instance('config', $configRepo);
$app->alias(LoggerInterface::class, 'log');

// Resolve
$logger = $app->make(LoggerInterface::class);
$log2   = $app->make('log');                      // از طریق alias
$ulid   = $app->make(Ulid::class);                // هر بار جدید

// پارامتر override
$ctrl = $app->make(ProductController::class, ['product' => $product]);

// Contextual
$app->when(Mailer::class)->needs(TransportInterface::class)->give(SmtpTransport::class);

// Tag
$app->tag([SmsChannel::class, MailChannel::class], 'notification.channel');
foreach ($app->tagged('notification.channel') as $channel) { /* lazy */ }

// Method Injection
$app->call([$controller, 'store'], ['id' => 5]);
$app->call('Modules\Shop\Http\Controllers\OrderController@cancel');

// Extend (Decorator)
$app->extend(CacheRepository::class, fn ($repo, $app) => new LoggedCache($repo, $app->make('log')));

// Scope
$app->beginScope('request');
// …
$app->endScope('request');      // یا $app->flushScoped()
```

### ۴٫۱ Facade
```php
/**
 * @method static mixed make(string $abstract, array $parameters = [])
 * @method static void bind(string $abstract, \Closure|string|null $concrete = null, bool $shared = false)
 * @method static void singleton(string $abstract, \Closure|string|null $concrete = null)
 * @method static mixed call(callable|array|string $callback, array $parameters = [])
 * @mixin \Tavaan\Engine\Container\Container
 */
final class App extends Facade
{
    protected static function accessor(): string { return ContainerInterface::class; }
}
```

### ۴٫۲ Helper
```php
app();                       // ContainerInterface
app(LoggerInterface::class); // make
```

---

## ۵. الگوریتم‌ها

### ۵٫۱ Auto-Wiring (بازگشتی)
→ شبه‌کد کامل در **DOC-026 §4.1**.

نکات پیاده‌سازی دقیق:

```php
private function build(string $concrete, array $parameters): object
{
    $signature = $this->reflectionCache->signatureOf($concrete);   // کش‌شده

    if ($signature->constructor === null) {
        return new $concrete();
    }

    $args = [];
    foreach ($signature->parameters as $param) {
        // 1) override صریح
        if (\array_key_exists($param->name, $parameters)) {
            $args[] = $parameters[$param->name];
            continue;
        }
        // 2) contextual
        $ctx = $this->contextual[$concrete][$param->typeString] ?? $this->contextual[$concrete]['$' . $param->name] ?? null;
        if ($ctx !== null) {
            $args[] = $ctx instanceof \Closure ? $ctx($this) : $this->make($ctx);
            continue;
        }
        // 3) تزریق نوع
        if ($param->isClass) {
            try {
                $args[] = $this->make($param->typeString);
                continue;
            } catch (BindingResolutionException $e) {
                if ($param->nullable || $param->hasDefault) {
                    $args[] = $param->hasDefault ? $param->default : null;
                    continue;
                }
                throw $e;
            }
        }
        // 4) Union/Intersection
        if ($param->isUnion) {
            $resolved = $this->resolveUnion($param, $parameters);   // اولین قابل‌ساخت (ترتیب قطعی)
            if ($resolved !== self::UNRESOLVED) { $args[] = $resolved; continue; }
        }
        // 5) Default / Nullable
        if ($param->hasDefault) { $args[] = $param->default; continue; }
        if ($param->nullable)   { $args[] = null; continue; }
        if ($param->isVariadic) { continue; }   // بدون آرگومان

        throw BindingResolutionException::unresolvableParameter($concrete, $param->name);
    }

    return $signature->reflection->newInstanceArgs($args);
}
```

### ۵٫۲ resolveUnion (DNF Types — PHP 8.2)
```
function resolveUnion(param, parameters):
  candidates = []
  for each part in param.type.parts (مرتب بر اساس: class > interface > builtin > null):
     if part is class-string and (isInstantiable(part) or bound(part)):
        candidates[] = part
  if len(candidates) == 1 → return make(candidates[0])
  if len(candidates) > 1:
     if parameters has name → use it
     else → throw AmbiguousUnionException (با فهرست گزینه‌ها در پیام)
  if 'null' in parts → return null
  return UNRESOLVED
```
🔒 **قطعیت:** ترتیب candidates باید بر اساس نام کلاس مرتب شود تا نتیجه در هر اجرا یکسان باشد.

### ۵٫۳ ReflectionCache
```php
final readonly class ParameterSignature
{
    public function __construct(
        public string $name,
        public ?string $typeString,     // FQCN برای class
        public bool $isClass,
        public bool $isUnion,
        public bool $isIntersection,
        public bool $isBuiltin,
        public string $builtinType,     // 'int','string','array','bool','float','iterable','callable','mixed','object'
        public bool $nullable,
        public bool $hasDefault,
        public mixed $default,
        public bool $isVariadic,
        public bool $isPromoted,
        public bool $byReference,
    ) {}
}

final class ReflectionCache
{
    /** @var array<class-string, ConstructorSignature> */
    private array $memory = [];
    private ?array $file = null;
    private bool $dirty = false;

    public function signatureOf(string $class): ConstructorSignature
    {
        return $this->memory[$class]
            ??= $this->file[$class]
            ??= $this->introspect($class);
    }

    private function introspect(string $class): ConstructorSignature
    {
        if (!\class_exists($class) && !\interface_exists($class)) {
            throw BindingResolutionException::classNotFound($class);
        }
        $ref = new \ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return new ConstructorSignature($class, null, [], $ref);
        }
        $params = [];
        foreach ($ctor->getParameters() as $p) {
            $params[] = self::fromReflection($p);   // بدون نگه‌داشتن ReflectionParameter (سنگین)
        }
        return new ConstructorSignature($class, $ctor->getName(), $params, $ref);
    }

    public function dump(string $file): void  { /* var_export به PHP array */ }
    public function load(string $file): void  { /* require */ }
}
```
🔒 در PROD: `ReflectionCache::load(storage/cache/container-reflection.php)` → صفر Reflection.
🔒 `ReflectionClass` نگه داشته می‌شود فقط برای `newInstanceArgs` (سبک‌تر از `new $class(...)` با named args).

### ۵٫۴ تشخیص چرخه
```
buildStack: array<string, int>   (abstract → depth)

onEnter(abstract):
   if isset(buildStack[abstract]):
      path = keys from index of abstract to end + abstract
      throw CircularDependencyException(context: ['path' => path])
   buildStack[abstract] = count(buildStack)

onExit(abstract): unset(buildStack[abstract])
```
🔒 پیام خطا:
```
وابستگی چرخه‌ای شناسایی شد:
  A → B → C → A
راه‌حل: یکی از وابستگی‌ها را با Interface + Lazy Proxy بشکنید یا
از Setter Injection استفاده کنید.
docs: docs/02-architecture/container-di.md#circular
```

### ۵٫۵ Scope Manager
```php
final class ScopeManager
{
    /** @var array<string, array<string, object>> scopeId → (abstract → instance) */
    private array $scopes = [];
    /** @var list<string> */
    private array $stack = [];

    public function begin(string $id): void { $this->scopes[$id] = []; $this->stack[] = $id; }
    public function end(string $id): void   { unset($this->scopes[$id]); \array_pop($this->stack); }
    public function current(): ?string      { return \end($this->stack) ?: null; }
    public function store(string $scopeId, string $abstract, object $i): void { $this->scopes[$scopeId][$abstract] = $i; }
    public function find(string $abstract): ?object
    {
        foreach (\array_reverse($this->stack) as $id) {
            if (isset($this->scopes[$id][$abstract])) { return $this->scopes[$id][$abstract]; }
        }
        return null;
    }
}
```

---

## ۶. پیکربندی

```php
// engines/container/config/container.php
return [
    'reflection_cache' => [
        'enabled' => env_bool('TVN_CONTAINER_REFLECTION_CACHE', false),
        'file'    => storage_path('cache/container-reflection.php'),
    ],
    'compiled' => [
        'enabled' => env_bool('TVN_CONTAINER_COMPILED', false),
        'file'    => storage_path('cache/container.php'),
    ],
    'lazy_proxies' => [
        // abstractهایی که همیشه Lazy ساخته شوند
        'enabled' => true,
        'targets' => [
            \Tavaan\Core\Contracts\Database\ConnectionInterface::class,
            \Tavaan\Core\Contracts\Mail\MailerInterface::class,
            \Tavaan\Core\Contracts\Search\SearchEngineInterface::class,
        ],
    ],
    'stats' => [
        'enabled' => env_bool('TVN_CONTAINER_STATS', false),   // فقط dev
    ],
    'strict' => [
        'allow_untyped_parameters' => false,   // 🔒 پارامتر بدون type → خطا
        'allow_ambiguous_union'    => false,
    ],
];
```

---

## ۷. قراردادها

→ `core/Contracts/Container/ContainerInterface.php` (DOC-014 §2.1)
→ `core/Contracts/Container/ContextualBindingBuilder.php`
→ سازگاری با PSR-11: `Container implements PsrLike\ContainerInterface` (با `get()`/`has()` — بدون نیاز به پکیج `psr/container`؛ Interface داخلی تعریف می‌شود).

---

## ۸. نقاط توسعه

| نوع | نام | توضیح |
|---|---|---|
| Event | `container.resolving` | قبل از ساخت هر سرویس (برای Debug Bar) |
| Event | `container.resolved` | پس از ساخت |
| Hook | `extend()` | Decorator |
| Hook | `ContextualBindingBuilder` | وابستگی زمینه‌ای |
| Contract | `ContainerInterface` | تعویض کامل کانتینر |
| Attribute | `#[Singleton]`, `#[Inject]`, `#[Scoped]` | ثبت خودکار با Discovery |

```php
#[Singleton]
final class MetricsCollector { /* … */ }

final class SomeService
{
    public function __construct(
        #[Inject('cache.redis')] private readonly CacheRepository $cache,
    ) {}
}
```

---

## ۹. امنیت

| تهدید | کنترل |
|---|---|
| تزریق کلاس دلخواه از ورودی کاربر به `make()` | 🔒 `make()` با abstract از ورودی کاربر ممنوع → `assertSafeAbstract()` |
| DoS با چرخهٔ عمیق | CycleDetector + `max_depth = 32` |
| Memory Exhaust با Singletonهای بزرگ | `stats()` + هشدار در Debug Bar |
| نشت سرویس Request در Resident | `flushScoped()` در terminate |

```php
private function assertSafeAbstract(string $abstract): void
{
    if (!\class_exists($abstract) && !\interface_exists($abstract) && !$this->bound($abstract)) {
        throw BindingResolutionException::notFound($abstract);
    }
    if (\str_contains($abstract, "\0") || \strlen($abstract) > 300) {
        throw BindingResolutionException::unsafe($abstract);
    }
}
```
🔒 هرگز `$container->make($_GET['class'])` — در `security-policy` هم ممنوع است.

---

## ۱۰. کارایی

| عملیات | هدف |
|---|---|
| `make()` singleton (کش‌شده) | ≤ ۱µs |
| `make()` transient بدون وابستگی | ≤ ۲µs |
| `make()` با ۵ وابستگی (Reflection Cache) | ≤ ۲۰µs |
| `make()` اولین بار (Reflection کامل) | ≤ ۳۰۰µs |
| `call()` با ۳ پارامتر | ≤ ۲۵µs |
| ۱۰۰۰ `make` متوالی | ≤ ۱ms |
| حافظه با ۲۰۰ binding | ≤ ۱٫۵MB |
| Compiled Container → بهبود | ≥ ۸۰٪ |

**تکنیک‌ها:** `array_key_exists` به‌جای `isset`، کش Reflection، Lazy Proxy، اجتناب از
`Closure::bind`، استفاده از `newInstanceArgs` (سریع‌تر از named args در حلقه).

---

## ۱۱. مدیریت خطا

| استثنا | کد | MessageId |
|---|---|---|
| `BindingResolutionException` | TCR001 | `container.binding_not_found` |
| `CircularDependencyException` | TCR002 | `container.circular_dependency` |
| `AmbiguousUnionException` | TCR003 | `container.ambiguous_union` |
| `UntypedParameterException` | TCR004 | `container.untyped_parameter` |
| `ScopeNotActiveException` | TCR005 | `container.scope_not_active` |

---

## ۱۲. تست

| ID | سناریو | انتظار |
|---|---|---|
| T-01 | Singleton دو بار make → یک نمونه | `assertSame` |
| T-02 | Transient دو بار → دو نمونه | `assertNotSame` |
| T-03 | Auto-wire ۵ سطح تو‌در‌تو | موفق |
| T-04 | چرخه A→B→A | `CircularDependencyException` با path |
| T-05 | Union `(A&B)\|null` | Resolve درست |
| T-06 | پارامتر با default و بدون type + `strict.allow_untyped=false` | خطا |
| T-07 | Contextual برای دو مصرف‌کننده | پیاده‌سازی متفاوت |
| T-08 | `tagged()` lazy (بدون make تا iterate) | شمارش make = 0 |
| T-09 | `extend()` دو بار → زنجیرهٔ decorator | ترتیب صحیح |
| T-10 | `flushScoped()` → singleton باقی، scoped حذف | — |
| T-11 | Variadic parameter | بدون خطا |
| T-12 | Promoted readonly property | تزریق درست |
| T-13 | Enum injection با default | مقدار default |
| T-14 | `call()` روی static method | موفق |
| T-15 | Reflection Cache dump/load → نتیجه یکسان | equality |

---

## ۱۳. مثال End-to-End

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../../core/bootstrap.php';

use Tavaan\Engine\Container\Container;

interface GreeterInterface { public function greet(string $name): string; }

final class PersianGreeter implements GreeterInterface
{
    public function greet(string $name): string { return "سلام {$name}!"; }
}

final class WelcomeService
{
    public function __construct(
        private readonly GreeterInterface $greeter,
        private readonly \DateTimeImmutable $now,
    ) {}

    public function run(string $name): string
    {
        return $this->greeter->greet($name) . ' (' . $this->now->format('H:i') . ')';
    }
}

$c = new Container();
$c->singleton(GreeterInterface::class, PersianGreeter::class);
$c->instance(\DateTimeImmutable::class, new \DateTimeImmutable('2026-10-15 12:00:00'));

echo $c->make(WelcomeService::class)->run('تَوان'), PHP_EOL;
// خروجی: سلام تَوان! (12:00)
```

---

## ۱۴. معیار پذیرش 🧪

- AC-CON-01: همهٔ سناریوهای §12 سبز.
- AC-CON-02: اهداف کارایی §10 در بنچمارک پاس شوند.
- AC-CON-03: صفر `eval`/`create_function` در کد.
- AC-CON-04: در حالت Resident پس از ۱۰۰۰ Request نشت سرویس scoped = ۰.
- AC-CON-05: PHPStan level max بدون Baseline.
- AC-CON-06: `tvn container:graph` گراف DOT تولید می‌کند.

---

## ۱۵. محدودیت و آینده

- ⚠️ Proxy با `eval` ساخته نمی‌شود → Lazy Proxy فقط از طریق `__call` (برای Interfaceهای تایپ‌شده
  نیاز به کلاس Proxy از پیش‌نوشته است). راه‌حل بلندمدت: تولید فایل Proxy در `storage/cache/proxies/`
  با `file_put_contents` + include یک‌باره (ADR-0016).
- 💡 1.1: `#[Inject]` Discovery خودکار، `container:compile`.
- 💡 2.0: Child Container برای ماژول‌ها (Isolation).

---

## ۱۶. مراجع

| منبع | الهام | تفاوت |
|---|---|---|
| Laravel `illuminate/container` | ساختار Binding/Alias/Contextual | افزودن Scoped، ReflectionCache پایدار، حذف `getContextualConcrete` پرهزینه |
| Symfony DI Compiler | ایدهٔ Compiled Container | بدون YAML/XML، فقط PHP |
| PHP-DI | Attribute-based injection | بدون Doctrine Annotations |
| PSR-11 | `get()`/`has()` | Interface داخلی (بدون پکیج `psr/container`) |

---

## ۱۷. بلوک ماشین‌خوان 🧠

```yaml
# machine-readable: tavaan.engine.container.v1
engine:
  id: tavaan.engine.container
  namespace: Tavaan\Engine\Container
  path: engines/container
  priority: P0
  phase: M1
  depends_on: []
  soft_depends_on: []
  extensions: {required: [], optional: []}
  facade: {name: App, accessor: 'Tavaan\Core\Contracts\Container\ContainerInterface'}
  tables: []
  permissions: []
  events_emitted: ['container.resolving', 'container.resolved']
  events_consumed: []
  config_file: engines/container/config/container.php
  config_keys: [container.reflection_cache.enabled, container.compiled.enabled,
                container.lazy_proxies.targets, container.strict.allow_untyped_parameters]
  public_api:
    - class: Container
      methods: [bind, singleton, scoped, factory, instance, alias, tag, tagged, when,
                extend, make, get, has, call, lazy, flush, flushScoped, forgetInstance,
                bindings, dependencyGraph, stats]
  loc_estimate: 1200
  acceptance:
    - {id: AC-CON-01, test: 'engines/container/tests/Unit/ContainerTest.php'}
    - {id: AC-CON-02, test: 'engines/container/benchmarks/container.bench.php'}
    - {id: AC-CON-04, test: 'engines/container/tests/Feature/ResidentScopeTest.php'}
```
