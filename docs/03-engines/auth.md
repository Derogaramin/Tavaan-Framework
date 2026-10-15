# DOC-056 — موتور Auth | احراز هویت

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-056 |
| Engine ID | `tavaan.engine.auth` |
| Namespace | `Tavaan\Engine\Auth` |
| اولویت | **P0** — فاز M4 |
| وابستگی | `session`, `hash`, `crypt`, `event`, `database` |
| Facade | `Auth` → `auth` |
| LOC | ۲۰۰۰ |

---

## ۱. هدف
احراز هویت چند‌Guard با Session/Token/JWT، ثبت‌نام، بازیابی رمز، OTP، 2FA، Device Tracking
و Session Hardening — همه خودنوشته و امن.

## ۲. مفاهیم
| واژه | تعریف |
|---|---|
| **Guard** | مکانیزم احراز (web/api/admin) |
| **Provider** | منبع کاربر (Database/LDAP/Custom) |
| **Authenticatable** | موجودی که قابل احراز است (User) |
| **Token** | JWT/Opaque برای API |
| **Session** | نشست وب (کوکی‌محور) |
| **Device** | نشست فعال روی یک دستگاه |

## ۳. معماری
```
Request
  │
  ├─ Guard Manager ──┬── SessionGuard (web)     → از Session
  │                  ├── TokenGuard (api)        → Bearer JWT
  │                  └── CustomGuard
  │
  ├─ UserProvider ──→ Database (users) / Eloquent / LDAP
  │
  └─ Accessors: Auth::user(), Auth::id(), Auth::check(), Auth::attempt()
```

## ۴. API
```php
// بررسی
Auth::check(); Auth::guest();
Auth::user(); Auth::id(); Auth::userOrFail();

// ورود
Auth::attempt(['email'=>$e, 'password'=>$p], remember: true);
Auth::once([...]);                    // بدون session (یک‌بار)
Auth::login($user, remember: true);
Auth::loginUsingId($id);
Auth::logout();
Auth::logoutOtherDevices($currentPassword);
Auth::validate([...]);                // فقط بررسی اعتبارنامه

// Guard خاص
Auth::guard('api')->user();
Auth::guard('admin')->attempt([...]);

// Token
$token = Auth::token()->issue($user, ttl: 3600, scopes: ['*']);
$user = Auth::token()->authenticate($token);
Auth::token()->revoke($tokenId);
Auth::token()->refresh($refreshToken);

// OTP / 2FA
Auth::otp()->send($user, channel: 'sms');      // returns masked
Auth::otp()->verify($user, $code);
Auth::twoFactor()->enable($user);
Auth::twoFactor()->verifyTotp($user, $code);
Auth::twoFactor()->generateRecoveryCodes($user);

// Device
Auth::devices()->list($user);
Auth::devices()->revoke($deviceId);

// ثبت‌نام / بازیابی
Auth::register($data);
Auth::sendPasswordResetLink($email);
Auth::resetPassword($token, $newPassword);
```

## ۵. SessionGuard — جریان ورود (امن)
```php
public function attempt(array $credentials, bool $remember = false): bool
{
    $user = $this->provider->retrieveByCredentials($credentials);

    if ($user === null || !$this->provider->validateCredentials($user, $credentials)) {
        $this->events->dispatch(new LoginFailed($credentials['email'] ?? null, $this->request->ip()));
        $this->rateLimiter->hit($this->throttleKey($credentials));       // 🔒 افزایش شمارنده
        return false;
    }

    if (!$this->checkLockout($credentials)) { return false; }            // 🔒 قفل حساب
    if ($user->isBanned()) { throw new AccountBannedException(); }

    $this->login($user, $remember);
    $this->rateLimiter->clear($this->throttleKey($credentials));
    $this->events->dispatch(new LoginSucceeded($user->getAuthIdentifier(), $this->request->ip()));
    return true;
}

public function login(Authenticatable $user, bool $remember = false): void
{
    // 🔒 Session Fixation: بازتولید Session ID
    $this->session->regenerate(destroy: true);

    $this->session->put($this->getName(), $user->getAuthIdentifier());
    $this->session->put('_fingerprint', $this->fingerprint());           // 🔒 اثر انگشت

    if ($remember) {
        $this->ensureRememberToken($user);
        $this->queueRecallerCookie($user);
    }
    $this->recordDevice($user);
    $this->setUser($user);
}
```

