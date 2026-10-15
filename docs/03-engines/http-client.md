# DOC-064 — موتور HttpClient | کلاینت HTTP

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-064 |
| Engine ID | `tavaan.engine.http-client` |
| Namespace | `Tavaan\Engine\HttpClient` |
| اولویت | **P1** — فاز M5 |
| وابستگی | `event`, `cache`, `config`؛ نرم: `security` (SSRF) |
| Facade | `Http` |
| افزونه | `curl` (اختیاری؛ fallback به stream) |
| LOC | ۱۳۰۰ |

## ۱. هدف
کلاینت HTTP با Fluent API، Retry، Timeout، Circuit Breaker، SSRF Guard، Pool (موازی)
و Mock برای تست — با curl یا stream fallback.

## ۲. API
```php
$response = Http::get('https://api.example.com/users/1');
$response = Http::post($url, ['key'=>'value']);
$response = Http::withHeaders(['Authorization'=>'Bearer '.$t])->withToken($t)->get($url);
$response = Http::asJson()->acceptJson()->post($url, $data);
$response = Http::asForm()->post($url, $data);
$response = Http::withQueryParameters(['page'=>2])->get($url);
$response = Http::timeout(10)->retry(3, 100)->get($url);
$response = Http::withCookies($cookies, 'example.com')->get($url);
$response = Http::attach('file', $resource, 'name.pdf')->post($url);   // multipart

$response->status(); $response->successful(); $response->failed();
$response->json(); $response->json('data.id'); $response->body();
$response->header('Content-Type'); $response->headers();
$response->throw();                          // throws اگر 4xx/5xx
$response->throwIf(fn($r) => $r->status() === 429);

// Pool (درخواست‌های موازی)
$responses = Http::pool(fn ($pool) => [
    $pool->as('user')->get($url1),
    $pool->as('posts')->get($url2),
]);
$responses['user']->json();

// Fake (تست)
Http::fake(['api.example.com/*' => Http::response(['ok'=>true], 200)]);
Http::assertSent(fn ($req) => $req->url() === $url);
```

## ۳. پیاده‌سازی (Transport)
```php
final class PendingRequest
{
    public function send(string $method, string $url, array $options = []): Response
    {
        $this->ssrfGuard->assertAllowed($url);         // 🔒 SSRF قبل از هر چیز
        $request = $this->buildRequest($method, $url, $options);

        return $this->retryHandler->run(
            attempts: $this->tries,
            backoff: $this->backoff,
            retryIf: fn (\Throwable $e) => $this->isRetryable($e),
            callback: fn () => $this->circuitBreaker->call(
                $this->serviceName($url),
                fn () => $this->transport->send($request),
            ),
        );
    }
}
```

### ۳٫۱ Transport (curl یا stream)
```php
interface HttpTransportInterface { public function send(RawHttpRequest $r): RawHttpResponse; }

final class CurlTransport implements HttpTransportInterface { /* curl_init, curl_setopt, curl_exec */ }
final class StreamTransport implements HttpTransportInterface { /* file_get_contents با stream_context */ }

// انتخاب در Compat::driver('http')
```
🔒 در حالت stream، `follow_location = 0` تا redirectها را خودمان با SSRF re-check مدیریت کنیم.

### ۳٫۲ Redirect با SSRF Re-check
```php
private function handleRedirect(RawHttpResponse $r, int $remaining): RawHttpResponse
{
    if ($remaining === 0 || !$r->isRedirect()) { return $r; }
    $location = $r->header('Location');
    $absolute = $this->resolveUrl($this->currentUrl, $location);
    $this->ssrfGuard->assertAllowed($absolute);      // 🔒 بررسی مجدد
    return $this->sendRaw($absolute, $remaining - 1);
}
```

## ۴. Circuit Breaker
```php
final class CircuitBreaker
{
    public function call(string $service, callable $action): mixed
    {
        $state = $this->state($service);
        if ($state === State::Open) {
            if ($this->shouldTryHalfOpen($service)) { /* اجازهٔ یک تست */ }
            else { throw new CircuitOpenException(messageId: 'http_client.circuit_open'); }
        }
        try {
            $result = $action();
            $this->recordSuccess($service);
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure($service);
            throw $e;
        }
    }
    // آستانه: ۵ خطا در ۶۰s → Open برای ۳۰s → Half-Open → Closed
}
```

## ۵. Retry با Backoff
```php
private function backoffMs(int $attempt): int
{
    $base = $this->backoffBase * (2 ** ($attempt - 1));   // نمایی
    $jitter = (int) ($base * 0.2 * (\random_int(0, 100) / 100));
    return \min($base + $jitter, $this->backoffMax);
}
```
🔒 فقط برای خطاهای retryable: timeout, 5xx, 429 (با `Retry-After`).

## ۶. پیکربندی
```php
// config/http-client.php
return [
    'timeout' => env_int('TVN_HTTP_TIMEOUT', 10),
    'connect_timeout' => 5,
    'retry' => ['times'=>3, 'sleep'=>100, 'backoff'=>'exponential'],
    'verify_tls' => env_bool('TVN_HTTP_VERIFY_TLS', true),
    'ca_bundle' => null,
    'proxy' => env_string('TVN_HTTP_PROXY', null),
    'user_agent' => 'Tavaan/1.0 (+https://tavaan.dev)',
    'ssrf_protection' => true,
    'allowed_hosts' => [],          // خالی = همه (با SSRF guard)
    'pool_concurrency' => 10,
    'circuit_breaker' => ['threshold'=>5, 'window'=>60, 'cooldown'=>30],
];
```

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| SSRF | SsrfGuard + re-check در redirect |
| TLS Downgrade | `verify_tls` پیش‌فرض true + cipher list |
| Credential نشت | Authorization header mask در لاگ |
| DoS با redirect زیاد | max redirects (5) |
| Response بزرگ | max size |

## ۸. کارایی
| عملیات | هدف |
|---|---|
| Overhead روی هر درخواست | ≤ ۱ms (جدای از شبکه) |
| Pool ۱۰ درخواست | موازی (نه ترتیبی) |
| SSRF check (بدون DNS) | ≤ ۰٫۰۱ms |

## ۹. تست
| T-01 | get/post/put/delete با fake |
| T-02 | `Http::fake()` + assertSent |
| T-03 | Retry روی timeout |
| T-04 | Circuit breaker: open پس از ۵ خطا |
| T-05 | SSRF: block IP خصوصی |
| T-06 | Redirect با SSRF re-check |
| T-07 | Pool موازی |
| T-08 | JSON/form/multipart |
| T-09 | curl نبود → stream fallback |

## ۱۰. معیار پذیرش 🧪
- AC-HTTPC-01..09.

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.http-client.v1
engine:
  id: tavaan.engine.http-client
  namespace: Tavaan\Engine\HttpClient
  path: engines/http-client
  priority: P1
  phase: M5
  depends_on: [event, cache, config]
  soft_depends_on: [security]
  extensions: {required: [], optional: [{name: curl, fallback: stream}]}
  facade: {name: Http, accessor: http.client}
  config_file: config/http-client.php
  public_api:
    - class: Factory
      methods: [get, post, put, patch, delete, withHeaders, withToken, asJson, asForm,
                timeout, retry, pool, fake, assertSent, withQueryParameters, attach]
  loc_estimate: 1300
```
