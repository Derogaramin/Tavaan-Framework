# DOC-034 — موتور Pipeline | خط لوله

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-034 |
| Engine ID | `tavaan.engine.pipeline` |
| Namespace | `Tavaan\Engine\Pipeline` |
| اولویت | **P0** — فاز M1 |
| وابستگی | `container` |
| Facade | ندارد (مستقیم استفاده می‌شود) |
| LOC | ۲۵۰ |
| مصرف‌کنندگان | HTTP Middleware، Job Middleware، Console Middleware، Validation Chain |

---

## ۱. هدف

یک الگوی عمومی و بسیار سبک برای «عبور دادن یک Payload از زنجیره‌ای از پردازشگرها» که
پایهٔ Middleware در HTTP، Queue، Console و حتی Validation است.

- ✅ Generic (هر نوع Passable، نه فقط Request)
- ✅ پشتیبانی از پارامترهای Middleware (`auth:web,guest`)
- ✅ ترتیب معکوس صحیح (onion)
- ✅ `then()`/`thenReturn()`/`send()`
- ✅ بدون Closure leak (بدون `Closure::bind`)
- ✅ Stage-After (`terminate`) جدا

---

## ۲. معماری و API

```php
use Tavaan\Engine\Pipeline\Pipeline;

$response = (new Pipeline($container))
    ->send($request)                                   // Passable
    ->through([                                        // pipes
        TrustProxiesMiddleware::class,
        SecurityHeadersMiddleware::class,
        'auth:web,admin',                               // با پارامتر
        fn ($passable, $next) => $next($passable),      // Closure
    ])
    ->via('handle')                                     // نام متد
    ->then(fn ($request) => $dispatcher->dispatch($request));
```

### ۲٫۱ امضا

| متد | امضا | توضیح |
|---|---|---|
| `send` | `send(mixed $passable): static` | دادهٔ ورودی |
| `through` | `through(array\|iterable $pipes): static` | فهرست پردازشگرها |
| `pipe` | `pipe(string\|callable\|object $p): static` | افزودن یکی |
| `via` | `via(string $method = 'handle'): static` | نام متد |
| `then` | `then(callable $destination): mixed` | اجرای زنجیره |
| `thenReturn` | `thenReturn(): mixed` | destination = identity |
| `after` | `after(callable $stage): static` | افزودن به `terminate` |
| `terminate` | `terminate(mixed $passable, mixed $result): void` | اجرای post-stages |

### ۲٫۲ قرارداد Pipe
```php
interface MiddlewareInterface
{
    /**
     * @template TPassable
     * @param TPassable $passable
     * @param callable(TPassable): mixed $next
     */
    public function handle(mixed $passable, \Closure $next, string ...$params): mixed;

    /** اختیاری — پس از پایان */
    public function terminate(mixed $passable, mixed $result): void;
}
```
🔒 `terminate()` اختیاری است؛ Pipeline با `method_exists` بررسی می‌کند.

---

## ۳. الگوریتم

```php
public function then(callable $destination): mixed
{
    $pipeline = \array_reduce(
        \array_reverse($this->pipes),
        $this->carry(),
        fn (mixed $passable): mixed => $destination($passable),
    );

    return $pipeline($this->passable);
}

private function carry(): \Closure
{
    return function (\Closure $next, string|callable|object $pipe): \Closure {
        return function (mixed $passable) use ($next, $pipe): mixed {
            if (\is_callable($pipe)) {
                return $pipe($passable, $next);
            }

            [$name, $parameters] = $this->parsePipeString($pipe);

            $instance = \is_object($name) ? $name : $this->container->make($name);

            return $instance->{$this->method}(
                $passable,
                static fn (mixed $p): mixed => $next($p),
                ...$parameters,
            );
        };
    };
}

/** 'auth:web,admin' → ['auth', ['web','admin']] */
private function parsePipeString(string|object $pipe): array
{
    if (!\is_string($pipe)) {
        return [$pipe, []];
    }
    [$name, $parameters] = \array_pad(\explode(':', $pipe, 2), 2, '');

    if ($name === '' || !\preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $name)) {
        // alias از config یا class-string
        $name = $this->aliases[$name] ?? $name;
    }

    return [$name, $parameters === '' ? [] : \explode(',', $parameters)];
}
```

### ۳٫۱ چرا `array_reduce` + `array_reverse`؟
- بدون نیاز به آرایهٔ mutable در زمان اجرا
- هر Closure فقط `$next` را می‌بندد (بدون leak)
- O(n) ساخت، O(n) اجرا