### ۵٫۱ Fingerprint (جلوگیری از Session Hijack)
```php
private function fingerprint(): string
{
    return \hash('sha256', \implode('|', [
        $this->request->userAgent() ?? '',
        $this->request->header('Accept-Language') ?? '',
        // ⚠️ IP را شامل نکن (برای موبایل تغییر می‌کند) یا با mask /24
    ]));
}
// در هر درخواست: اگر fingerprint تغییر کرد → نیازمند re-auth یا logout
```

### ۵٫۲ Rehash خودکار
```php
public function validateCredentials(Authenticatable $user, array $credentials): bool
{
    $valid = $this->hasher->check($credentials['password'], $user->getAuthPassword());
    if ($valid && $this->hasher->needsRehash($user->getAuthPassword())) {
        $this->provider->rehashPasswordIfRequired($user, $credentials);
    }
    return $valid;
}
```

## ۶. TokenGuard / JWT (خودنوشته)
```php
final class JwtEncoder
{
    public function encode(array $payload, string $algorithm = 'HS256'): string
    {
        $this->assertAllowedAlgorithm($algorithm);      // 🔒 رد 'none'
        $header = ['typ' => 'JWT', 'alg' => $algorithm, 'kid' => $this->keyId];

        $payload += [
            'iss' => $this->issuer, 'aud' => $this->audience,
            'iat' => \time(), 'nbf' => \time(),
            'exp' => \time() + $this->ttl, 'jti' => Ulid::generate()->toString(),
        ];

        $h = Base64Url::encode(\json_encode($header, JSON_THROW_ON_ERROR));
        $p = Base64Url::encode(\json_encode($payload, JSON_THROW_ON_ERROR));
        $sig = Base64Url::encode($this->sign("{$h}.{$p}", $algorithm));
        return "{$h}.{$p}.{$sig}";
    }

    public function decode(string $jwt): array
    {
        [$h, $p, $s] = \explode('.', $jwt) + [null,null,null];
        if ($h === null || $p === null || $s === null) { throw new TokenInvalidException(); }

        $header = \json_decode(Base64Url::decode($h), true, flags: JSON_THROW_ON_ERROR);
        $alg = $header['alg'] ?? 'none';

        // 🔒 الگوریتم پین‌شده؛ از header نپذیر
        if ($alg !== $this->algorithm) {
            throw new TokenInvalidException(messageId: 'auth.token.algorithm_mismatch');
        }
        if (!$this->verify("{$h}.{$p}", Base64Url::decode($s), $alg)) {
            throw new TokenInvalidException(messageId: 'auth.token.signature_invalid');
        }

        $payload = \json_decode(Base64Url::decode($p), true, flags: JSON_THROW_ON_ERROR);
        $now = \time();
        if (($payload['exp'] ?? 0) < $now) { throw new TokenExpiredException(); }
        if (($payload['nbf'] ?? 0) > $now) { throw new TokenInvalidException(); }
        if (($payload['iss'] ?? '') !== $this->issuer) { throw new TokenInvalidException(); }
        if (($payload['aud'] ?? '') !== $this->audience) { throw new TokenInvalidException(); }
        if ($this->isRevoked($payload['jti'])) { throw new TokenInvalidException(); }

        return $payload;
    }
}
```
🔒 **الگوریتم پین‌شده** (از config، نه header) → جلوگیری از Algorithm Confusion.
🔒 `alg=none` همیشه رد می‌شود.
🔒 `jti` برای Blacklist/Revoke.
🔒 RS256/EdDSA برای کلید عمومی/خصوصی (Key Confusion: هرگز RSA public را به‌عنوان HMAC secret نپذیر).

