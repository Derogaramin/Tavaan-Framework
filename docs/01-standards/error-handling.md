# DOC-013 — مدیریت خطا و استثناها (Error Handling) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-013 |
| وضعیت | Approved v1.0 |
| موتور مسئول | `engines/error` + `engines/log` |
| سند وابسته | DOC-010 (قواعد PHP)، DOC-016 (امنیت) |

---

## ۱. فلسفه

1. **Fail Fast**: خطا در نزدیک‌ترین نقطه به منشأ پرتاب می‌شود.
2. **Fail Loud**: هیچ خطایی بی‌لاگ نمی‌ماند (حتی اگر به کاربر نمایش داده نشود).
3. **Fail Safe**: در PROD هیچ جزئیات فنی به کاربر نشت نمی‌کند.
4. **Fail Explainable**: هر خطا دارای `messageId`، `code`، `context`، `hint`، `docsUrl` و `refCode` است.
5. **Recoverable by Design**: خطاهای موقت (Deadlock، Timeout، Lock) با Retry Policy مدیریت می‌شوند.

---

## ۲. سلسله‌مراتب استثناها (Exception Hierarchy)

```
\Throwable
├── \Error                                   (خطاهای زبانی — هرگز catch نشود مگر در Handler)
└── \Exception
    └── TavaanException  (abstract, implements RenderableException, ContextualException)
        ├── Core\
        │   ├── ContainerException            (Binding/Resolve)
        │   ├── CircularDependencyException
        │   ├── BindingResolutionException
        │   ├── ConfigException
        │   └── AutoloadException
        ├── Http\
        │   ├── HttpException (abstract)
        │   │   ├── BadRequestException        (400)
        │   │   ├── UnauthorizedException      (401)
        │   │   ├── PaymentRequiredException   (402)
        │   │   ├── ForbiddenException         (403)
        │   │   ├── RouteNotFoundException     (404)
        │   │   ├── MethodNotAllowedException  (405)
        │   │   ├── UnsupportedMediaTypeException (415)
        │   │   ├── UnprocessableEntityException  (422)
        │   │   ├── TooManyRequestsException   (429)
        │   │   └── ServerErrorException       (500)
        │   └── CsrfTokenMismatchException     (419)
        ├── Validation\
        │   └── ValidationException            (422 + errors)
        ├── Database\
        │   ├── QueryException                 (SQL + Bindings masked)
        │   ├── UniqueConstraintException      (→ 409)
        │   ├── ForeignKeyConstraintException
        │   ├── DeadlockException              (retryable)
        │   ├── ConnectionLostException        (retryable)
        │   └── MigrationException
        ├── Auth\
        │   ├── AuthenticationException        (401)
        │   ├── InvalidCredentialsException
        │   ├── TokenExpiredException
        │   ├── TokenInvalidException
        │   ├── AccountLockedException
        │   └── TwoFactorRequiredException
        ├── Authorization\
        │   └── AuthorizationException         (403)
        ├── Cache\StoreUnavailableException    (fallback-able)
        ├── Queue\
        │   ├── JobException (abstract)
        │   ├── MaxAttemptsExceededException
        │   └── JobTimedOutException
        ├── Filesystem\
        │   ├── FileNotFoundException
        │   ├── DiskFullException
        │   └── PathTraversalException         (security)
        ├── Crypto\
        │   ├── DecryptException
        │   └── KeyMissingException
        ├── View\
        │   ├── ViewNotFoundException
        │   └── TemplateCompilationException
        ├── I18n\MissingTranslationException   (log-only, never fatal)
        ├── Workflow\
        │   ├── IllegalTransitionException
        │   └── WorkflowDefinitionException
        ├── Payment\
        │   ├── GatewayException
        │   └── PaymentVerificationException
        └── Engine\
            ├── EngineNotInstalledException
            ├── EngineCapabilityException      (ext موجود نیست)
            └── EngineBootException
```

### ۲٫۱ استثناهای «کنترل جریان» — ممنوع
🔒 از استثنا برای جریان عادی استفاده نکنید (مثل `find()` → `NotFound`). به‌جای آن `?Type` برگردانید
و استثنا فقط برای حالت غیرمنتظره.
⚠️ استثنا: `findOrFail()` که صریحاً برای پرتاب طراحی شده.

