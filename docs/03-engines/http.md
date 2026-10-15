# DOC-039 — موتور HTTP | درخواست، پاسخ و کرنل وب

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-039 |
| Engine ID | `tavaan.engine.http` |
| Namespace | `Tavaan\Engine\Http` |
| اولویت | **P0** — فاز M2 |
| وابستگی | `pipeline`, `event`, `error`, `config`؛ نرم: `session`, `router`, `view` |
| Facade | `Http` → `http`، `Request`، `Response` |
| LOC | ۲۸۰۰ |
| مرتبط | DOC-025 (Lifecycle)، DOC-040 (Router) |

---

## ۱. هدف و دامنه

لایهٔ وب تَوان: **Request/Response Immutable**، **Kernel**، **Middlewareهای امنیتی**،
**Server Adapterها** (FPM / Built-in / Swoole / FrankenPHP) و **Response Factory**.

- ✅ Request/Response با Bagهای تفکیک‌شده و Immutable (`withX()`)
- ✅ PSR-7-like اما **بدون وابستگی به `psr/http-message`** (Interface داخلی)
- ✅ Kernel + Middleware Pipeline + Terminate
- ✅ Upload، Cookie، Header، Query، JSON، Route Parameters
- ✅ Response Factory (json/view/file/download/stream/redirect/no-content)
- ✅ HTTP Cache (ETag/Last-Modified/Conditional)
- ✅ Compression (gzip/deflate/br)
- ✅ Server Adapters با Interface واحد
- ❌ خارج: Routing (موتور جدا)، Session (موتور جدا)

---

## ۲. معماری

```
        ┌──────────────── Server Adapters ────────────────┐
        │ FpmServer │ BuiltInServer │ SwooleServer │ FRP  │
        └───────┬─────────────────────────────────────────┘
                │ Request::capture()  /  fromSwoole()
                ▼
        ┌───────────────┐
        │  HttpKernel   │──── Pipeline(Global MW) ────▶ Router ────▶ Pipeline(Route MW)
        └───────┬───────┘                                                    │
                │                                                            ▼
                │                                                    Controller/Action
                │                                                            │
                ◀────────────────── Response ◀───────────────────────────────┘
                │
        ┌───────▼────────┐
        │ ResponsePrep   │  ETag • Compression • Headers • Cookies
        └───────┬────────┘
                ▼
             send()  →  terminate()
```

### ۲٫۱ کلاس‌ها

| کلاس | مسئولیت | LOC |
|---|---|---|
| `Request` | Immutable request + bags | 480 |
| `Response` | Immutable response | 260 |
| `Uri` | پارس/ساخت URI (RFC 3986) | 200 |
| `Stream` | Stream روی `php://temp`/string/resource | 180 |
| `HeaderBag` | دسترسی case-insensitive به header | 160 |
| `ParameterBag` | query/post/attributes | 140 |
| `ServerBag` | `$_SERVER` نرمال‌شده | 120 |
| `FileBag` | آپلودها | 130 |
| `CookieBag` | کوکی‌های درخواست | 70 |
| `UploadedFile` | فایل آپلود + validation | 200 |
| `HttpKernel` | orchestrator | 300 |
| `ResponseFactory` | سازندهٔ پاسخ‌ها | 220 |
| `Redirector` | redirect + `intended()` | 130 |
| `ResponsePreparer` | ETag/Compression/Headers | 180 |
| `Middleware/*` | ۱۴ middleware | 700 |
| `Server/*` | ۴ adapter | 450 |
| `HttpServiceProvider` | ثبت | 90 |

---

## ۳. Request — API کامل

### ۳٫۱ ساخت

```php
$request = Request::capture();                    // از superglobals
$request = Request::create('/shop/products?page=2', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$request = Server\FpmServer::captureRequest();
$request = Server\SwooleServer::fromSwooleRequest($swRequest);
```

### ۳٫۲ متدها

