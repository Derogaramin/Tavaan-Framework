# DOC-041 — موتور Session | نشست

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-041 |
| Engine ID | `tavaan.engine.session` |
| Namespace | `Tavaan\Engine\Session` |
| اولویت | **P0** — فاز M2 |
| وابستگی | `http`, `crypt`, `cache` |
| Facade | `Session` → `session` |
| LOC | ۹۰۰ |

## ۱. هدف
مدیریت نشست امن با ۵ درایور (file/redis/database/cache/array)، رمزنگاری، Session
Hardening، Regeneration، Flash Data و Garbage Collection.

## ۲. درایورها
| درایور | مقیاس‌پذیری | Resident-safe | کاربرد |
|---|---|---|---|
| `array` | ❌ | ✅ | تست، CLI |
| `file` | ❌ | ⚠️ | تک‌سرور |
| `cookie` | ✅ | ✅ | stateless (رمزنگاری‌شده) |
| `database` | ✅ | ✅ | بدون Redis |
| `redis` | ✅ | ✅ | تولید |

## ۳. API
```php
Session::get('key', $default);
Session::put('key', $value);
Session::has('key'); Session::exists('key');
Session::pull('key');                      // get + forget
Session::forget('key'); Session::flush();
Session::all();
Session::push('cart.items', $item);
Session::increment('visits');

// Flash (فقط برای درخواست بعد)
Session::flash('status', 'success');
Session::reflash(); Session::keep(['status']);
Session::getOldInput('email');             // برای فرم

// Regeneration (امنیت)
Session::regenerate(destroy: true);        // 🔒 پس از login
Session::migrate();
Session::invalidate();
Session::id(); Session::getName();

// Token
Session::token();                          // CSRF token
Session::previousUrl();
```

## ۴. چرخهٔ زندگی
```
StartSessionMiddleware::handle()
  │
  ├─ loadSession(id از کوکی)
  │    → Handler::read(id) → unserialize (با allowlist)
  ├─ checkExpiration (idle/absolute)
  ├─ checkFingerprint (DOC-056 §5.1)
  │    → اگر تغییر → invalidate
  ├─ request را با session مرتبط کن
  ▼
  ... (پردازش درخواست)
  ▼
  terminate():
  ├─ ageFlashData()
  ├─ Handler::write(id, serialize(data))
  └─ Set-Cookie (HttpOnly, Secure, SameSite)
```

## ۵. پیاده‌سازی امن
```php
final class SessionManager
{
    public function start(Request $request): void
    {
        $id = $this->getIdFromCookie($request) ?? $this->generateId();

        if (!$this->isValidId($id)) {
            $id = $this->generateId();      // 🔒 رد ID نامعتبر (fixation)
        }

        $this->session = $this->handler->read($id);

        // 🔒 Idle timeout
        if ($this->isExpired($this->session, $this->idleTimeout)) {
            $this->session->flush();
            $id = $this->generateId();
        }

        $this->session->setId($id);
        $this->session->start();
    }

    private function generateId(): string
    {
        return \bin2hex(\random_bytes(20));  // 🔒 ۴۰ کاراکتر CSPRNG
    }

    public function regenerate(bool $destroy = false): void
    {
        $oldId = $this->session->id();
        $newId = $this->generateId();
        $data  = $this->session->all();

        if ($destroy) { $this->handler->destroy($oldId); }
        $this->session->setId($newId);
        $this->session->replace($data);
        // 🔒 کوکی جدید در terminate ارسال می‌شود
    }

    private function isValidId(string $id): bool
    {
        return \preg_match('/^[a-f0-9]{40}$/', $id) === 1;   // فقط hex معتبر
    }
}
```

### ۵٫۱ Cookie نشست
```php
new Cookie(
    name: $this->name,               // 'tavaan_session'
    value: $encryptedId,             // 🔒 رمزنگاری‌شده یا signed
    expire: $this->lifetime,
    path: '/',
    domain: $this->domain,
    secure: $this->secure,           // 🔒 true در prod
    httpOnly: true,                  // 🔒 همیشه
    sameSite: 'Lax',                 // 🔒 پیش‌فرض Lax
);
```