---

## ۳. آناتومی `TavaanException`

```php
<?php
declare(strict_types=1);

namespace Tavaan\Core\Exceptions;

/**
 * پایهٔ همهٔ استثناهای تَوان.
 *
 * @api 1.0
 */
abstract class TavaanException extends \RuntimeException implements
    RenderableException,
    ContextualException,
    ReportableException
{
    /** کد ارجاع ۸ کاراکتری برای پشتیبانی (بدون نشت جزئیات) */
    public readonly string $refCode;

    /**
     * @param string                      $messageId کلید ترجمه (هرگز متن خام)
     * @param array<string, scalar|null>  $context   دادهٔ زمینه (Masking خودکار)
     * @param string|null                 $hint      راهنمای رفع (برای توسعه‌دهنده)
     * @param int                         $code      کد عددی دامنه‌ای
     */
    public function __construct(
        public readonly string $messageId,
        public readonly array $context = [],
        public readonly ?string $hint = null,
        public readonly ?string $docsUrl = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageId, $code, $previous);
        $this->refCode = RefCodeGenerator::generate();
    }

    /** پیام محلی‌سازی‌شده (در زمان Render) */
    public function localizedMessage(TranslatorInterface $t): string
    {
        return $t->get($this->messageId, $this->context);
    }

    public function shouldReport(): bool
    {
        return true;
    }

    public function reportContext(): array
    {
        return [
            'exception' => static::class,
            'messageId' => $this->messageId,
            'code'      => $this->code,
            'refCode'   => $this->refCode,
            'context'   => LogMasker::mask($this->context),
            'hint'      => $this->hint,
        ];
    }
}
```

### ۳٫۱ توابع سازندهٔ نام‌دار (Named Constructors) — الگوی توصیه‌شده

```php
final class RouteNotFoundException extends HttpException
{
    public static function forPath(string $method, string $path): self
    {
        return new self(
            messageId: 'http.route.not_found',
            context: ['method' => $method, 'path' => $path],
            hint: 'مسیر را در routes/ بررسی کنید یا `tvn route:list` بزنید.',
            docsUrl: 'docs/03-engines/router.md#404',
            statusCode: 404,
        );
    }
}
```

---

## ۴. کدهای خطا (Error Codes) 🔒

### ۴٫۱ ساختار کد
```
T<Area><NNN>
T    = ثابت (همیشه T)
Area = ۲ یا ۳ حرف، شناسهٔ حوزه (جدول زیر)
NNN  = شمارهٔ سه‌رقمی یکتا در آن حوزه (001..999)
```

**حوزه‌های در حال استفاده (دارای کد ثبت‌شده):**

| Area | حوزه | Area | حوزه |
|---|---|---|---|
| `AU` | Auth (احراز هویت) | `AZ` | Authorization (دسترسی) |
| `CF` | Config (پیکربندی) | `CR` | Container (کانتینر/DI) |
| `DB` | Database (پایگاه داده) | `EN` | Engine (موتور) |
| `EV` | Event (رویداد) | `FS` | Filesystem (فایل) |
| `HT` | Http (وب/روت) | `PY` | Payment (پرداخت) |
| `SE` | Security (امنیت) | `VA` | Validation (اعتبارسنجی) |
| `WF` | Workflow (گردش‌کار) | | |

**حوزه‌های رزروشده (هنوز بدون کد — برای فازهای بعد):**
`CA`=Cache، `QU`=Queue، `VW`=View، `I18`=I18n، `MD`=Module، `OB`=Observability،
`CY`=Crypto، `KR`=Kernel/Core، `AU`/`AZ` توسعه می‌یابند.

> ⚠️ توجه: `CR` برای **Container** است (نه Core). خطاهای هستهٔ عمومی از حوزهٔ رزروشدهٔ `KR` استفاده می‌کنند.

### ۴٫۲ جدول کامل کدها (مرجع یگانه — همچنین در `engines/error/codes.php`)

