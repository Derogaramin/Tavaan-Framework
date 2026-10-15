# DOC-035 — موتور Error | مدیریت خطا

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-035 |
| Engine ID | `tavaan.engine.error` |
| Namespace | `Tavaan\Engine\Error` |
| اولویت | **P0** — فاز M1 |
| وابستگی | `config`, `log`, `event` |
| Facade | ندارد (Kernel-level) |
| LOC | ۸۰۰ |
| مرتبط | DOC-013 (استاندارد خطا) |

---

## ۱. هدف

مدیریت یکپارچهٔ همهٔ خطاهای PHP و استثناها: **ثبت → نگاشت → رندر → گزارش**،
با رفتار متفاوت در `development` / `production` / `testing` / `ci`.

- ✅ تبدیل `E_*` به `ErrorException`
- ✅ تبدیل `E_DEPRECATED` به Failure در CI (الزام NFR-QUA-01)
- ✅ExceptionHandler با Renderable/Reportable/Contextual
- ✅ Mapper استثنا → HTTP Status
- ✅ رندر JSON (RFC 9457) و HTML (صفحهٔ خطا)
- ✅ صفحهٔ خطای غنی در DEV (trace + snippet + request dump)
- ✅ `refCode` برای ردیابی پشتیبانی
- ✅ Masking دادهٔ حساس
- ✅ Shutdown Handler برای خطاهای Fatal

---

## ۲. معماری

```
  PHP Error / Exception
        │
        ▼
┌──────────────────┐     ┌──────────────────────┐
│  ErrorHandler    │────▶│  ExceptionHandler     │
│ (set_error_…)    │     │                       │
│ (set_exception_…)│     │  1. shouldReport?     │
│ (shutdown)       │     │  2. report → Logger   │
└──────────────────┘     │  3. prepareException  │
                         │  4. render            │
                         └──────┬───────────────┘
                                │
        ┌───────────────────────┼────────────────────────┐
        ▼                       ▼                        ▼
 ┌──────────────┐      ┌────────────────┐       ┌────────────────┐
 │RenderableIntf│      │ExceptionMapper │       │Renderer chain  │
 │ (خود استثنا)  │      │ →HttpException │       │ Json│Html│Cli  │
 └──────────────┘      └────────────────┘       └────────────────┘
```

### ۲٫۱ کلاس‌ها

| کلاس | مسئولیت |
|---|---|
| `EarlyErrorManager` | ثبت موقت خطا قبل از بوت کامل |
| `ErrorHandler` | `set_error_handler`/`set_exception_handler`/shutdown |
| `ExceptionHandler` | منطق اصلی report/render |
| `ExceptionMapper` | نگاشت `Throwable` → `HttpException` |
| `RefCodeGenerator` | تولید کد ارجاع |
| `LogMasker` | پنهان‌سازی دادهٔ حساس |
| `Renderers\JsonRenderer` | RFC 9457 |
| `Renderers\HtmlRenderer` | صفحهٔ خطا |
| `Renderers\CliRenderer` | خروجی کنسول |
| `Renderers\DebugRenderer` | صفحهٔ غنی DEV |
| `ErrorCode` | ثابت‌های کد خطا |
| `WhoopsLike\SnippetExtractor` | استخراج کد اطراف خطا |
| `ErrorPage\ViewErrorPage` | قالب HTML خطا (خودبسنده، بدون View Engine) |
| `ErrorServiceProvider` | ثبت |

---

## ۳. API

