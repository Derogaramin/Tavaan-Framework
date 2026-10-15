# DOC-044 — موتور API | Resource، Versioning و OpenAPI

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-044 |
| Engine ID | `tavaan.engine.api` |
| Namespace | `Tavaan\Engine\Api` |
| اولویت | **P1** — فاز M6 |
| وابستگی | `http`, `validation`, `orm` |
| Facade | ندارد (Resource base class) |
| LOC | ۱۲۰۰ |

## ۱. هدف
لایهٔ API: **Resource/Transformer** برای شکل‌دهی خروجی JSON، **Versioning**،
**OpenAPI 3.1 Generator**، Rate Limit و Idempotency.

## ۲. API Resource
```php
final class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->resource->id,
            'title' => $this->resource->title,
            'slug'  => $this->resource->slug,
            'price' => [
                'amount'   => $this->resource->price->amount(),
                'currency' => $this->resource->price->currency()->value,
                'formatted'=> money($this->resource->price)->format(),
            ],
            'in_stock' => $this->when($request->user()?->can('shop.stock.view'), fn () => $this->resource->stock > 0),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'links' => [
                'self' => route('api.v1.product.show', $this->resource),
            ],
        ];
    }
}

// استفاده
return ProductResource::make($product);
return ProductResource::collection($products);
return ProductResource::collection($products->paginate(20));   // با meta/links
```

### ۲٫۱ متدهای شرطی
`when(condition, value)`, `whenLoaded(relation)`, `whenHas(relation)`,
`whenCounted(relation)`, `mergeWhen(condition, array)`, `whenPivotLoaded(...)`.

## ۳. Versioning
```php
// URL-based
Route::prefix('api/v1')->group(base_path('routes/api/v1.php'));
Route::prefix('api/v2')->group(base_path('routes/api/v2.php'));

// Header-based
Route::middleware('api.version:2')->group(...);   // Accept: application/vnd.tavaan.v2+json
```
```php
// config/api.php
'versioning' => [
    'strategy' => 'url',           // url|header|query
    'default' => 'v1',
    'header' => 'Accept',
    'prefix' => 'application/vnd.tavaan.',
    'supported' => ['v1','v2'],
    'deprecated' => ['v1'],        // → Deprecation header
],
```
🔒 روت‌های deprecated → header `Deprecation` + `Sunset`.

## ۴. OpenAPI Generator (از Route+Validator+Resource)
```bash
tvn api:docs --version=v1 --format=yaml --output=docs/openapi.yaml
```
```php
final class OpenApiGenerator
{
    public function generate(string $version): array
    {
        $spec = ['openapi'=>'3.1.0', 'info'=>[...], 'paths'=>[]];
        foreach ($this->router->routesForVersion($version) as $route) {
            $path = $this->convertPath($route->uri());      // {product} → همان
            foreach ($route->methods() as $method) {
                $spec['paths'][$path][\strtolower($method)] = [
                    'operationId' => $route->name(),
                    'tags' => [$route->module()],
                    'parameters' => $this->parameters($route),
                    'requestBody' => $this->requestBody($route),   // از FormRequest rules
                    'responses' => $this->responses($route),       // از Resource + #[ApiResponse]
                    'security' => $this->security($route),
                ];
            }
        }
        return $spec;
    }
}
```

### ۴٫۱ Attribute-based Metadata
```php
#[Api(tags: ['products'], summary: 'فهرست محصولات')]
#[ApiResponse(status: 200, description: 'موفق', resource: ProductResource::class)]
#[ApiResponse(status: 422, description: 'خطای اعتبارسنجی')]
public function index(): AnonymousResourceCollection { /* … */ }
```

## ۵. Idempotency (برای POSTهای بین‌سرویسی)
```php
// middleware 'idempotent'
final class IdempotencyMiddleware implements MiddlewareInterface
{
    public function handle(RequestInterface $r, \Closure $next, string ...$p): ResponseInterface
    {
        $key = $r->header('Idempotency-Key');
        if ($key === null) { return $next($r); }

        $cacheKey = 'idempotency:' . $r->path() . ':' . $key;
        if (($cached = $this->cache->get($cacheKey)) !== null) {
            return Response::fromJson($cached);        // 🔒 پاسخ قبلی
        }

        $response = $next($r);
        $this->cache->put($cacheKey, $response->toArray(), ttl: 86400);
        return $response;
    }
}
```

## ۶. Rate Limit (per API key/user/IP)
```php
Route::middleware('throttle:api')->group(...);
// throttle:api → config rate_limit.api = ['max'=>60,'decay'=>60]
```
Headerهای پاسخ: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`, `Retry-After` (در 429).

## ۷. پاسخ خطای API (RFC 9457)
→ مطابق DOC-013 §6.1.

## ۸. پیکربندی
```php
// config/api.php
return [
    'prefix' => 'api',
    'middleware' => ['api'],
    'pagination' => ['per_page'=>20, 'max_per_page'=>100],
    'date_format' => \DateTimeInterface::ATOM,
    'preserve_keys' => false,
    'versioning' => [/* بالا */],
    'openapi' => ['title'=>config('app.name'), 'version'=>'1.0.0', 'servers'=>[]],
];
```

## ۹. امنیت
| تهدید | کنترل |
|---|---|
| Over-fetching | Resource با `when()` برای فیلدهای حساس |
| Mass Data | pagination + max_per_page |
| Version leak | فقط supported versions |
| Rate | throttle |
| نشت error | RFC 9457 بدون trace در prod |
| Replay در write | Idempotency-Key |

## ۱۰. تست
| T-01 | Resource با nested + whenLoaded |
| T-02 | Collection با pagination meta |
| T-03 | Versioning url/header |
| T-04 | OpenAPI generation از routes |
| T-05 | Idempotency: دو POST با یک key → یک اثر |
| T-06 | Rate limit → 429 + headers |
| T-07 | `when()` فیلد مخفی برای guest |

## ۱۱. معیار پذیرش 🧪
- AC-API-01..07. AC-API-08: OpenAPI خروجی با `swagger-cli validate` معتبر است.

## ۱۲. مراجع
| منبع | تفاوت |
|---|---|
| Laravel API Resources | `when/whenLoaded` | OpenAPI generator داخلی |
| JSON:API | ساختار | ساده‌تر |
| OpenAPI 3.1 | spec | generator خودنوشته |

## ۱۳. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.api.v1
engine:
  id: tavaan.engine.api
  namespace: Tavaan\Engine\Api
  path: engines/api
  priority: P1
  phase: M6
  depends_on: [http, validation, orm]
  facade: null
  config_file: config/api.php
  public_api:
    - class: JsonResource
      methods: [make, collection, toArray, when, whenLoaded, whenCounted, mergeWhen, response]
    - class: OpenApiGenerator
      methods: [generate, toYaml, toJson]
  loc_estimate: 1200
```
