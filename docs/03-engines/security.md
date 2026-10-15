# DOC-059 — موتور Security | CSRF، RateLimit، Header، Sanitizer، SSRF

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-059 |
| Engine ID | `tavaan.engine.security` |
| Namespace | `Tavaan\Engine\Security` |
| اولویت | **P0** — فاز M4 |
| وابستگی | `http`, `crypt`, `cache`, `session` |
| Facade | `Security` → `security` |
| LOC | ۱۵۰۰ |
| مرتبط | DOC-016 (Security Policy) |

---

## ۱. هدف
تجمیع کنترل‌های امنیتی عرضی: **CSRF**، **Rate Limiting**، **Security Headers**،
**HTML Sanitizer**، **SSRF Guard**، **Signature Verification**، **Input Normalizer**.

## ۲. زیربخش‌ها

### ۲٫۱ CSRF
```php
// Middleware
final class CsrfVerifyMiddleware implements MiddlewareInterface
{
    private const array SAFE_METHODS = ['GET','HEAD','OPTIONS'];

    public function handle(RequestInterface $request, \Closure $next, string ...$p): ResponseInterface
    {
        if (\in_array($request->method(), self::SAFE_METHODS, true) || $this->isExcluded($request)) {
            return $next($request);
        }

        $token = $request->input('_token')
              ?? $request->header('X-CSRF-TOKEN')
              ?? $request->header('X-XSRF-TOKEN');   // کوکی رمزنگاری‌شده

        if ($token === null || !$this->verify($token, $request)) {
            throw new CsrfTokenMismatchException(messageId: 'http.csrf.mismatch');   // 419
        }
        return $next($request);
    }

    private function verify(string $token, RequestInterface $request): bool
    {
        $sessionToken = $request->session()->token();
        // 🔒 hash_equals (constant-time)
        return \hash_equals($sessionToken, $this->decryptIfEncrypted($token));
    }
}

// API
Csrf::token();                    // توکن فعلی
Csrf::field();                    // <input hidden>
Csrf::rotate();                   // چرخش (پس از login)
```
🔒 توکن CSRF = HMAC(session_id + user_id) یا random در session.
🔒 Double-Submit Cookie برای APIهای stateless (با کوکی رمزنگاری‌شده).
🔒 `SameSite=Lax` لایهٔ دوم دفاع.

### ۲٫۲ Rate Limiting
```php
// Middleware: throttle:60,1  (۶۰ بار در دقیقه)
RateLimit::attempt(key: 'login:'.$ip, maxAttempts: 5, decaySeconds: 60);
RateLimit::tooManyAttempts($key, $max);
RateLimit::hit($key, $decay);
RateLimit::remaining($key, $max);
RateLimit::clear($key);
RateLimit::availableIn($key);      // ثانیه تا آزادسازی
```

**الگوریتم‌ها:**
| الگوریتم | توضیح | کاربرد |
|---|---|---|
| Fixed Window | شمارش در پنجرهٔ ثابت | ساده |
| Sliding Window | پنجرهٔ لغزان (دقیق‌تر) | پیش‌فرض |
| Token Bucket | توکن با نرخ پرشدن | API |
| Leaky Bucket | خروجی با نرخ ثابت | صف |

```php
// Sliding Window (Redis) — اتمیک با Lua
private const string LUA_SLIDING = <<<'LUA'
local key = KEYS[1]
local now = tonumber(ARGV[1])
local window = tonumber(ARGV[2])
local limit = tonumber(ARGV[3])
redis.call('ZREMRANGEBYSCORE', key, 0, now - window)
local count = redis.call('ZCARD', key)
if count < limit then
  redis.call('ZADD', key, now, now .. '-' .. math.random(1000000))
  redis.call('EXPIRE', key, math.ceil(window/1000))
  return limit - count - 1
end
return -1
LUA;
```
🔒 در صورت نبود Redis → File/Database با `flock`/`SELECT FOR UPDATE`.
🔒 پاسخ `429` با `Retry-After` و `X-RateLimit-*` headers.

### ۲٫۳ Security Headers
→ فهرست کامل در DOC-016 §3.7. پیاده‌سازی در `SecurityHeadersMiddleware`.
```php
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(RequestInterface $r, \Closure $next, string ...$p): ResponseInterface
    {
        $response = $next($r);
        foreach ($this->resolveHeaders() as $name => $value) {
            if ($value !== null && (!$response->hasHeader($name) || $this->override)) {
                $response = $response->withHeader($name, $value);
            }
        }
        return $response->withoutHeader('X-Powered-By');
    }

    private function resolveHeaders(): array
    {
        $c = $this->config;
        return [
            'Content-Security-Policy'   => $c->get('security.headers.csp'),
            'Strict-Transport-Security' => $c->get('security.headers.hsts'),
            'X-Content-Type-Options'    => 'nosniff',
            'X-Frame-Options'           => $c->get('security.headers.frame', 'DENY'),
            'Referrer-Policy'           => $c->get('security.headers.referrer', 'strict-origin-when-cross-origin'),
            'Permissions-Policy'        => $c->get('security.headers.permissions'),
            'Cross-Origin-Opener-Policy'=> 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];
    }
}
```
🔒 غیرفعال‌سازی هر header → هشدار لاگ + badge در Debug Bar.