```php
// ثبت (در Bootstrap)
$handler = new ExceptionHandler($logger, $config, $events, $viewRenderer);
ErrorHandler::register($handler, environment: 'production', failOnDeprecation: false);

// افزودن Mapper سفارشی
$handler->map(PaymentDeclinedException::class, fn ($e) => new HttpException(402, messageId: 'payment.declined'));

// Renderable
$handler->renderable(fn (WorkflowLockedException $e, RequestInterface $r)
    => $r->expectsJson() ? null : response()->view('errors.locked', [], 423));

// Reportable سفارشی
$handler->reportable(fn (CriticalDomainException $e) => $alerting->notify($e));

// غیرقابل گزارش
$handler->dontReport([ValidationException::class, RouteNotFoundException::class]);

// Level سفارشی
$handler->level(QueryException::class, LogLevel::Error);

// Context افزودنی
$handler->context(fn () => ['tenant' => TenantContext::identifier(), 'user' => Auth::id()]);
```

---

## ۴. الگوریتم‌ها

### ۴٫۱ جریان `handle(Throwable)`
```
1. $e = prepareException($e)         # نگاشت به HttpException در صورت نیاز
2. if shouldReport($e):
      context = merge(baseContext(), $e->reportContext(), runtimeContext())
      context = LogMasker::mask(context)
      level   = levelFor($e)
      logger.log(level, $e->messageId, context + ['exception' => $e])
      events.dispatch(ExceptionOccurred($e))
      foreach reportCallbacks: if handled → stop
3. $response = render($e, $request)
      a. if $e instanceof RenderableException && $e->render() !== null → return
      b. foreach renderCallbacks → if Response → return
      c. renderer = negotiate($request)   # Json / Html / Cli
      d. return renderer.render($e, $request)
4. return $response
```

### ۴٫۲ `shouldReport`
```
false اگر:
  - در $dontReport (یا instanceof یکی از آن‌ها)
  - $e instanceof ReportableException && !$e->shouldReport()
  - HttpException با status < 500 و config.error.report_client_errors = false
true در غیر این صورت
```

### ۴٫۳ ExceptionMapper (پیش‌فرض)
```php
private const array DEFAULT_MAP = [
    ValidationException::class          => 422,
    AuthenticationException::class      => 401,
    AuthorizationException::class       => 403,
    RouteNotFoundException::class       => 404,
    MethodNotAllowedException::class    => 405,
    UniqueConstraintException::class    => 409,
    TokenExpiredException::class        => 401,
    CsrfTokenMismatchException::class   => 419,
    TooManyRequestsException::class     => 429,
    FileNotFoundException::class        => 404,
    IllegalTransitionException::class   => 409,
    ModelNotFoundException::class       => 404,
];
```
🔒 نگاشت با **طولانی‌ترین تطبیق سلسله‌مراتبی** (اگر استثنا فرزند است، والد بررسی شود).

### ۴٫۴ Negotiation رندر
```
if PHP_SAPI === 'cli'                → CliRenderer
elif request->expectsJson()          → JsonRenderer
elif request is API route (/api/*)   → JsonRenderer
elif env === 'development' && debug  → DebugRenderer (Html)
else                                 → HtmlRenderer (safe)
```

### ۴٫۵ RefCode
```php
final class RefCodeGenerator
{
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';   // base32 بدون کاراکترهای مبهم

    public static function generate(): string
    {
        $bytes = \random_bytes(5);
        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= self::ALPHABET[\ord($bytes[$i % 5]) % 32 ^ ($i * 7 & 31)];
        }
        return $out;
    }
}
```
🔒 `refCode` در `X-Tavaan-Ref` Header و در Body پاسخ خطا و در لاگ.

