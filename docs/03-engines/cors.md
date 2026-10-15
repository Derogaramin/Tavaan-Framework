# DOC-046 — موتور CORS

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-046 |
| Engine ID | `tavaan.engine.cors` |
| Namespace | `Tavaan\Engine\Http\Cors` |
| اولویت | **P0** — فاز M2 |
| وابستگی | `http`, `config` |
| LOC | ۳۵۰ |

## ۱. هدف
مدیریت Cross-Origin Resource Sharing با پیکربندی per-route، Preflight (OPTIONS) و
Origin allowlist امن.

## ۲. API و Middleware
```php
final class CorsMiddleware implements MiddlewareInterface
{
    public function handle(RequestInterface $request, \Closure $next, string ...$p): ResponseInterface
    {
        // Preflight
        if ($request->method() === 'OPTIONS' && $request->header('Access-Control-Request-Method')) {
            return $this->handlePreflight($request);
        }

        $response = $next($request);
        return $this->addCorsHeaders($request, $response);
    }

    private function addCorsHeaders(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->header('Origin');
        if ($origin === null || !$this->isAllowed($origin)) { return $response; }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $this->reflectOrigin($origin))
            ->withHeader('Vary', 'Origin')                                  // 🔒 برای cache
            ->withHeader('Access-Control-Allow-Credentials', $this->config['supports_credentials'] ? 'true' : 'false')
            ->withHeader('Access-Control-Expose-Headers', \implode(', ', $this->config['exposed_headers']));

        return $response;
    }

    private function handlePreflight(RequestInterface $request): ResponseInterface
    {
        $origin = $request->header('Origin');
        $method = $request->header('Access-Control-Request-Method');
        $headers = $request->header('Access-Control-Request-Headers');

        if (!$this->isAllowed($origin) || !$this->isMethodAllowed($method)) {
            return new Response(status: 403);
        }

        return (new Response(status: 204))
            ->withHeader('Access-Control-Allow-Origin', $this->reflectOrigin($origin))
            ->withHeader('Access-Control-Allow-Methods', \implode(', ', $this->config['allowed_methods']))
            ->withHeader('Access-Control-Allow-Headers', $headers ?: \implode(', ', $this->config['allowed_headers']))
            ->withHeader('Access-Control-Max-Age', (string) $this->config['max_age'])
            ->withHeader('Vary', 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
    }
}
```

## ۳. Origin Matching
```php
private function isAllowed(string $origin): bool
{
    $allowed = $this->config['allowed_origins'];
    if (\in_array('*', $allowed, true)) { return true; }   // ⚠️ فقط با credentials=false

    foreach ($allowed as $pattern) {
        if ($origin === $pattern) { return true; }
        // wildcard: *.example.com
        if (\str_contains($pattern, '*')) {
            $regex = '#^' . \str_replace('\*', '[^.]+', \preg_quote($pattern, '#')) . '$#';
            if (\preg_match($regex, $origin) === 1) { return true; }
        }
        // pattern با scheme
        if (\str_starts_with($pattern, '*://')) {
            $host = \substr($pattern, \strlen('*://'));
            if (\str_ends_with($origin, '://' . $host)) { return true; }
        }
    }
    return false;
}
```
🔒 اگر `allowed_origins = ['*']` و `supports_credentials = true` → **خطای پیکربندی** (RFC نقض می‌شود).
🔒 هنگام `*` نباید origin را reflect کرد؛ ولی با credentials باید origin خاص reflect شود.

## ۴. پیکربندی
```php
// config/cors.php
return [
    'paths' => ['api/*', 'sanctum/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => env_list('TVN_CORS_ORIGINS'),       // پیش‌فرض خالی (امن)
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Tavaan-Ref', 'X-RateLimit-Remaining'],
    'max_age' => 3600,
    'supports_credentials' => false,                          // 🔒 پیش‌فرض false
];
```

## ۵. امنیت
| تهدید | کنترل |
|---|---|
| Origin باز (`*` + credentials) | رد در config validation |
| Cache Poisoning | `Vary: Origin` |
| Preflight bypass | بررسی method/header مجاز |
| Origin spoofing | تطبیق دقیق + allowlist |

## ۶. تست
| T-01 | Simple request با origin مجاز → headers |
| T-02 | origin غیرمجاز → بدون CORS header |
| T-03 | Preflight OPTIONS → 204 با headers |
| T-04 | wildcard `*.example.com` |
| T-05 | `*` + credentials → config error |
| T-06 | Vary: Origin حاضر |

## ۷. معیار پذیرش 🧪
- AC-CORS-01..06.

## ۸. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.cors.v1
engine:
  id: tavaan.engine.cors
  namespace: Tavaan\Engine\Http\Cors
  path: engines/cors
  priority: P0
  phase: M2
  depends_on: [http, config]
  config_file: config/cors.php
  public_api:
    - class: CorsMiddleware
      methods: [handle]
  loc_estimate: 350
```