### ۶٫۱ Refresh Token Rotation + Reuse Detection
```php
public function refresh(string $refreshToken): TokenPair
{
    $data = $this->decryptRefresh($refreshToken);
    $family = $data['family'];

    if ($this->isUsed($data['jti'])) {
        // 🔒 استفادهٔ مجدد = سرقت احتمالی → کل family را revoke کن
        $this->revokeFamily($family);
        $this->events->dispatch(new TokenReuseDetected($family));
        throw new TokenInvalidException(messageId: 'auth.token.reuse_detected');
    }
    $this->markUsed($data['jti']);
    return $this->issueWithFamily($family);     // توکن جدید در همان family
}
```

## ۷. OTP و 2FA
```php
// OTP (موبایل/ایمیل)
public function send(Authenticatable $user, string $channel = 'sms'): SendResult
{
    $this->rateLimiter->assertAllowed("otp:{$user->id()}", max: 5, per: 300);   // ۵ در ۵ دقیقه
    $code = (string) \random_int(100000, 999999);         // 🔒 ۶ رقم CSPRNG
    $this->cache->put("otp:{$user->id()}", $this->hasher->make($code), ttl: 120); // ۲ دقیقه
    $this->notifier->sendOtp($user, $code, $channel);
    return new SendResult(maskedTarget: $this->mask($user), expiresAt: 120);
}

public function verify(Authenticatable $user, string $code): bool
{
    $hash = $this->cache->get("otp:{$user->id()}");
    if ($hash === null) { return false; }                   // منقضی
    $ok = $this->hasher->check($code, $hash);               // 🔒 هش، نه مقایسهٔ خام
    if ($ok) { $this->cache->forget("otp:{$user->id()}"); } // 🔒 یک‌بارمصرف
    return $ok;
}
```

### ۷٫۱ TOTP (RFC 6238)
```php
public function generateTotp(string $secret, ?int $timestamp = null, int $period = 30, int $digits = 6): string
{
    $counter = \intdiv($timestamp ?? \time(), $period);
    $binCounter = \pack('N*', 0, $counter);
    $hash = \hash_hmac('sha1', $binCounter, Base32::decode($secret), true);
    $offset = \ord($hash[\strlen($hash) - 1]) & 0x0F;
    $code = ((\ord($hash[$offset]) & 0x7F) << 24 | (\ord($hash[$offset+1]) & 0xFF) << 16
           | (\ord($hash[$offset+2]) & 0xFF) << 8 | (\ord($hash[$offset+3]) & 0xFF)) % (10 ** $digits);
    return \str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
}
public function verifyTotp(string $secret, string $code, int $window = 1): bool
{
    $now = \time();
    for ($i = -$window; $i <= $window; $i++) {
        if (\hash_equals($this->generateTotp($secret, $now + $i * 30), $code)) { return true; }
    }
    return false;
}
```

## ۸. پیکربندی
```php
// config/auth.php
return [
    'defaults' => ['guard' => 'web', 'passwords' => 'users'],
    'guards' => [
        'web'   => ['driver' => 'session', 'provider' => 'users'],
        'api'   => ['driver' => 'jwt',     'provider' => 'users'],
        'admin' => ['driver' => 'session', 'provider' => 'users'],
    ],
    'providers' => [
        'users' => ['driver' => 'eloquent', 'model' => \App\Models\User::class],
    ],
    'jwt' => [
        'algorithm' => 'HS256', 'ttl' => 3600, 'refresh_ttl' => 1209600,
        'issuer' => env_string('APP_URL'), 'audience' => env_string('APP_URL'),
        'leeway' => 30,
    ],
    'throttle' => [
        'login' => ['max' => 5, 'decay' => 60, 'lock' => 15 * 60],   // ۵ شکست → ۱۵ دقیقه قفل
        'otp'   => ['max' => 5, 'decay' => 300],
        'reset' => ['max' => 3, 'decay' => 3600],
    ],
    'session_hardening' => [
        'regenerate_on_login' => true, 'fingerprint' => true,
        'idle_timeout' => 1800, 'absolute_timeout' => 43200,
    ],
    'passwords' => [
        'users' => ['table' => 'password_resets', 'expire' => 3600, 'throttle' => 60],
    ],
    'otp' => ['length' => 6, 'ttl' => 120, 'channels' => ['sms','mail']],
    'two_factor' => ['enabled' => true, 'required_for' => ['admin','super-admin'], 'window' => 1],
];
```