| Code | MessageId | HTTP | حوزه | توضیح |
|---|---|---|---|---|
| TAU001 | `auth.credentials.invalid` | 401 | Auth | نام کاربری/رمز نادرست |
| TAU002 | `auth.token.expired` | 401 | Auth | توکن منقضی |
| TAU003 | `auth.account.locked` | 423 | Auth | حساب قفل است |
| TAZ001 | `authorization.denied` | 403 | Authorization | دسترسی مجاز نیست |
| TCF001 | `config.generic` | 500 | Config | خطای عمومی پیکربندی |
| TCF002 | `config.frozen` | 500 | Config | تغییر config در حالت Frozen |
| TCF003 | `config.not_cacheable` | 500 | Config | Closure در config (غیرقابل کش) |
| TCF004 | `core.env.parse_failed` | 500 | Config | خطای پارس `.env` |
| TCF005 | `config.schema_invalid` | 500 | Config | نقض Schema پیکربندی |
| TCF006 | `config.missing_key` | 500 | Config | کلید الزامی موجود نیست |
| TCR001 | `container.binding_not_found` | 500 | Container | binding یافت نشد |
| TCR002 | `container.circular_dependency` | 500 | Container | وابستگی چرخه‌ای |
| TCR003 | `container.ambiguous_union` | 500 | Container | Union Type مبهم |
| TCR004 | `container.untyped_parameter` | 500 | Container | پارامتر بدون type |
| TCR005 | `container.scope_not_active` | 500 | Container | Scope فعال نیست |
| TDB001 | `database.query.failed` | 500 | Database | خطای کوئری |
| TDB002 | `database.unique_violation` | 409 | Database | مقدار تکراری |
| TDB003 | `database.deadlock` | 500 (retry) | Database | بن‌بست |
| TEN001 | `engine.not_installed` | 500 | Engine | موتور نصب نیست |
| TEN002 | `engine.capability_missing` | 501 | Engine | افزونهٔ PHP لازم موجود نیست |
| TEV001 | `event.listener_failed` | 500 | Event | خطا در Listener |
| TEV002 | `event.max_listeners` | 500 | Event | عبور از حد Listenerها |
| TEV003 | `event.outbox_unavailable` | 500 | Event | Outbox در دسترس نیست |
| TEV004 | `event.not_allowlisted` | 400 | Event | رویداد در allowlist نیست |
| TEV005 | `event.poison` | 500 | Event | رویداد سمّی (deserialize شکست) |
| TFS001 | `filesystem.not_found` | 404 | Filesystem | فایل یافت نشد |
| THT001 | `http.route.not_found` | 404 | Http | مسیر یافت نشد |
| THT002 | `http.method.not_allowed` | 405 | Http | متد مجاز نیست |
| THT003 | `http.csrf.mismatch` | 419 | Http | توکن CSRF نامعتبر |
| THT004 | `http.rate_limited` | 429 | Http | بیش از حد مجاز |
| TPY001 | `payment.gateway.failed` | 502 | Payment | خطای درگاه |
| TSE001 | `security.path_traversal` | 400 | Security | تلاش عبور از مسیر |
| TVA001 | `validation.failed` | 422 | Validation | اعتبارسنجی ناموفق |
| TWF001 | `workflow.illegal_transition` | 409 | Workflow | گذار نامجاز |

### ۴٫۳ قواعد کد
- 🔒 این جدول **مرجع یگانه** است؛ هر کد جدید باید هم اینجا و هم در `engines/error/codes.php` ثبت شود.
- 🔒 کد تکراری = خطای CI (`tvn` در بوت yuniqueness را بررسی می‌کند).
- 🔒 Area باید از فهرست §4.1 باشد؛ Area جدید = افزودن به جدول + ADR.
- ⚠️ `refCode` (زمان اجرا، ۸ کاراکتر) با `code` (طراحی، `T<Area><NNN>`) متفاوت است: `refCode` برای ردیابی لاگ.
- 💡 کدهای هر موتور همچنین در §۱۱ مستند همان موتور فهرست می‌شوند (مثلاً `docs/03-engines/config.md` §11).

---

## ۵. خطاهای PHP → استثنا (Error Handler)

