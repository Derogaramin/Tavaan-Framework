# DOC-040 — موتور Router | مسیریاب با Compiled Trie

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-040 |
| Engine ID | `tavaan.engine.router` |
| Namespace | `Tavaan\Engine\Http\Routing` |
| اولویت | **P0** — فاز M2 |
| وابستگی | `http`, `container`, `config` |
| Facade | `Route` → `router` |
| LOC | ۱۴۰۰ |

---

## ۱. هدف

مسیریاب با **تطبیق زیر ۵ میکروثانیه در ۱۰۰۰ روت**، پشتیبانی کامل از Group/Domain/Constraint/
Named Routes/Attribute Routing/Model Binding و **Route Caching**.

---

## ۲. معماری

```
routes/*.php  +  #[Route] Attributes
        │
        ▼
┌────────────────┐    ┌──────────────────┐    ┌──────────────────────┐
│ Router (API)   │───▶│ RouteCollection   │───▶│ RouteCompiler        │
└────────────────┘    └────────┬─────────┘    │ → CompiledMatcher    │
                               │              └──────────┬───────────┘
                               ▼                         ▼
                        ┌─────────────┐         ┌────────────────┐
                        │ RouteMatcher│◀────────│ routes.php cache│
                        └──────┬──────┘         └────────────────┘
                               ▼
                        ┌─────────────┐   ┌──────────────┐   ┌─────────────┐
                        │ Route       │──▶│ControllerRes.│──▶│ModelBinder  │
                        └─────────────┘   └──────────────┘   └─────────────┘
                               │
                               ▼
                        UrlGenerator (route(), current(), signed)
```

---

## ۳. API تعریف روت

```php
// ── ساده ──────────────────────────────────────────────
Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::match(['get','post'], '/login', [AuthController::class, 'login']);
Route::any('/debug', fn () => 'ok');
Route::view('/about', 'pages.about', ['title' => 'درباره ما']);
Route::redirect('/old', '/new', 301);
Route::permanentRedirect('/legacy', '/new');

// ── پارامتر ───────────────────────────────────────────
Route::get('/users/{user}', [UserController::class, 'show']);
Route::get('/posts/{post}/comments/{comment}', [CommentController::class, 'show']);
Route::get('/files/{path}', [FileController::class, 'show'])->where('path', '.*');
Route::get('/blog/{year:\d{4}}/{slug:[a-z0-9\-]+}', [PostController::class, 'show']);

// ── گروه ──────────────────────────────────────────────
Route::prefix('api/v1')
    ->name('api.v1.')
    ->middleware(['api', 'auth:sanctum'])
    ->namespace('Modules\Shop\Http\Controllers\Api')
    ->domain('{tenant}.example.com')
    ->group(function (): void {
        Route::apiResource('products', ProductController::class);
        Route::apiResource('orders', OrderController::class)->only(['index','show','store']);
    });

// ── Resource ──────────────────────────────────────────
Route::resource('photos', PhotoController::class);
//   → index, create, store, show, edit, update, destroy
Route::apiResource('photos', PhotoController::class);
//   → index, store, show, update, destroy
Route::resource('comments', CommentController::class)->shallow();
Route::resource('posts.comments', PostCommentController::class);   // nested

// ── Attribute-based ───────────────────────────────────
#[Route('/admin/users', methods: ['GET'], name: 'admin.users.index', middleware: ['auth','can:admin.user.view'])]
public function index(): View { /* … */ }

// ── Fallback و محدودیت ────────────────────────────────
Route::fallback(fn () => response()->view('errors.404', status: 404));
Route::get('/download/{file}', [DlController::class, 'get'])->whereUuid('file');
```

### ۳٫۱ Fluent API روت

| متد | توضیح |
|---|---|
| `name(string)` | نام یکتا |
| `middleware(array\|string)` | افزودن middleware |
| `withoutMiddleware(array\|string)` | حذف |
| `where(param, regex)` / `whereNumber/Alpha/Alphanumeric/Uuid/Ulid/Slug/In` | محدودیت |
| `defaults(param, value)` | مقدار پیش‌فرض |
| `domain(string)` | محدودیت دامنه (با wildcard) |
| `prefix(string)` | پیشوند مسیر |
| `namespace(string)` | — |
| `scopeBindings()` | Model Binding تو‌در‌تو |
| `cache(seconds, tags)` | کش پاسخ |
| `throttle(string)` | Rate Limit |
| `idempotent()` | Idempotency-Key اجباری |
| `signed()` | امضای URL |
| `can(ability, model)` | مجوز |
| `verified()` | ایمیل تأییدشده |
| `redirectIf(...)` | — |
| `fallbackOrder(int)` | اولویت در fallback |
| `condition(callable)` | شرط پویا |