### ۴٫۶ LogMasker
```php
final class LogMasker
{
    private const array DEFAULT_KEYS = [
        'password', 'password_confirmation', 'pass', 'passwd', 'pwd',
        'token', 'access_token', 'refresh_token', 'id_token', 'api_key', 'apikey',
        'secret', 'client_secret', 'private_key', 'authorization', 'cookie',
        'card_number', 'card', 'pan', 'cvv', 'cvv2', 'expiry',
        'ssn', 'national_id', 'national_code', 'iban', 'account_number',
        'otp', 'verification_code', 'session_id',
    ];

    private const array PATTERNS = [
        'card'    => '/\b(\d{4})\d{8,11}(\d{4})\b/',
        'mobile'  => '/\b(09\d{2})\d{3}(\d{4})\b/',
        'email'   => '/\b([A-Za-z0-9._%+-]{1,3})[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/',
        'jwt'     => '/\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{5,}\b/',
        'base64key' => '/\b[A-Za-z0-9+\/]{40,}={0,2}\b/',
    ];

    public static function mask(array $data, int $depth = 0): array
    {
        if ($depth > 8) { return ['…truncated…']; }

        $out = [];
        foreach ($data as $k => $v) {
            $key = \is_string($k) ? \strtolower($k) : '';

            if (\in_array($key, self::DEFAULT_KEYS, true) || self::matchesKeyPattern($key)) {
                $out[$k] = '********';
                continue;
            }
            if (\is_array($v)) { $out[$k] = self::mask($v, $depth + 1); continue; }
            if (\is_string($v)) { $out[$k] = self::maskString($v); continue; }
            if ($v instanceof \Stringable && self::isSensitiveObject($v)) { $out[$k] = '[REDACTED:' . $v::class . ']'; continue; }
            $out[$k] = $v;
        }
        return $out;
    }

    private static function maskString(string $v): string
    {
        foreach (self::PATTERNS as $pattern) {
            $v = \preg_replace_callback($pattern, static fn (array $m): string => self::partial($m[0]), $v) ?? $v;
        }
        return $v;
    }

    private static function partial(string $s): string
    {
        $len = \mb_strlen($s);
        if ($len <= 4) { return '****'; }
        return \mb_substr($s, 0, 2) . \str_repeat('*', \max(2, $len - 4)) . \mb_substr($s, -2);
    }
}
```

---

## ۵. صفحهٔ خطای DEV (DebugRenderer)

بخش‌ها (بدون View Engine — HTML خودبسنده با CSS inline):
1. **Header**: استثنا + messageId + refCode + کد خطا
2. **Message**: ترجمه‌شده + Hint
3. **Stack Trace**: قابل باز/بسته، با لینک به فایل:خط (در IDE با `phpstorm://open?file=…`)
4. **Code Snippet**: ۱۰ خط قبل/بعد با هایلایت خط خطا
5. **Request**: method, url, headers (masked), query, body (masked), cookies (masked)
6. **Context**: user, tenant, session, route, module
7. **Queries**: آخرین ۲۰ کوئری با زمان (اگر `db` فعال)
8. **Events**: آخرین ۲۰ رویداد
9. **Env**: نسخهٔ PHP، SAPI، extensionها، `app.env`
10. **Suggestions**: بر اساس نوع استثنا (جدول داخلی)

🔒 در PROD: فقط `messageId` ترجمه‌شده + `refCode` + لینک پشتیبانی + نسخهٔ app.

---

## ۶. پیکربندی

```php
// engines/error/config/error.php
return [
    'environment' => env_string('TVN_APP_ENV', 'production'),

    'reporting' => [
        'level_map' => [
            \Tavaan\Core\Exceptions\Database\QueryException::class => 'error',
            \Tavaan\Core\Exceptions\Validation\ValidationException::class => 'info',
        ],
        'dont_report' => [
            \Tavaan\Core\Exceptions\Validation\ValidationException::class,
            \Tavaan\Core\Exceptions\Http\RouteNotFoundException::class,
            \Tavaan\Core\Exceptions\Authorization\AuthorizationException::class,
            \Tavaan\Core\Exceptions\Auth\AuthenticationException::class,
        ],
        'report_client_errors' => false,
        'max_context_depth'    => 8,
    ],

    'rendering' => [
        'debug'         => env_bool('TVN_APP_DEBUG', false),
        'renderer'      => 'auto',          // auto|json|html|cli
        'json_format'   => 'rfc9457',       // rfc9457|simple
        'html_views'    => [
            403 => 'errors.403', 404 => 'errors.404', 419 => 'errors.419',
            429 => 'errors.429', 500 => 'errors.500', 503 => 'errors.503',
        ],
        'fallback_view' => 'errors.generic',
        'show_ref_code' => true,
        'support_url'   => env_string('TVN_SUPPORT_URL', null),
    ],

    'deprecations' => [
        'fail_in_ci'   => env_bool('TVN_FAIL_ON_DEPRECATION', false),
        'log_channel'  => 'deprecations',
        'max_per_request' => 10,            // جلوگیری از log flooding
    ],

    'masking' => [
        'extra_keys'   => [],
        'extra_patterns' => [],
        'disable'      => false,
    ],

    'fatal' => [
        'memory_limit_reserve' => 262_144,  // ذخیره برای رندر خطای OOM
        'safe_page'    => true,
    ],
];
```