| گروه | متد | امضا |
|---|---|---|
| متد | `method()` | `: string` (uppercase) |
| | `realMethod()` | بدون override |
| | `isMethod(string)` | `: bool` |
| | `withMethod(string)` | `: static` |
| URL | `uri()` | `: Uri` |
| | `path()` | `: string` (`/shop/products`) |
| | `fullUrl()` / `url()` | با/بدون query |
| | `fullUrlWithQuery(array)` | `: string` |
| | `isSecure()` | `: bool` |
| | `scheme()` / `host()` / `port()` | |
| | `baseUrl()` | مسیر پایه (اگر در subfolder) |
| | `is(string ...$patterns)` | تطبیق با wildcard |
| | `routeIs(string ...$names)` | |
| Header | `header(string $k, ?string $d = null)` | `: ?string` |
| | `headers()` | `: array<string, list<string>>` |
| | `hasHeader(string)` | |
| | `withHeader(string, string)` | `: static` |
| | `userAgent()` / `accept()` / `acceptLanguage()` / `contentType()` / `referer()` | |
| | `bearerToken()` | `: ?string` |
| | `expectsJson()` / `wantsJson()` | `: bool` |
| ورودی | `input(string $k, mixed $d = null)` | query + post + json |
| | `query(?string $k = null, mixed $d = null)` | |
| | `post(?string $k = null, mixed $d = null)` | |
| | `json(?string $k = null, mixed $d = null)` | |
| | `all()` | `: array` (merged) |
| | `only(string ...$keys)` / `except(...)` | |
| | `filled(string $k)` / `missing(string $k)` | |
| | `has(string $k)` / `hasAny(array)` | |
| | `boolean/int/float/string/enum/array/date($k)` | cast helper |
| | `collect(string $k)` | `: Collection` |
| | `old(string $k, mixed $d = null)` | از Session flash |
| | `withInput(array)` | `: static` |
| فایل | `file(string $k)` | `: ?UploadedFile` |
| | `files()` / `allFiles()` | |
| | `hasFile(string $k)` | |
| Cookie | `cookie(string $k, mixed $d = null)` | |
| Attribute | `attribute(string $k, mixed $d = null)` | |
| | `attributes()` | route params + middleware-set |
| | `withAttribute(string, mixed)` | `: static` |
| مسیر | `routeParameter(string $k)` | |
| | `routeParameters()` | |
| کلاینت | `ip()` / `ips()` | با trusted proxy |
| | `user()` / `id()` | از Auth (اگر موجود) |
| | `session()` | |
| زمان | `requestTime()` / `microtime()` | |
| | `elapsedMs()` | از `TVN_START` |
| Debug | `dd()` / `dump()` | فقط dev |

### ۳٫۳ Immutable Pattern
```php
public function withAttribute(string $key, mixed $value): static
{
    $clone = clone $this;
    $clone->attributes->set($key, $value);
    return $clone;
}

public function __clone(): void
{
    $this->headers   = clone $this->headers;
    $this->query     = clone $this->query;
    $this->request   = clone $this->request;
    $this->attributes= clone $this->attributes;
    $this->cookies   = clone $this->cookies;
    $this->files     = clone $this->files;
    $this->server    = clone $this->server;
}
```
🔒 Bagها باید `clone` شوند تا تغییر در clone روی اصلی اثر نگذارد.

### ۳٫۴ ورودی JSON (امن)
```php
private function parseJsonBody(): array
{
    if ($this->parsedJson !== null) { return $this->parsedJson; }

    $raw = (string) $this->body();
    if ($raw === '') { return $this->parsedJson = []; }

    if (\strlen($raw) > $this->maxJsonSize) {
        throw new PayloadTooLargeException(messageId: 'http.json.too_large',
            context: ['size' => \strlen($raw), 'max' => $this->maxJsonSize]);
    }

    try {
        $decoded = \json_decode($raw, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    } catch (\JsonException $e) {
        throw new BadRequestException(messageId: 'http.json.invalid',
            context: ['error' => $e->getMessage()], hint: 'بدنهٔ درخواست JSON معتبر نیست.');
    }

    $this->assertDepth($decoded, maxDepth: 8);
    return $this->parsedJson = \is_array($decoded) ? $decoded : ['_value' => $decoded];
}
```

---

## ۴. Response — API کامل

```php
$response = new Response(body: 'OK', status: 200, headers: ['X-Foo' => 'bar']);

$response->statusCode();                 // 200
$response->withStatus(404, 'Not Found');
$response->header('Content-Type', 'application/json');
$response->withHeaders(['Cache-Control' => 'no-store']);
$response->withoutHeader('X-Powered-By');
$response->body();
$response->withBody('new');
$response->withJson(['ok' => true], 200, JSON_UNESCAPED_UNICODE);
$response->cookie(new Cookie('theme', 'dark'));
$response->withCookie(...)
$response->isSuccessful();               // 2xx
$response->isRedirect();                 // 3xx
$response->isClientError() / isServerError();
$response->send();                       // never
$response->withProtocolVersion('1.1');
```