```php
final class ErrorHandler
{
    public function register(): void
    {
        \set_error_handler($this->handleError(...));
        \set_exception_handler($this->handleException(...));
        \register_shutdown_function($this->handleShutdown(...));
    }

    /**
     * تبدیل همهٔ اخطارها به ErrorException (در dev/CI).
     *
     * @throws \ErrorException
     */
    public function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (($this->errorReporting & $severity) === 0) {
            return true; // احترام به @ و error_reporting
        }

        // 🔒 Deprecated در CI = شکست
        if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
            if ($this->failOnDeprecation) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
            $this->logger->warning('deprecated', ['message' => $message, 'file' => $file, 'line' => $line]);
            return true;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /** خطاهای فاجعه‌بار (OOM, Fatal) از shutdown function */
    public function handleShutdown(): void
    {
        $error = \error_get_last();
        if ($error !== null && \in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $this->handleFatal($error);
        }
    }
}
```

| سطح PHP | رفتار در DEV | رفتار در PROD | رفتار در CI |
|---|---|---|---|
| `E_ERROR`/`E_PARSE` | صفحهٔ خطا + trace | صفحهٔ ۵۰۰ + refCode | Failure |
| `E_WARNING` | Exception + trace | Log `error` + ادامه | Failure |
| `E_NOTICE`/`E_USER_NOTICE` | Log `notice` | Log `notice` | Failure (اختیاری) |
| `E_DEPRECATED` | Log `warning` + Debug Bar | Log `warning` | **Failure** |
| `E_STRICT` | نادیده (8.4 حذف) | نادیده | Failure |

---

## ۶. زنجیرهٔ پردازش استثنا (Exception Pipeline)

```
Throwable
  → ExceptionHandler::report($e)          [1] آیا Reportable است؟
      → LogMasker::mask(context)          [2] پنهان‌سازی دادهٔ حساس
      → Logger::error(...)                [3] ثبت در کانال مناسب
      → Sentry/Remote Adapter (اختیاری)   [4]
  → ExceptionHandler::render($e, $request)
      → RenderableException::render()?    [5] خود استثنا پاسخ دارد؟
      → ExceptionMapper: map($e) → HttpException/Response  [6]
      → View/JSON negotiation              [7]
      → ErrorResponseBuilder               [8]
  → Response (با Header X-Tavaan-Ref: <refCode>)
  → Terminate: Event `ExceptionOccurred`  [9]
```

### ۶٫۱ قالب پاسخ خطای JSON (استاندارد RFC 9457 Problem Details)

```json
{
  "type": "https://docs.tavaan.dev/errors/TVA001",
  "title": "اعتبارسنجی ناموفق",
  "status": 422,
  "detail": "مقادیر ارسالی معتبر نیستند.",
  "instance": "/api/v1/orders",
  "ref": "A7F3K9Q2",
  "errors": {
    "mobile": ["شمارهٔ موبایل معتبر نیست"],
    "amount": ["مبلغ باید بزرگ‌تر از صفر باشد"]
  },
  "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736"
}
```

### ۶٫۲ قالب صفحهٔ خطای HTML
- **DEV**: Stack Trace + Code Snippet (با هایلایت) + Request Dump + Query Log + Suggestions.
- **PROD**: پیام محلی + `refCode` + لینک پشتیبانی. 🔒 بدون trace.

---

## ۷. استثناهای قابل بازیابی (Retryable)

```php
interface RetryableException
{
    public function retryable(): bool;
    public function maxRetries(): int;      // پیش‌فرض ۳
    public function backoffMs(int $attempt): int;  // نمایی + jitter
}
```

| استثنا | Retry | Backoff |
|---|---|---|
| `DeadlockException` | ✅ ۳ بار | 50ms → 150ms → 450ms |
| `ConnectionLostException` | ✅ ۲ بار | 100ms → 500ms |
| `LockTimeoutException` | ✅ ۵ بار | 200ms + jitter |
| `HttpTimeoutException` (کلاینت) | ✅ ۳ بار | نمایی |
| `RateLimitException` (کلاینت) | ✅ با `Retry-After` | — |
| `UniqueConstraintException` | ❌ | — |
| `ValidationException` | ❌ | — |
| `AuthorizationException` | ❌ | — |

```php
// الگوی استفاده
$this->retry->run(
    attempts: 3,
    backoff: Backoff::exponential(baseMs: 50, factor: 3, jitter: true),
    retryOn: [DeadlockException::class, ConnectionLostException::class],
    callback: fn () => $this->orders->save($order),
);
```