### ۳٫۲ Terminate Stages
```php
public function terminate(mixed $passable, mixed $result): void
{
    foreach ($this->terminateStages as $stage) {
        $stage($passable, $result);
    }
    // + فراخوانی terminate روی همهٔ pipeها (ترتیب رفت، نه برگشت)
    foreach ($this->resolvedPipes as $pipe) {
        if (\method_exists($pipe, 'terminate')) {
            $pipe->terminate($passable, $result);
        }
    }
}
```

---

## ۴. انواع Pipe پشتیبانی‌شده

| نوع | مثال |
|---|---|
| Class-String | `CsrfMiddleware::class` |
| Alias | `'auth'`, `'throttle:60,1'` |
| Closure | `fn ($p, $next) => $next($p)` |
| Object | `$myMiddleware` |
| Array callable | `[Handler::class, 'process']` |
| Invokable | `new class { public function __invoke($p,$n){} }` |

---

## ۵. پیکربندی

```php
// engines/pipeline/config/pipeline.php
return [
    'aliases' => [
        'auth'      => \Tavaan\Engine\Auth\Middleware\AuthenticateMiddleware::class,
        'guest'     => \Tavaan\Engine\Auth\Middleware\RedirectIfAuthenticated::class,
        'throttle'  => \Tavaan\Engine\Security\Middleware\RateLimitMiddleware::class,
        'can'       => \Tavaan\Engine\Rbac\Middleware\AuthorizeMiddleware::class,
        'verified'  => \Tavaan\Engine\Auth\Middleware\EnsureEmailVerified::class,
        'signed'    => \Tavaan\Engine\Security\Middleware\ValidateSignature::class,
        'cache'     => \Tavaan\Engine\Http\Middleware\ResponseCacheMiddleware::class,
        'tenant'    => \Tavaan\Engine\Tenancy\Middleware\IdentifyTenantMiddleware::class,
    ],
    'max_depth'   => 64,          // حفاظت در برابر زنجیرهٔ خیلی طولانی
    'trace'       => env_bool('TVN_PIPELINE_TRACE', false),
];
```

---

## ۶. امنیت
| تهدید | کنترل |
|---|---|
| تزریق alias از ورودی کاربر | 🔒 alias فقط از config؛ parse با regex سخت‌گیرانه |
| زنجیرهٔ بی‌نهایت | `max_depth` |
| Exception در Middleware بدون terminate | `try/finally` در Kernel |

---

## ۷. کارایی
| عملیات | هدف |
|---|---|
| ساخت Pipeline با ۱۲ pipe | ≤ ۵µs |
| اجرای ۱۲ pipe (بدون منطق) | ≤ ۱۰µs |
| Memory footprint | ≤ ۲۰KB |

---

## ۸. تست
| ID | سناریو |
|---|---|
| T-01 | ترتیب اجرا (onion): before/after در هر لایه |
| T-02 | پارامتر `throttle:60,1` → `['60','1']` |
| T-03 | Closure به‌عنوان pipe |
| T-04 | short-circuit: pipe بدون `$next` → زنجیره قطع |
| T-05 | exception در pipe میانی → propagate + terminate اجرا شد |
| T-06 | `thenReturn()` = identity |
| T-07 | Passable دلخواه (string, array, DTO) |
| T-08 | `max_depth` → خطا |
| T-09 | Alias نامعتبر → `PipelinePipeNotFoundException` |

---

## ۹. معیار پذیرش 🧪
- AC-PIP-01: همهٔ تست‌های §8 سبز.
- AC-PIP-02: سربار Pipeline روی ۱۲ Middleware تهی ≤ ۱۰µs.
- AC-PIP-03: در HTTP/Queue/Console بدون تغییر کد قابل استفاده است.
- AC-PIP-04: `terminate` حتی با Exception اجرا می‌شود.

---

## ۱۰. مراجع
| منبع | تفاوت |
|---|---|
| Laravel `Pipeline` | حذف `array_reduce` با `Closure::bind` (سنگین)، افزودن `after()`/`terminate()` عمومی، Generic Passable |
| Koa.js `compose` | الگوی onion |
| PSR-15 | سازگاری مفهومی (نه Interface) |

---

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.pipeline.v1
engine:
  id: tavaan.engine.pipeline
  namespace: Tavaan\Engine\Pipeline
  path: engines/pipeline
  priority: P0
  phase: M1
  depends_on: [container]
  facade: null
  tables: []
  config_file: engines/pipeline/config/pipeline.php
  public_api:
    - class: Pipeline
      methods: [send, through, pipe, via, then, thenReturn, after, terminate]
  loc_estimate: 250
  acceptance:
    - {id: AC-PIP-01, test: 'engines/pipeline/tests/Unit/PipelineTest.php'}
```