### ۴٫۱ ResponseFactory
```php
response()->json(['ok' => true], 200);
response()->view('shop::product.show', ['product' => $p], 200);
response()->file($path, ['Content-Type' => 'application/pdf']);
response()->download($path, 'invoice-1001.pdf', disposition: 'attachment');
response()->stream($callback, 200, ['Content-Type' => 'text/csv']);
response()->streamDownload(fn() => /* … */, 'export.csv');
response()->noContent();
response()->make('OK', 200);
response()->redirectToRoute('shop.product.show', ['product' => $id]);
response()->redirectToRoute(...)->withInput()->withErrors($errors);
response()->problem('validation.failed', 422, errors: [...]);   // RFC 9457
```

### ۴٫۲ ارسال (FPM)
```php
public function send(): never
{
    if (!\headers_sent($file, $line)) {
        \http_response_code($this->status);
        foreach ($this->headers->allPreserveCase() as $name => $values) {
            $replace = true;
            foreach ($values as $value) {
                \header($name . ': ' . $value, $replace, $this->status);
                $replace = false;
            }
        }
        foreach ($this->cookies->queued() as $cookie) {
            \setcookie($cookie->name, $cookie->value, $cookie->toArray());  // PHP 7.3+ array form
        }
    }

    $body = $this->bodyStream();
    if ($body->isSeekable()) { $body->rewind(); }
    while (!$body->eof()) {
        echo $body->read(8192);
        if (\connection_status() !== CONNECTION_NORMAL) { break; }
    }

    if (\function_exists('fastcgi_finish_request')) { \fastcgi_finish_request(); }
    // ⚠️ exit() در اینجا فراخوانی نمی‌شود تا terminate() اجرا شود
    return $this;    // ⚠️ امضای واقعی: void
}
```
🔒 برای `send(): never` باید `exit` در Kernel باشد، نه Response (تا terminate اجرا شود).
راه‌حل تَوان: `Kernel::sendAndTerminate()` → `send()` (بدون exit) → `terminate()` → `exit`.

---

## ۵. Middlewareهای داخلی

| Middleware | مرحله | وظیفه |
|---|---|---|
| `TrustProxiesMiddleware` | قبل از همه | تنظیم `X-Forwarded-*` با allowlist |
| `MaintenanceModeMiddleware` | اوایل | ۵۰۳ اگر `storage/framework/down` |
| `SecurityHeadersMiddleware` | اوایل | CSP/HSTS/… |
| `CorsMiddleware` | اوایل | preflight + headers |
| `RequestSizeLimitMiddleware` | اوایل | ۴۱۳ برای body بزرگ |
| `StartSessionMiddleware` | میانی | شروع/پایان Session |
| `ShareErrorsFromSessionMiddleware` | میانی | flash errors → view |
| `CsrfVerifyMiddleware` | میانی | ۴۱۹ |
| `AuthenticationMiddleware` | میانی | ۴۰۱/redirect |
| `RateLimitMiddleware` | میانی | ۴۲۹ |
| `SetLocaleMiddleware` | میانی | از session/user/header |
| `TraceContextMiddleware` | میانی | `traceparent` |
| `ResponseCacheMiddleware` | میانی | ETag/304 |
| `CompressionMiddleware` | پایانی | gzip/deflate/br |

