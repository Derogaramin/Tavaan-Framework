# DOC-042 — موتور Cookie | کوکی

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-042 |
| Engine ID | `tavaan.engine.cookie` |
| Namespace | `Tavaan\Engine\Http\Cookie` |
| اولویت | **P0** — فاز M2 |
| وابستگی | `http`, `crypt` |
| Facade | `Cookie` → `cookie` |
| LOC | ۴۰۰ |

## ۱. هدف
مدیریت کوکی با رمزنگاری/امضا، صف‌سازی برای ارسال در پاسخ، و تنظیمات امن پیش‌فرض.

## ۲. API
```php
Cookie::make('theme', 'dark', minutes: 60, path:'/', secure:true, httpOnly:true, sameSite:'Lax');
Cookie::forever('remember', $token);
Cookie::queue(...);                     // صف برای ارسال در پاسخ
Cookie::get('theme');                    // از درخواست (decrypt اگر لازم)
Cookie::forget('theme');                 // کوکی با expire گذشته
```

## ۳. کوکی رمزنگاری‌شده/امضاشده
```php
final class Cookie
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly int $expire = 0,
        public readonly string $path = '/',
        public readonly ?string $domain = null,
        public readonly bool $secure = false,
        public readonly bool $httpOnly = true,     // 🔒 پیش‌فرض true
        public readonly string $sameSite = 'Lax',  // 🔒 پیش‌فرض Lax
        public readonly bool $encrypted = false,
    ) {}

    public function toArray(): array   // برای setcookie()
    {
        return [
            'expires' => $this->expire, 'path' => $this->path, 'domain' => $this->domain,
            'secure' => $this->secure, 'httponly' => $this->httpOnly, 'samesite' => $this->sameSite,
        ];
    }
}

// EncryptCookiesMiddleware: decrypt ورودی، encrypt خروجی
final class EncryptCookiesMiddleware implements MiddlewareInterface
{
    public function handle(RequestInterface $request, \Closure $next, string ...$p): ResponseInterface
    {
        $decrypted = [];
        foreach ($request->cookies() as $name => $value) {
            if (\in_array($name, $this->except, true)) { $decrypted[$name] = $value; continue; }
            try {
                $decrypted[$name] = $this->decrypt($value);   // 🔒 با hash_equals برای integrity
            } catch (DecryptException) {
                $decrypted[$name] = null;   // کوکی دستکاری‌شده → نادیده
            }
        }
        $request = $request->withCookies($decrypted);
        $response = $next($request);

        foreach ($this->cookieJar->queued() as $cookie) {
            if ($cookie->encrypted) { $cookie = $cookie->withValue($this->encrypt($cookie->value)); }
            $response = $response->withCookie($cookie);
        }
        return $response;
    }
}
```
🔒 کوکی دستکاری‌شده (MAC نامعتبر) → بی‌صدا `null` (نه crash).

## ۴. امنیت
| تهدید | کنترل |
|---|---|
| XSS سرقت کوکی | `HttpOnly` پیش‌فرض |
| CSRF | `SameSite=Lax/Strict` |
| Man-in-the-middle | `Secure` در prod |
| Tampering | رمزنگاری + MAC |
| Session Cookie | جدا از session (موتور session) |

🔒 کوکی‌های حساس (session, remember_token, XSRF) همیشه encrypted.
🔒 `XSRF-TOKEN` کوکی برای SPA (با SameSite).

## ۵. تست
| T-01 | make/queue → Set-Cookie در پاسخ |
| T-02 | encrypt/decrypt roundtrip |
| T-03 | کوکی دستکاری‌شده → null |
| T-04 | HttpOnly/Secure/SameSite پیش‌فرض |
| T-05 | forget → expire گذشته |
| T-06 | `except` برای کوکی‌های خاص |

## ۶. معیار پذیرش 🧪
- AC-COOK-01..06.

## ۷. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.cookie.v1
engine:
  id: tavaan.engine.cookie
  namespace: Tavaan\Engine\Http\Cookie
  path: engines/cookie
  priority: P0
  phase: M2
  depends_on: [http, crypt]
  facade: {name: Cookie, accessor: cookie}
  public_api:
    - class: CookieJar
      methods: [make, forever, queue, get, forget, hasQueued, queued]
  loc_estimate: 400
```