### ۲٫۴ HTML Sanitizer (Allowlist)
```php
$clean = Security::sanitize($dirtyHtml, config: 'rich_text');

// پیکربندی allowlist
'sanitizers' => [
    'rich_text' => [
        'allowed_tags' => ['p','br','strong','em','u','ul','ol','li','a','h2','h3','blockquote','code','pre','img'],
        'allowed_attributes' => [
            'a'   => ['href','title','rel','target'],
            'img' => ['src','alt','width','height'],
        ],
        'allowed_protocols' => ['http','https','mailto'],
        'strip_comments' => true,
        'remove_scripts' => true,
    ],
    'strict' => ['allowed_tags' => [], 'escape_all' => true],
],
```
🔒 الگوریتم: پارس HTML → درخت DOM → حذف گره‌های غیرمجاز → escape attributeها → بازسازی.
🔒 حذف `on*` event handlers، `javascript:` URI، `<script>`, `<iframe>` (مگر allowlist).
🔒 پیاده‌سازی خالص (بدون ext-dom، با fallback به ext-dom اگر موجود).

### ۲٫۵ SSRF Guard
```php
final class SsrfGuard
{
    private const array BLOCKED_CIDRS = [
        '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
        '169.254.0.0/16', '0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24',
        '::1/128', 'fc00::/7', 'fe80::/10', '::/128',
    ];

    public function assertAllowed(string $url): void
    {
        $parts = \parse_url($url);
        $scheme = \strtolower($parts['scheme'] ?? '');
        if (!\in_array($scheme, ['http','https'], true)) {
            throw new SsrfException(messageId: 'security.ssrf.scheme', context: ['scheme' => $scheme]);
        }
        $host = $parts['host'] ?? '';
        if ($host === '') { throw new SsrfException(messageId: 'security.ssrf.no_host'); }

        // 🔒 resolve DNS سپس بررسی IP (جلوگیری از DNS Rebinding)
        $ips = $this->resolve($host);
        foreach ($ips as $ip) {
            if ($this->isBlocked($ip)) {
                throw new SsrfException(messageId: 'security.ssrf.private_ip', context: ['ip' => $ip]);
            }
        }
    }

    private function isBlocked(string $ip): bool
    {
        foreach (self::BLOCKED_CIDRS as $cidr) {
            if (IpMatcher::matches($ip, $cidr)) { return true; }
        }
        return false;
    }
}
```
🔒 استفاده در `http-client` قبل از هر درخواست خروجی.
🔒 DNS Pinning: resolve یک‌بار، اتصال به همان IP (جلوگیری از rebinding).
🔒 Allowlist دامنه برای webhookها.

### ۲٫۶ Signature Verification
```php
$signed = Security::sign('/unsubscribe?user=1', expires: now()->addDays(7));
Security::validateSignature($request);   // در middleware 'signed'
```
→ الگوریتم در DOC-040 §8.2.

### ۲٫۷ Input Normalizer
```php
$clean = Security::normalize($input);
// - Unicode NFKC normalize
// - حذف Bidi/Control/Zero-width chars
// - یکسان‌سازی ارقام عربی/فارسی → لاتین (برای validate)
// - Trim
// - حذف null bytes
```

## ۳. پیکربندی
```php
// config/security.php
return [
    'csrf' => ['enabled'=>true, 'except'=>[], 'encrypted_cookie'=>false],
    'rate_limit' => ['enabled'=>true, 'driver'=>'cache', 'default'=>['max'=>60,'decay'=>60]],
    'headers' => [/* DOC-016 §3.7 */],
    'sanitizers' => [/* بالا */],
    'ssrf' => ['enabled'=>true, 'allow_private'=>false, 'allowed_hosts'=>[]],
    'normalize' => ['unicode'=>true, 'strip_bidi'=>true, 'digits_to_latin'=>true],
];
```

## ۴. کارایی
| عملیات | هدف |
|---|---|
| CSRF verify | ≤ ۰٫۰۵ms |
| RateLimit check (cache) | ≤ ۰٫۲ms |
| Sanitize ۱۰KB HTML | ≤ ۲ms |
| SSRF guard (بدون DNS) | ≤ ۰٫۰۱ms |

## ۵. تست
| T-01 | CSRF: بدون توکن → 419 |
| T-02 | CSRF: توکن درست → پاس |
| T-03 | RateLimit: ۶۱ درخواست → 429 + Retry-After |
| T-04 | Sliding window دقت |
| T-05 | Sanitizer: `onerror=`, `javascript:` حذف |
| T-06 | SSRF: `169.254.169.254` → رد |
| T-07 | SSRF: DNS rebinding |
| T-08 | Security headers همه حاضر |
| T-09 | Signature با expires منقضی → رد |
| T-10 | Normalizer: zero-width/bidi حذف |

## ۶. معیار پذیرش 🧪
- AC-SEC-01..10. AC-SEC-11: همهٔ کنترل‌های SEC-* در DOC-016 که به این موتور مربوط است.

## ۷. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.security.v1
engine:
  id: tavaan.engine.security
  namespace: Tavaan\Engine\Security
  path: engines/security
  priority: P0
  phase: M4
  depends_on: [http, crypt, cache, session]
  facade: {name: Security, accessor: security}
  tables: []
  components: [csrf, rate_limit, headers, sanitizer, ssrf, signature, normalizer]
  config_file: config/security.php
  public_api:
    - class: Security
      methods: [sanitize, sign, validateSignature, normalize, ssrfCheck]
    - class: RateLimiter
      methods: [attempt, tooManyAttempts, hit, remaining, clear, availableIn]
    - class: Csrf
      methods: [token, field, rotate, verify]
  loc_estimate: 1500
```