---

## ۴. ساختار دادهٔ روت

```php
final readonly class RouteDefinition
{
    public function __construct(
        public string $id,                       // hash پایدار
        public string $name,                     // 'shop.product.show'
        public array $methods,                   // ['GET','HEAD']
        public string $uri,                      // '/shop/products/{product}'
        public string $domain,                   // '' یا '{tenant}.example.com'
        public array $action,                    // ['controller'=>…, 'method'=>…] یا Closure
        public array $middleware,
        public array $wheres,                    // param => regex
        public array $defaults,
        public string $namespace,
        public array $meta,                      // cache/throttle/signed/…
        public int $staticSegments,              // تعداد segment ثابت (برای امتیازدهی)
        public bool $hasWildcard,
        public ?string $moduleId,
        public array $attributes,                // از #[Route]
    ) {}
}
```

---

## ۵. الگوریتم تطبیق — Compiled Trie

### ۵٫۱ ساخت Trie
```
روت‌ها:
  GET /shop/products
  GET /shop/products/{product}
  GET /shop/products/{product}/reviews
  GET /shop/cart
  GET /admin/users/{user:\d+}

Trie:
root
├── shop
│   ├── products  [GET → index]
│   │   └── {product}  (constraint: slug)  [GET → show]
│   │       └── reviews [GET → reviews]
│   └── cart      [GET → cart]
└── admin
    └── users
        └── {user} (constraint: \d+)  [GET → show]
```

### ۵٫۲ گره‌ها
```php
final class TrieNode
{
    /** @var array<string, self> segment ثابت → گره */
    public array $static = [];

    /** @var list<ParamEdge> پارامتریک، مرتب بر اساس specificity */
    public array params = [];

    public ?WildcardEdge $wildcard = null;

    /** @var array<string, RouteMatch> method → route */
    public array $handlers = [];
}

final readonly class ParamEdge
{
    public function __construct(
        public string $name,
        public string $regex,        // پیش‌کامپایل
        public bool $isNumeric,
        public bool $hasConstraint,
        public int $specificity,     // هرچه بیشتر، اولویت بالاتر
        public TrieNode $node,
    ) {}
}
```

### ۵٫۳ الگوریتم Match
```
function match(method, path, host):
  segments = split(trim(path, '/'), '/')
  results  = []
  walk(root, segments, 0, params={}, results)

  if results is empty:
      if anyMethodMatches(path) → throw MethodNotAllowed(allowed: [...])
      else → throw RouteNotFound
  else:
      best = selectBest(results)     # بر اساس specificity
      if not domainMatches(best, host) → continue with next
      return best

function walk(node, segments, i, params, results):
  if i == len(segments):
      collect handlers at node → results
      return

  seg = segments[i]

  # 1) Static (بالاترین اولویت)
  if seg in node.static:
      walk(node.static[seg], segments, i+1, params, results)

  # 2) Parametric (مرتب بر اساس specificity)
  for edge in node.params:
      if preg_match(edge.regex, seg):
          walk(edge.node, segments, i+1, params + {edge.name: seg}, results)
          if results is not empty and edge is exclusive: break

  # 3) Wildcard (پایین‌ترین اولویت)
  if node.wildcard:
      walk(node.wildcard.node, segments, len(segments),
           params + {node.wildcard.name: join('/', segments[i:])}, results)
```

### ۵٫۴ Specificity Score (برای انتخاب بهترین)
```
score(route) =
    1000 * count(staticSegments)
  +  100 * count(constrainedParams)
  +   10 * count(unconstrainedParams)
  +    1 * (hasDomain ? 1 : 0)
  -  500 * (hasWildcard ? 1 : 0)
```
🔒 در صورت تساوی → روتی که **زودتر ثبت** شده برنده است (قطعیت).

### ۵٫۵ پیچیدگی
| عملیات | پیچیدگی |
|---|---|
| ساخت Trie (n روت، k segment) | O(n·k) |
| Match (بهترین حالت، همه static) | O(k) — هش در هر سطح |
| Match (بدترین حالت) | O(k · 3^k) نظری، عملاً ≤ O(k·m) |
| حافظه | O(n·k) گره |