### ۵٫۱ TrustProxiesMiddleware
```php
final class TrustProxiesMiddleware implements MiddlewareInterface
{
    /** @param list<string> $trusted (CIDR یا `*`) */
    public function __construct(private readonly array $trusted, private readonly array $trustedHeaders) {}

    public function handle(RequestInterface $request, \Closure $next, string ...$p): ResponseInterface
    {
        if ($this->trusted === [] || !($request instanceof Request)) {
            return $next($request);
        }

        $remoteIp = $request->server()->get('REMOTE_ADDR');
        if (!$this->isTrusted($remoteIp)) {
            return $next($request);
        }

        // 🔒 فقط از پروکسی مورد اعتماد، headerهای forward را بپذیر
        $cloned = $request;
        if (\in_array('X-Forwarded-For', $this->trustedHeaders, true)) {
            $chain = \array_map('trim', \explode(',', (string) $request->header('X-Forwarded-For')));
            $realIp = $this->firstUntrusted(\array_reverse($chain));
            if ($realIp !== null) { $cloned = $cloned->withClientIp($realIp); }
        }
        if (\in_array('X-Forwarded-Proto', $this->trustedHeaders, true)
            && \strtolower((string) $request->header('X-Forwarded-Proto')) === 'https') {
            $cloned = $cloned->withSecure(true);
        }
        if (\in_array('X-Forwarded-Host', $this->trustedHeaders, true)) {
            $cloned = $cloned->withHost((string) $request->header('X-Forwarded-Host'));
        }

        return $next($cloned);
    }

    private function isTrusted(string $ip): bool
    {
        if (\in_array('*', $this->trusted, true)) { return true; }
        foreach ($this->trusted as $cidr) {
            if (IpMatcher::matches($ip, $cidr)) { return true; }
        }
        return false;
    }
}
```
🔒 **پیش‌فرض: `trusted_proxies = []`** — یعنی هیچ header forward پذیرفته نمی‌شود (امن).
🔒 `IpMatcher` باید IPv4 و IPv6 را پشتیبانی کند (پیاده‌سازی خالص با `inet_pton` + bitmask).

### ۵٫۲ SecurityHeadersMiddleware
```php
public function handle(RequestInterface $r, \Closure $next, string ...$p): ResponseInterface
{
    $response = $next($r);
    foreach ($this->headers() as $name => $value) {
        if (!$response->hasHeader($name) || $this->override) {
            $response = $response->withHeader($name, $value);
        }
    }
    return $response->withoutHeader('X-Powered-By');
}
```

---

## ۶. HttpKernel

```php
final class HttpKernel implements KernelInterface
{
    public function handle(Request $request): Response
    {
        $this->requestStack->push($request);

        try {
            $this->bootstrapForRequest($request);

            $response = $this->pipeline
                ->send($request)
                ->through($this->globalMiddleware())
                ->then(fn (Request $r): Response => $this->dispatchToRouter($r));

            return $this->prepareResponse($response, $request);
        } catch (\Throwable $e) {
            return $this->renderException($e, $request);
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            foreach ($this->resolvedMiddleware as $mw) {
                if ($mw instanceof TerminableMiddleware) { $mw->terminate($request, $response); }
            }
            $this->events->dispatch(new RequestTerminated($request, $response));
        } finally {
            $this->requestStack->pop();
            $this->container->flushScoped();       // 🔒 Resident safety
            $this->clock->reset();
        }
    }

    private function dispatchToRouter(Request $request): Response
    {
        $route = $this->router->match($request->method(), $request->path(), $request->host());
        $request = $request->withRoute($route);

        return $this->pipeline
            ->send($request)
            ->through($route->gatherMiddleware())
            ->then(fn (Request $r): mixed => $this->controller->dispatch($r, $route));
    }
}
```

---

## ۷. Server Adapters

| Adapter | فایل | توضیح |
|---|---|---|
| `FpmServer` | `Server/FpmServer.php` | پیش‌فرض؛ از superglobals |
| `BuiltInServer` | `Server/BuiltInServer.php` | `php -S` با router script |
| `SwooleServer` | `Server/SwooleServer.php` | Resident + Coroutine |
| `FrankenPhpServer` | `Server/FrankenPhpServer.php` | Worker mode |

```php
interface ServerAdapterInterface
{
    public function run(): never;
    public function stop(int $code = 0): never;
    public function name(): string;
    public function isResident(): bool;
}
```

### ۷٫۱ Swoole — نکات حیاتی
```php
final class SwooleServer implements ServerAdapterInterface
{
    public function run(): never
    {
        $server = new \Swoole\Http\Server($this->host, $this->port, $this->mode, SOCK_STREAM);
        $server->set($this->settings());

        $server->on('start',   fn () => $this->events->dispatch(new ServerStarted('swoole')));
        $server->on('workerStart', fn ($srv, $workerId) => $this->onWorkerStart($srv, $workerId));
        $server->on('request', $this->handleRequest(...));
        $server->on('shutdown', fn () => $this->shutdown());

        $server->start();
        exit(0);
    }

    private function handleRequest(\Swoole\Http\Request $swReq, \Swoole\Http\Response $swRes): void
    {
        $this->container->beginScope('request');
        try {
            $request  = Request::fromSwoole($swReq);
            $response = $this->kernel->handle($request);
            $this->sendToSwoole($response, $swRes);
            $this->kernel->terminate($request, $response);
        } catch (\Throwable $e) {
            $this->logger->critical('swoole.request_failed', ['error' => $e->getMessage()]);
            $swRes->status(500);
            $swRes->end('Internal Server Error');
        } finally {
            $this->container->endScope('request');
            $this->recycleIfNeeded();
        }
    }

    private function settings(): array
    {
        return [
            'worker_num'          => $this->workers,               // پیش‌فرض: CPU cores
            'max_request'         => 3000,                         // 🔒 recycle برای جلوگیری از leak
            'max_request_grace'   => 500,
            'enable_coroutine'    => true,
            'open_http2_protocol' => true,
            'package_max_length'  => 8 * 1024 * 1024,
            'buffer_output_size'  => 2 * 1024 * 1024,
            'log_level'           => SWOOLE_LOG_WARNING,
            'daemonize'           => $this->daemonize,
        ];
    }
}
```