## ۹. امنیت (SEC-AU-* در DOC-016)
| تهدید | کنترل |
|---|---|
| Brute Force | Throttle + Lockout |
| Session Fixation | regenerate در login |
| Session Hijack | fingerprint + HttpOnly + Secure |
| Timing | `hash_equals`, پیام یکسان |
| Token Theft | short TTL + refresh rotation + reuse detection |
| Algorithm Confusion | alg پین‌شده |
| Credential Stuffing | captcha پس از N شکست + breached password check |
| OTP Replay | یک‌بارمصرف + هش + TTL |
| User Enumeration | پیام یکسان user/pass، rate limit یکسان |

🔒 `auth.credentials.invalid` برای هر دو حالت (کاربر نبود / رمز اشتباه).

## ۱۰. کارایی
| عملیات | هدف |
|---|---|
| `Auth::attempt` (Argon2id) | ≤ ۱۰۰ms (طبیعی برای هش) |
| `Auth::user()` از session (کش) | ≤ ۰٫۱ms |
| JWT encode/decode | ≤ ۰٫۲ms |
| TOTP verify | ≤ ۰٫۱ms |

## ۱۱. تست
| ID | سناریو |
|---|---|
| T-01 | attempt با اعتبارنامهٔ درست/غلط |
| T-02 | regenerate session در login (fixation) |
| T-03 | Throttle: ۶ تلاش → ۴۲۹ + lockout |
| T-04 | Remember me + recaller cookie |
| T-05 | JWT encode/decode + `alg=none` رد |
| T-06 | Algorithm confusion (RS256→HS256) رد |
| T-07 | Refresh rotation + reuse detection |
| T-08 | OTP یک‌بارمصرف + انقضا |
| T-09 | TOTP با RFC 6238 vectors |
| T-10 | rehash خودکار |
| T-11 | پیام یکسان برای user/pass غلط |
| T-12 | Device list + revoke |

## ۱۲. معیار پذیرش 🧪
- AC-AUTH-01..12 (§11).
- AC-AUTH-13: همهٔ کنترل‌های SEC-AU-* در DOC-016 پیاده و تست‌شده.

## ۱۳. مراجع
| منبع | تفاوت |
|---|---|
| Laravel Auth | Guard/Provider | JWT/OTP/2FA داخلی (بدون Sanctum/Fortify) |
| Firebase JWT | JWT | خودنوشته با pinning |
| RFC 6238 | TOTP | — |
| OWASP ASVS | Session/Password | — |

## ۱۴. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.auth.v1
engine:
  id: tavaan.engine.auth
  namespace: Tavaan\Engine\Auth
  path: engines/auth
  priority: P0
  phase: M4
  depends_on: [session, hash, crypt, event, database]
  facade: {name: Auth, accessor: auth}
  tables: [password_resets, personal_access_tokens, user_devices, two_factor_secrets]
  events_emitted: [auth.login.succeeded, auth.login.failed, auth.logout,
                   auth.token.reuse_detected, auth.otp.sent, auth.password.reset]
  config_file: config/auth.php
  public_api:
    - class: AuthManager
      methods: [guard, user, id, check, guest, attempt, once, login, loginUsingId,
                logout, validate, token, otp, twoFactor, devices, register,
                sendPasswordResetLink, resetPassword]
  loc_estimate: 2000
  acceptance:
    - {id: AC-AUTH-05, test: 'engines/auth/tests/Security/JwtAlgorithmTest.php'}
    - {id: AC-AUTH-13, test: 'engines/auth/tests/Security/SessionHardeningTest.php'}
```