### ۵٫۶ Route Cache
```php
// storage/cache/routes.php
return [
    'generated_at' => '2026-10-15T12:00:00+00:00',
    'hash'         => 'sha256:...',           // از فایل‌های route (برای invalidate)
    'trie'         => [ /* serialized structure */ ],
    'routes'       => [ /* RouteDefinition array */ ],
    'names'        => ['shop.product.show' => 'route_17'],
    'patterns'     => [ /* precompiled regex */ ],
];
```
🔒 تولید با `tvn route:cache` و invalidate خودکار با تغییر hash فایل‌های `routes/`.

---

## ۶. Route Group — پیاده‌سازی

```php
public function group(array $attributes, \Closure $routes): void
{
    $previous = $this->groupStack;
    $this->groupStack[] = $attributes;

    try {
        $routes($this);
    } finally {
        $this->groupStack = $previous;
    }
}

private function mergeGroup(RouteDefinition $route): RouteDefinition
{
    foreach ($this->groupStack as $group) {
        $route = $route->withPrefix($group['prefix'] ?? '')
                       ->withName(($group['name'] ?? '') . $route->name)
                       ->withMiddleware($group['middleware'] ?? [])
                       ->withNamespace($group['namespace'] ?? null)
                       ->withDomain($group['domain'] ?? '')
                       ->withWheres($group['where'] ?? []);
    }
    return $route;
}
```

---

## ۷. Model Binding

```php
// Implicit — با type hint در متد کنترلر
public function show(Product $product): View { /* … */ }

// Explicit
Route::model('product', Product::class);
Route::bind('product', fn (string $value): Product => Product::findBySlugOrFail($value));

// با کلید سفارشی
Route::get('/products/{product:slug}', [ProductController::class, 'show']);

// Nested + scopeBindings
Route::get('/users/{user}/orders/{order}', [...])->scopeBindings();
// → order باید متعلق به user باشد، وگرنه 404
```

```php
final class ModelBinder
{
    public function resolve(Request $request, RouteDefinition $route, \ReflectionMethod $method): array
    {
        $args = [];
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $class = $type->getName();
                if (\is_subclass_of($class, ModelInterface::class) || \is_subclass_of($class, UrlRoutable::class)) {
                    $key   = $route->bindingKeyFor($param->getName()) ?? 'id';
                    $value = $route->parameter($param->getName());
                    $model = $this->findModel($class, $key, $value, $route->scopeBindings(), $request);
                    if ($model === null) {
                        if ($type->allowsNull() || $param->isDefaultValueAvailable()) { $args[] = null; continue; }
                        throw ModelNotFoundException::for($class, $value);
                    }
                    $this->authorizeBinding($class, $model, $request);   // 🔒 Policy
                    $args[] = $model;
                    continue;
                }
            }
            // Route parameter ساده یا Container
            $args[] = $route->parameter($param->getName())
                      ?? ($type && !$type->isBuiltin() ? $this->container->make($type->getName()) : null);
        }
        return $args;
    }
}
```
🔒 **امنیت:** Model Binding باید Policy/Ownership را بررسی کند (جلوگیری از IDOR).

---

## ۸. UrlGenerator

```php
route('shop.product.show', ['product' => 42]);           // /shop/products/42
route('shop.product.show', $product);                    // از UrlRoutable::getRouteKey()
url('/assets/app.css');                                  // absolute
action([ProductController::class, 'show'], ['product' => 1]);
Url::current(); Url::previous(); Url::full();
Url::signed('unsubscribe', ['user' => 1], expiresAt: now()->addDays(7));
Url::temporarySignedRoute('verify', now()->addMinutes(60), ['id' => $id]);
Url::isValidSignature($request);
```

### ۸٫۱ الگوریتم ساخت URL
```
1. یافتن route از name (کش `names`)
2. جایگزینی placeholderها با مقادیر (با urlencode برای segment)
3. بررسی همهٔ پارامترهای اجباری پر شده باشند → وگرنه UrlGenerationException
4. حذف پارامترهای استفاده‌نشده به query string
5. افزودن domain (با جایگزینی wildcard)
6. افزودن baseUrl و scheme
7. برای signed: افزودن `signature` و `expires`
```