---

## ۷. امنیت

| تهدید | کنترل |
|---|---|
| نشت مسیر/SQL/Secret در PROD | `HtmlRenderer` safe mode + Masker |
| نشت در پیام خطای JSON | فقط `messageId` ترجمه‌شده |
| XSS در صفحهٔ خطای DEV | escape همهٔ مقادیر (حتی در DEV) |
| Log Flooding | `max_per_request` + Rate Limit روی لاگ |
| OOM هنگام رندر خطا | Memory Reserve (۲۵۶KB) آزاد می‌شود |
| Timing leak از پیام خطا | پیام یکسان برای اعتبارنامهٔ نادرست |

---

## ۸. کارایی

| عملیات | هدف |
|---|---|
| `report()` با Log File | ≤ ۱ms |
| `render()` JSON | ≤ ۰٫۵ms |
| `render()` HTML safe | ≤ ۲ms |
| `render()` Debug (DEV) | ≤ ۳۰ms |
| سربار ErrorHandler در حالت عادی | ۰ (فقط ثبت handler) |

---

## ۹. معیار پذیرش 🧪
- AC-ERR-01..08 (مطابق DOC-013 §11).
- AC-ERR-09: در CI، یک `E_USER_DEPRECATED` عمدی → Failure.
- AC-ERR-10: صفحهٔ DEV شامل trace + snippet + request masked است.
- AC-ERR-11: `tvn error:simulate <code>` صفحهٔ خطا را تولید می‌کند (برای QA).

---

## ۱۰. مراجع
| منبع | الهام | تفاوت |
|---|---|---|
| Laravel `Handler` | renderable/reportable، `dontReport` | افزودن Mapper سلسله‌مراتبی، RefCode، MessageId |
| Whoops/Filp | صفحهٔ خطای غنی | خودنوشته بدون پکیج، با Masking |
| Symfony ErrorHandler | Debug page، Deprecation handler | ساده‌تر، بدون HTML-injection پیچیده |
| RFC 9457 | Problem Details | فرمت پیش‌فرض JSON |
| Sentry | Context/Tags | بدون وابستگی، Adapter اختیاری |

---

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.error.v1
engine:
  id: tavaan.engine.error
  namespace: Tavaan\Engine\Error
  path: engines/error
  priority: P0
  phase: M1
  depends_on: [config, log, event]
  facade: null
  tables: []
  events_emitted: [exception.occurred, exception.reported, deprecation.detected]
  config_file: engines/error/config/error.php
  public_api:
    - class: ExceptionHandler
      methods: [report, render, map, renderable, reportable, dontReport, level, context]
    - class: ErrorHandler
      methods: [register, unregister, handleError, handleException, handleShutdown]
    - class: LogMasker
      methods: [mask, maskString, addKeys, addPatterns]
  loc_estimate: 800
  acceptance:
    - {id: AC-ERR-03, test: 'engines/error/tests/Feature/ProductionRenderTest.php'}
    - {id: AC-ERR-05, test: 'engines/error/tests/Unit/LogMaskerTest.php'}
```
