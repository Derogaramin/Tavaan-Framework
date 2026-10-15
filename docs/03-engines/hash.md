# DOC-055 — موتور Hash | درهم‌سازی رمز

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-055 |
| Engine ID | `tavaan.engine.hash` |
| Namespace | `Tavaan\Engine\Hash` |
| اولویت | **P0** — فاز M4 |
| وابستگی | `config` |
| Facade | `Hash` → `hash` |
| LOC | ۳۵۰ |

## ۱. هدف
هش رمز عبور با **Argon2id** (پیش‌فرض) و bcrypt (fallback)، با Rehash خودکار.

## ۲. API
```php
$hash = Hash::make('password123');
$ok   = Hash::check('password123', $hash);
$need = Hash::needsRehash($hash);
$info = Hash::info($hash);        // ['algo'=>'argon2id','memory_cost'=>65536,…]
```

## ۳. پیاده‌سازی
```php
final class Argon2idHasher implements HasherInterface
{
    public function __construct(
        private readonly int $memoryCost = 65536,   // 64 MB
        private readonly int $timeCost   = 4,
        private readonly int $threads    = 1,       // 🔒 1 برای جلوگیری از parallel attack
    ) {}

    public function make(string $value): string
    {
        if (!\defined('PASSWORD_ARGON2ID')) {
            return $this->bcryptFallback($value);
        }
        return \password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost,
            'time_cost'   => $this->timeCost,
            'threads'     => $this->threads,
        ]);
    }

    public function check(string $value, string $hash): bool
    {
        return \password_verify($value, $hash);     // 🔒 constant-time داخلی
    }

    public function needsRehash(string $hash): bool
    {
        return \password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost, 'time_cost' => $this->timeCost, 'threads' => $this->threads,
        ]);
    }

    private function bcryptFallback(string $value): string
    {
        return \password_hash($value, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
```

## ۴. Rehash خودکار (در Auth)
```php
// پس از verify موفق
if ($this->hasher->needsRehash($user->password)) {
    $user->password = $this->hasher->make($plainPassword);
    $user->save();
}
```
🔒 این اجازه می‌دهد پارامترها را با مرور زمان افزایش دهیم بدون reset رمز کاربران.

## ۵. پیکربندی
```php
// config/hash.php
return [
    'driver' => env_string('TVN_HASH_DRIVER', 'argon2id'),  // argon2id|bcrypt
    'argon2id' => ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1],
    'bcrypt'   => ['cost' => 12, 'rounds' => 12],
    'rehash_on_login' => true,
];
```

## ۶. امنیت
| تهدید | کنترل |
|---|---|
| Rainbow Table | Salt خودکار در `password_hash` |
| Brute Force | Argon2id memory-hard |
| Timing Attack | `password_verify` constant-time |
| Weak Params | حداقل‌های امن (memory 64MB) |
| Downgrade | rehash خودکار به قوی‌تر |

🔒 **هرگز** `md5`/`sha1`/`sha256` برای رمز عبور.
🔒 `threads=1` برای جلوگیری از حملات side-channel موازی.

## ۷. تست
| T-01 | make/check roundtrip |
| T-02 | رمز اشتباه → false |
| T-03 | needsRehash با cost قدیمی → true |
| T-04 | Argon2id نبود → bcrypt fallback |
| T-05 | دو hash از یک رمز متفاوت (salt) |

## ۸. معیار پذیرش 🧪
- AC-HASH-01..05. AC-HASH-06: هش Argon2id با `$argon2id$` شروع می‌شود.

## ۹. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.hash.v1
engine:
  id: tavaan.engine.hash
  namespace: Tavaan\Engine\Hash
  path: engines/hash
  priority: P0
  phase: M4
  depends_on: [config]
  facade: {name: Hash, accessor: hash}
  drivers: [argon2id, bcrypt]
  public_api:
    - class: HashManager
      methods: [make, check, needsRehash, info, driver]
  loc_estimate: 350
```