---

## ۸. قواعد catch 🔒

```php
// ❌ ممنوع
try { $x = $s->do(); } catch (\Throwable $e) {}
try { $x = $s->do(); } catch (\Exception $e) { return false; }

// ✅ مجاز
try {
    return $gateway->charge($order);
} catch (GatewayTimeoutException $e) {
    $this->logger->warning('payment.gateway.timeout', ['order' => $order->id(), 'ref' => $e->refCode]);
    $this->events->dispatch(new PaymentRetryScheduled($order->id()));
    return PaymentResult::pending(refCode: $e->refCode);
} catch (GatewayException $e) {          // هرگز \Throwable در کد کاربردی
    throw new PaymentFailedException(messageId: 'payment.failed', previous: $e);
}
```

| قاعده |
|---|
| R1 🔒 catch فقط برای استثناهای **مشخص** یا خانوادهٔ دامنه‌ای |
| R2 🔒 ممنوعیت `catch (\Throwable)` خارج از Kernel Handler و Worker Loop |
| R3 🔒 در rethrow، `previous` حتماً پاس شود |
| R4 🔒 هیچ catch بدون Log یا Transformation |
| R5 ⚠️ `finally` برای آزادسازی منابع (File Handle، Connection) |
| R6 🔒 در Job/Worker، catch سطح بالا مجاز است اما باید به `failed()` منتهی شود |
| R7 🔒 ممنوعیت تغییر نوع استثنا بدون حفظ `previous` |
| R8 ⚠️ ترجمهٔ استثنا به MessageId، نه به متن خام |

---

## ۹. خطاهای اعتبارسنجی (Validation Errors)

```php
final class ValidationException extends TavaanException
{
    /** @param array<string, list<string>> $errors field => messageIds */
    public function __construct(
        public readonly array $errors,
        public readonly array $data = [],
        int $statusCode = 422,
    ) {
        parent::__construct(
            messageId: 'validation.failed',
            context: ['fields' => \array_keys($errors)],
            hint: 'خطاهای هر فیلد در کلید errors پاسخ است.',
            code: ErrorCode::VALIDATION_FAILED,
        );
    }

    public function firstError(string $field): ?string { /* … */ }
    public function hasErrors(): bool { /* … */ }
}
```
- 🔒 پیام‌های خطا همیشه MessageId و در `lang/` ترجمه می‌شوند.
- ⚠️ در فرم HTML: `old()` + `errors` در Session flash.
- ⚠️ در API: RFC 9457 + `errors` map.

---

## ۱۰. RefCode و ردیابی

| قلم | توضیح |
|---|---|
| `refCode` | ۸ کاراکتر base32 از CSPRNG، یکتا در هر رخداد خطا |
| `traceId` | W3C Trace ID (۳۲ hex) در همهٔ لاگ‌های همان درخواست |
| `spanId` | ۱۶ hex |
| Header | `X-Tavaan-Ref: <refCode>` و `traceparent` |
| لاگ | `refCode` و `traceId` در همهٔ رکوردهای مرتبط |
| UI | کاربر refCode را به پشتیبانی می‌دهد → `tvn log:find <refCode>` |

---

## ۱۱. معیار پذیرش (Acceptance Criteria) 🧪

- 🧪 AC-EH-01: هر استثنای هسته دارای `messageId` و کلید ترجمهٔ `fa` و `en` است.
- 🧪 AC-EH-02: در CI، تبدیل `E_DEPRECATED` به Exception فعال است و تست دارد.
- 🧪 AC-EH-03: پاسخ ۵۰۰ در PROD فاقد trace/file/line است (تست snapshot).
- 🧪 AC-EH-04: `refCode` در Header و Body پاسخ‌های خطا موجود است.
- 🧪 AC-EH-05: دادهٔ حساس (password, token, card) در لاگ mask می‌شود (تست unit).
- 🧪 AC-EH-06: `DeadlockException` با Retry خودکار بازیابی می‌شود (تست integration).
- 🧪 AC-EH-07: هیچ `catch` خالی در codebase (بررسی AST).
- 🧪 AC-EH-08: همهٔ کدهای `T**NNN` در `codes.php` ثبت و یکتا هستند.