---

## ۸. HTTP Cache و Compression

### ۸٫۱ ETag / Last-Modified
```php
final class ResponsePreparer
{
    public function prepare(Response $response, Request $request): Response
    {
        if ($response->isRedirect() || $response->statusCode() === 204) { return $response; }

        // ETag
        if ($this->config->get('http.etag.enabled', true) && $response->hasBody() && !$response->hasHeader('ETag')) {
            $etag = '"' . \hash('xxh128', $response->body()) . '"';
            $response = $response->withHeader('ETag', $etag);
        }

        // Conditional
        if ($this->isFresh($request, $response)) {
            return $response->withStatus(304)->withBody('')->withoutHeader('Content-Length');
        }

        // Compression
        if ($this->shouldCompress($response, $request)) {
            $response = $this->compress($response, $request);
        }

        // Content-Length
        if (!$response->hasHeader('Content-Length') && !$response->isStreaming()) {
            $response = $response->withHeader('Content-Length', (string) \strlen($response->body()));
        }

        return $response;
    }

    private function isFresh(Request $req, Response $res): bool
    {
        $ifNoneMatch = $req->header('If-None-Match');
        if ($ifNoneMatch !== null && $res->hasHeader('ETag')) {
            $etags = \array_map('trim', \explode(',', $ifNoneMatch));
            if (\in_array($res->header('ETag'), $etags, true) || \in_array('*', $etags, true)) {
                return \in_array($req->method(), ['GET', 'HEAD'], true);
            }
        }
        $ifModified = $req->header('If-Modified-Since');
        if ($ifModified !== null && $res->hasHeader('Last-Modified')) {
            return \strtotime($res->header('Last-Modified')) <= \strtotime($ifModified);
        }
        return false;
    }

    private function shouldCompress(Response $res, Request $req): bool
    {
        $size = \strlen($res->body());
        if ($size < $this->minSize) { return false; }               // پیش‌فرض 1024
        if ($res->hasHeader('Content-Encoding')) { return false; }
        $type = (string) $res->header('Content-Type');
        if (!\in_array(self::compressibleType($type), self::COMPRESSIBLE, true)) { return false; }
        return $this->acceptedEncoding($req) !== null;
    }

    private const array COMPRESSIBLE = ['text/html','text/plain','text/css','text/javascript',
        'application/javascript','application/json','application/xml','text/xml','image/svg+xml',
        'application/rss+xml','application/manifest+json'];
}
```

### ۸٫۲ Compression
```php
private function compress(Response $res, Request $req): Response
{
    $enc = $this->acceptedEncoding($req);      // br > gzip > deflate
    $body = $res->body();

    $compressed = match ($enc) {
        'br'      => \function_exists('brotli_compress') ? \brotli_compress($body, 4) : false,
        'gzip'    => \function_exists('gzencode') ? \gzencode($body, 5) : false,
        'deflate' => \function_exists('gzdeflate') ? \gzdeflate($body, 5) : false,
        default   => false,
    };

    if ($compressed === false || \strlen($compressed) >= \strlen($body)) { return $res; }

    return $res->withBody($compressed)
        ->withHeader('Content-Encoding', (string) $enc)
        ->withHeader('Vary', 'Accept-Encoding')
        ->withHeader('Content-Length', (string) \strlen($compressed));
}
```
⚠️ `brotli` یک ext خارجی است → فقط در صورت موجود بودن؛ در غیر این صورت gzip.

---

## ۹. پیکربندی