### ۵٫۲ Serialization امن
```php
private function serialize(array $data): string
{
    return \json_encode($data, JSON_THROW_ON_ERROR);   // 🔒 JSON نه serialize()
}
private function unserialize(string $raw): array
{
    if (\str_starts_with($raw, 'enc:')) {               // cookie driver: رمزنگاری‌شده
        $raw = $this->encrypter->decrypt(\substr($raw, 4));
    }
    $data = \json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    return \is_array($data) ? $data : [];
}
```
🔒 **هرگز** `unserialize()` روی session (Object Injection). فقط JSON یا با allowlist.

## ۶. پیکربندی
```php
// config/session.php
return [
    'driver'   => env_string('TVN_SESSION_DRIVER', 'file'),   // file|redis|database|cookie|array
    'lifetime' => env_int('TVN_SESSION_LIFETIME', 120),        // دقیقه
    'expire_on_close' => false,
    'encrypt'  => env_string('TVN_SESSION_DRIVER') === 'cookie',
    'lottery'  => [2, 100],                                    // GC: ۲ از ۱۰۰
    'cookie'   => env_string('TVN_SESSION_COOKIE', 'tavaan_session'),
    'path'     => '/',
    'domain'   => env_string('TVN_SESSION_DOMAIN', null),
    'secure'   => env_bool('TVN_SESSION_SECURE', false),       // 🔒 در prod: true
    'http_only'=> true,
    'same_site'=> env_string('TVN_SESSION_SAME_SITE', 'lax'),   // lax|strict|none
    'idle_timeout'     => env_int('TVN_SESSION_IDLE', 1800),   // ثانیه
    'absolute_timeout' => env_int('TVN_SESSION_ABSOLUTE', 43200),
    'fingerprint'      => true,
];
```

## ۷. امنیت (SEC-AU-06..08)
| تهدید | کنترل |
|---|---|
| Session Fixation | regenerate پس از login + رد ID نامعتبر |
| Session Hijack | HttpOnly + Secure + SameSite + fingerprint |
| Session Prediction | ID با `random_bytes(20)` |
| Object Injection | JSON serialization (نه `serialize`) |
| Session Flood | idle/absolute timeout + GC |
| Cross-Site (CSRF) | SameSite=Lax + CSRF token (موتور security) |

## ۸. Garbage Collection
```php
// file driver: در lottery (۲ از ۱۰۰) فایل‌های منقضی حذف می‌شوند
private function gc(): void
{
    if (\random_int(1, $this->lottery[1]) > $this->lottery[0]) { return; }
    $expire = \time() - $this->lifetime * 60;
    foreach (\glob($this->path . '/sess_*') as $file) {
        if (\filemtime($file) < $expire) { @\unlink($file); }
    }
}
```

## ۹. تست
| T-01 | start/get/put/has/flush |
| T-02 | regenerate → ID جدید، داده حفظ |
| T-03 | idle timeout → flush |
| T-04 | fingerprint تغییر → invalidate |
| T-05 | cookie HttpOnly/Secure/SameSite |
| T-06 | flash فقط یک درخواست |
| T-07 | Object injection در serialize → رد |
| T-08 | ID نامعتبر → regenerate |
| T-09 | همهٔ ۵ درایور |
| T-10 | GC فایل‌های منقضی |

## ۱۰. معیار پذیرش 🧪
- AC-SES-01..10. AC-SES-11: در Resident (Swoole)، session بین درخواست‌ها نشت نمی‌کند.

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.session.v1
engine:
  id: tavaan.engine.session
  namespace: Tavaan\Engine\Session
  path: engines/session
  priority: P0
  phase: M2
  depends_on: [http, crypt, cache]
  facade: {name: Session, accessor: session}
  tables: [sessions]
  drivers: [array, file, cookie, database, redis]
  config_file: config/session.php
  public_api:
    - class: Session
      methods: [get, put, has, exists, pull, forget, flush, all, push, increment,
                flash, reflash, keep, regenerate, migrate, invalidate, id, token]
  loc_estimate: 900
```