### ۸٫۲ Signed URL
```php
public function sign(string $url, ?\DateTimeImmutable $expires = null): string
{
    $parts = \parse_url($url);
    $query = [];
    \parse_str($parts['query'] ?? '', $query);
    if ($expires !== null) { $query['expires'] = $expires->getTimestamp(); }

    \ksort($query);                                   // 🔒 ترتیب قطعی
    $payload = $this->normalizeUrl($url) . '?' . \http_build_query($query);

    $query['signature'] = \hash_hmac('sha256', $payload, $this->key);
    return $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '/') . '?' . \http_build_query($query);
}
```

---

## ۹. Attribute Routing

```php
#[RouteGroup(prefix: '/admin/shop', middleware: ['auth','can:shop.manage'], name: 'shop.admin.')]
final class ProductController extends Controller
{
    #[Route('', methods: ['GET'], name: 'product.index')]
    public function index(): View { /* … */ }

    #[Route('/products/{product:slug}', methods: ['GET'], name: 'product.show',
            middleware: 'cache:60', cacheTags: ['shop.product'])]
    public function show(Product $product): View { /* … */ }

    #[Route('/products', methods: ['POST'], name: 'product.store',
            throttle: '10,1', request: StoreProductRequest::class)]
    public function store(): RedirectResponse { /* … */ }
}
```

### ۹٫۱ Discovery + Cache
```php
final class AttributeRouteCollector
{
    /** @return list<RouteDefinition> */
    public function collect(array $controllerClasses): array
    {
        $routes = [];
        foreach ($controllerClasses as $class) {
            $ref = new \ReflectionClass($class);
            $groupAttr = $this->firstAttribute($ref, RouteGroup::class);

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attr = $this->firstAttribute($method, Route::class);
                if ($attr === null) { continue; }
                $routes[] = $attr->toDefinition($class, $method, $groupAttr);
            }
        }
        \usort($routes, static fn ($a, $b) => $a->id <=> $b->id);   // 🔒 قطعی
        return $routes;
    }
}
```
🔒 باید کش شود (`storage/cache/attribute-routes.php`).

---

## ۱۰. پیکربندی

```php
// engines/router/config/router.php
return [
    'files' => [
        base_path('routes/web.php')     => ['middleware' => 'web'],
        base_path('routes/api.php')     => ['middleware' => 'api', 'prefix' => 'api'],
        base_path('routes/admin.php')   => ['middleware' => ['web','auth','admin'], 'prefix' => 'admin'],
        base_path('routes/console.php') => ['type' => 'console'],
        base_path('routes/channels.php')=> ['type' => 'channels'],
    ],
    'cache' => [
        'enabled' => env_bool('TVN_ROUTE_CACHE', false),
        'file'    => storage_path('cache/routes.php'),
    ],
    'matching' => [
        'trailing_slash'   => 'ignore',   // ignore|require|forbid
        'case_sensitive'   => false,
        'normalize'        => true,       // حذف duplicate slash
        'prefer_static'    => true,
    ],
    'model_binding' => [
        'soft_deletes'     => false,      // شامل deleted
        'enforce_policy'   => true,       // 🔒
        'scope_bindings'   => true,
    ],
    'attributes' => [
        'enabled' => true,
        'cache'   => storage_path('cache/attribute-routes.php'),
        'paths'   => [base_path('app/src/Http/Controllers'), base_path('modules')],
    ],
    'fallback' => [
        'enabled' => true,
        'view'    => 'errors.404',
    ],
    'url' => [
        'force_https' => env_bool('TVN_FORCE_HTTPS', false),
        'asset_url'   => env_string('TVN_ASSET_URL', null),
    ],
];
```

---

## ۱۱. امنیت

| تهدید | کنترل |
|---|---|
| Route Collision | شناسایی در `route:cache` + هشدار |
| Regex DoS (ReDoS) | محدودیت طول regex ≤ ۲۰۰ + رد `(a+)+` patterns |
| Parameter Injection | هر پارامتر با `where` محدود یا پیش‌فرض `[^/]+` |
| IDOR در Model Binding | `enforce_policy = true` |
| Open Redirect در `Route::redirect` | فقط مسیر نسبی یا allowlist |
| Unsigned Route Exposure | `signed()` برای لینک‌های حساس |
| Method Spoofing | `method_override` قابل غیرفعال‌سازی |