```php
// engines/http/config/http.php
return [
    'driver'          => env_string('TVN_HTTP_DRIVER', 'fpm'),   // fpm|builtin|swoole|frankenphp
    'host'            => env_string('TVN_HTTP_HOST', '127.0.0.1'),
    'port'            => env_int('TVN_HTTP_PORT', 8080),
    'workers'         => env_int('TVN_HTTP_WORKERS', 0),          // 0 = auto (CPU)
    'max_request'     => 3000,

    'trusted_proxies' => env_list('TVN_TRUSTED_PROXIES'),         // پیش‌فرض: خالی (امن)
    'trusted_headers' => ['X-Forwarded-For','X-Forwarded-Host','X-Forwarded-Proto','X-Forwarded-Port'],

    'max_body_size'   => 8 * 1024 * 1024,
    'max_json_depth'  => 32,
    'max_json_size'   => 4 * 1024 * 1024,
    'max_upload_size' => 16 * 1024 * 1024,
    'max_input_vars'  => 1000,

    'method_override' => true,      // _method / X-HTTP-Method-Override
    'normalize_path'  => true,      // حذف duplicate slash، trailing slash

    'etag'          => ['enabled' => true, 'algorithm' => 'xxh128'],
    'compression'   => ['enabled' => true, 'min_size' => 1024, 'level' => 5, 'algorithms' => ['gzip','deflate']],
    'cache_control' => ['default' => 'no-cache, private', 'static_ttl' => 31536000],

    'middleware' => [
        'global' => [
            \Tavaan\Engine\Http\Middleware\TrustProxiesMiddleware::class,
            \Tavaan\Engine\Http\Middleware\MaintenanceModeMiddleware::class,
            \Tavaan\Engine\Http\Middleware\SecurityHeadersMiddleware::class,
            \Tavaan\Engine\Http\Middleware\CorsMiddleware::class,
            \Tavaan\Engine\Http\Middleware\RequestSizeLimitMiddleware::class,
            \Tavaan\Engine\Session\Middleware\StartSessionMiddleware::class,
            \Tavaan\Engine\Http\Middleware\ShareErrorsFromSessionMiddleware::class,
            \Tavaan\Engine\Security\Middleware\CsrfVerifyMiddleware::class,
            \Tavaan\Engine\Http\Middleware\SetLocaleMiddleware::class,
            \Tavaan\Engine\Observability\Middleware\TraceContextMiddleware::class,
        ],
        'groups' => [
            'web' => [/* … */],
            'api' => [\Tavaan\Engine\Security\Middleware\RateLimitMiddleware::class . ':api'],
        ],
    ],

    'swoole' => [
        'enable_coroutine' => true,
        'open_http2'       => true,
        'settings'         => [],
    ],
];
```

---

## ۱۰. امنیت

| تهدید | کنترل |
|---|---|
| Header Injection | رد `\r\n` در نام/مقدار header |
| HTTP Request Smuggling | رد body با `Content-Length` و `Transfer-Encoding` همزمان |
| Host Header Attack | `trusted_hosts` allowlist + استفاده از `app.url` برای URL generation |
| Path Traversal در `file()`/`download()` | realpath + sandbox |
| Open Redirect | `Redirector` فقط به allowlist یا مسیر نسبی |
| DoS با body بزرگ | `max_body_size` + `max_json_depth` |
| IP Spoofing | `trusted_proxies` خالی به‌صورت پیش‌فرض |
| Cookie Theft | `HttpOnly` پیش‌فرض |
| CRLF در `Location` | sanitize |
| Information Leak | حذف `X-Powered-By`، `Server` |

```php
private function assertSafeHeaderValue(string $value): void
{
    if (\preg_match('/[\r\n\0]/', $value) === 1) {
        throw new HeaderInjectionException(messageId: 'http.header.injection');
    }
}

private function detectSmuggling(Request $r): void
{
    if ($r->hasHeader('Content-Length') && $r->hasHeader('Transfer-Encoding')) {
        throw new BadRequestException(messageId: 'http.request.smuggling');
    }
}
```

---

## ۱۱. کارایی

| عملیات | هدف |
|---|---|
| `Request::capture()` | ≤ ۰٫۲ms |
| Pipeline ۱۲ middleware تهی | ≤ ۰٫۰۲ms |
| `Response::send()` برای ۱۰KB | ≤ ۰٫۱ms |
| ETag hash (xxh128) برای ۱۰۰KB | ≤ ۰٫۰۵ms |
| gzip ۱۰۰KB level 5 | ≤ ۱ms |
| سربار کل Hello World (نسبت به PHP خام) | ≤ ۳ms |
| Swoole RPS (1 vCPU) | ≥ ۳۰۰۰ |
| حافظهٔ Request+Response | ≤ ۱MB |

---

## ۱۲. تست

| ID | سناریو |
|---|---|
| T-01 | `Request::create()` با هر ۹ متد |
| T-02 | Method override با `_method` و header |
| T-03 | پارس JSON با عمق ۳۳ → خطا |
| T-04 | `input()` اولویت: query < post < json (قابل تنظیم) |
| T-05 | Immutable: `withAttribute` اصل را تغییر نمی‌دهد |
| T-06 | `TrustProxies` با CIDR v4 و v6 |
| T-07 | بدون trusted proxy → `X-Forwarded-For` نادیده |
| T-08 | ETag + `If-None-Match` → 304 |
| T-09 | `If-Modified-Since` → 304 |
| T-10 | Compression فقط برای content-type فشرده‌شدنی و ≥ min_size |
| T-11 | Header injection با `\r\n` → خطا |
| T-12 | `Content-Length` + `Transfer-Encoding` → 400 |
| T-13 | Upload با `UploadedFile` → `move()` به مسیر sandbox |
| T-14 | `streamDownload` → chunked بدون مصرف حافظه |
| T-15 | Terminate حتی با Exception اجرا می‌شود |
| T-16 | Swoole adapter (با stub) → scope flush پس از هر درخواست |
| T-17 | Response factory: همهٔ ۱۲ نوع |
| T-18 | `Redirector::intended()` با session |

---

## ۱۳. معیار پذیرش 🧪
- AC-HTTP-01: همهٔ تست‌های §12 سبز.
- AC-HTTP-02: سربار Hello World ≤ ۳ms.
- AC-HTTP-03: در Swoole پس از ۱۰۰۰۰ درخواست، نشت حافظه ≤ ۵٪.
- AC-HTTP-04: همهٔ Headerهای امنیتی در پاسخ پیش‌فرض حاضرند.
- AC-HTTP-05: ۰ مورد Header Injection / Smuggling در تست‌های امنیتی.
- AC-HTTP-06: `tvn serve` با Built-in server کار می‌کند.

---

## ۱۴. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| Symfony HttpFoundation | Request/Response Bagها | Immutable کامل، بدون `psr/*` |
| PSR-7/15 | مفاهیم Stream/Middleware | Interface داخلی، API ساده‌تر |
| Laravel Http | Kernel/Factory | افزودن `ResponsePreparer` یکپارچه، Adapterهای resident |
| Nyholm PSR-7 | Immutable | بدون وابستگی |
| Swoole Docs | تنظیمات server | — |

---

## ۱۵. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.http.v1
engine:
  id: tavaan.engine.http
  namespace: Tavaan\Engine\Http
  path: engines/http
  priority: P0
  phase: M2
  depends_on: [pipeline, event, error, config]
  soft_depends_on: [session, router, view, security, observability]
  extensions: {required: [], optional: [{name: zlib, fallback: none}, {name: swoole, fallback: fpm}]}
  facade: [{name: Http, accessor: http}, {name: Request, accessor: request}, {name: Response, accessor: response}]
  tables: []
  events_emitted: [http.request.received, http.route.matched, http.request.handled,
                   http.response.sent, http.request.terminated]
  config_file: engines/http/config/http.php
  public_api:
    - class: HttpKernel
      methods: [handle, terminate, sendAndTerminate, handleSubRequest]
    - class: Request
      methods: [capture, create, method, path, fullUrl, header, input, query, post,
                json, all, only, except, file, files, cookie, attribute, ip, isSecure,
                expectsJson, withAttribute, withMethod, withQuery, withRoute]
    - class: Response
      methods: [statusCode, withStatus, header, withHeader, withHeaders, body, withBody,
                withJson, cookie, send, isSuccessful, isRedirect]
    - class: ResponseFactory
      methods: [json, view, file, download, stream, streamDownload, noContent, make,
                redirectToRoute, redirectToUrl, problem]
  loc_estimate: 2800
  acceptance:
    - {id: AC-HTTP-02, test: 'engines/http/benchmarks/http.bench.php'}
    - {id: AC-HTTP-04, test: 'engines/http/tests/Security/HeaderAuditTest.php'}
```