```php
private function assertSafeRegex(string $param, string $regex): void
{
    if (\strlen($regex) > 200) {
        throw new RouteCompilationException(messageId: 'router.regex.too_long', context: ['param' => $param]);
    }
    if (\preg_match('/\([^)]*[+*]\)[+*]/', $regex) === 1) {   // nested quantifier
        throw new RouteCompilationException(messageId: 'router.regex.potential_redos', context: ['regex' => $regex]);
    }
}
```

---

## ۱۲. کارایی

| عملیات | هدف |
|---|---|
| Match با ۱۰۰۰ روت (static) | ≤ ۵µs |
| Match با ۱۰۰۰ روت (parametric) | ≤ ۱۵µs |
| ساخت Trie از ۱۰۰۰ روت | ≤ ۱۵ms |
| بارگذاری Route Cache (۱۰۰۰ روت) | ≤ ۱٫۵ms |
| UrlGenerator با کش نام | ≤ ۳µs |
| حافظهٔ ۱۰۰۰ روت | ≤ ۲MB |

**مقایسهٔ هدف با مراجع:** سریع‌تر از FastRoute در حالت cached (به دلیل Trie به‌جای regex chunk).

---

## ۱۳. دستورات CLI
| دستور | توضیح |
|---|---|
| `tvn route:list [--method=] [--path=] [--name=] [--json]` | فهرست |
| `tvn route:cache` / `route:clear` | کش |
| `tvn route:match GET /shop/products/1` | دیباگ تطبیق |
| `tvn route:tree` | نمایش Trie |
| `tvn route:check` | تشخیص collision/regex خطرناک |

---

## ۱۴. تست
| ID | سناریو |
|---|---|
| T-01 | Static match با/بدون trailing slash |
| T-02 | Param match با constraint `\d+` → `/abc` = 404 |
| T-03 | اولویت static > param > wildcard |
| T-04 | Method mismatch → 405 با `Allow` header |
| T-05 | Group prefix + name + middleware merge |
| T-06 | Domain routing با `{tenant}.example.com` |
| T-07 | `apiResource` → ۵ روت درست |
| T-08 | Nested resource + shallow |
| T-09 | Model Binding با slug + Policy |
| T-10 | `scopeBindings` → order متعلق به user دیگر = 404 |
| T-11 | Attribute routing با group attribute |
| T-12 | Route cache roundtrip → نتیجه یکسان |
| T-13 | Collision detection در `route:check` |
| T-14 | ReDoS regex → رد |
| T-15 | UrlGenerator با پارامتر اضافه → query string |
| T-16 | Signed URL با expires منقضی → invalid |
| T-17 | Fallback route |
| T-18 | ۱۰۰۰ روت → match ≤ ۵µs (benchmark) |

---

## ۱۵. معیار پذیرش 🧪
- AC-RT-01..06 (کارایی §12).
- AC-RT-07: همهٔ تست‌های §14.
- AC-RT-08: `tvn route:list` خروجی خوانا با middleware و module.

---

## ۱۶. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| `nikic/FastRoute` | Regex chunking | Trie به‌جای regex ترکیبی → سریع‌تر و حافظه کمتر |
| Symfony Routing | Compiler، Attribute | کامپایل به Trie نه regex بزرگ |
| Laravel Routing | Fluent API، Resource | افزودن `idempotent()`, `condition()`, specificity scoring |
| Rails routing | `resources`, `shallow`, `scope` | — |

---

## ۱۷. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.router.v1
engine:
  id: tavaan.engine.router
  namespace: Tavaan\Engine\Http\Routing
  path: engines/router
  priority: P0
  phase: M2
  depends_on: [http, container, config]
  facade: {name: Route, accessor: router}
  tables: []
  events_emitted: [router.matched, router.not_found, router.method_not_allowed, router.cache.generated]
  config_file: engines/router/config/router.php
  public_api:
    - class: Router
      methods: [get, post, put, patch, delete, options, head, match, any, view, redirect,
                permanentRedirect, resource, apiResource, group, prefix, middleware, domain,
                fallback, model, bind, match, current, currentRouteName, has]
    - class: UrlGenerator
      methods: [route, action, url, asset, current, previous, full, signed,
                temporarySignedRoute, isValidSignature, to]
  loc_estimate: 1400
  acceptance:
    - {id: AC-RT-01, test: 'engines/router/benchmarks/match.bench.php'}
    - {id: AC-RT-07, test: 'engines/router/tests/Unit/RouteMatcherTest.php'}
```
