# DOC-054 — موتور Crypt | رمزنگاری

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-054 |
| Engine ID | `tavaan.engine.crypt` |
| Namespace | `Tavaan\Engine\Crypt` |
| اولویت | **P0** — فاز M4 |
| وابستگی | `config` |
| Facade | `Crypt` → `encrypter` |
| افزونه | `openssl` (اجباری)، `sodium` (اختیاری برای Ed25519) |
| LOC | ۶۰۰ |

---

## ۱. هدف
رمزنگاری متقارن **AES-256-GCM** با Key Versioning، HKDF، HMAC، امضا (Ed25519/RSA)
و رمزنگاری Column-level برای PII — همه با openssl (بدون libsodium اجباری).

## ۲. API
```php
$cipher = Crypt::encrypt('متن محرمانه');         // string با prefix نسخه
$plain  = Crypt::decrypt($cipher);
$cipher = Crypt::encryptString('x');
$cipher = Crypt::encryptJson(['a'=>1]);
$data   = Crypt::decryptJson($cipher);
$mac    = Crypt::hmac('data', 'context');        // SHA-256
$sig    = Crypt::sign('payload');                // Ed25519 یا RSA
$ok     = Crypt::verify('payload', $sig, $publicKey);
$key    = Crypt::deriveKey('master', 'context', length: 32);   // HKDF
```

## ۳. قالب رشتهٔ رمزنگاری‌شده
```
v2:base64(iv):base64(ciphertext):base64(tag)
│   │           │                  └─ GCM auth tag (16 bytes)
│   │           └─ ciphertext
│   └─ IV (12 bytes برای GCM، تصادفی برای هر رمزنگاری)
└─ نسخهٔ کلید (برای rotation)
```
🔒 در JSON mode: `{"v":2,"iv":"…","ct":"…","tag":"…"}`.

## ۴. الگوریتم
```php
final class Encrypter implements EncrypterInterface
{
    public function __construct(
        private readonly KeyRing $keys,          // نسخه → کلید
        private readonly string $cipher = 'aes-256-gcm',
    ) {}

    public function encrypt(string $plaintext, string $aad = ''): string
    {
        $version = $this->keys->currentVersion();
        $key     = $this->keys->key($version);

        $iv = \random_bytes(12);                 // 🔒 GCM IV = 12 bytes
        $tag = '';
        $ciphertext = \openssl_encrypt(
            $plaintext, $this->cipher, $key,
            OPENSSL_RAW_DATA, $iv, $tag, $aad, 16,
        );
        if ($ciphertext === false) {
            throw new EncryptionException(messageId: 'crypt.encrypt_failed',
                context: ['error' => \openssl_error_string() ?: 'unknown']);
        }
        return \sprintf('v%d:%s:%s:%s', $version,
            \base64_encode($iv), \base64_encode($ciphertext), \base64_encode($tag));
    }

    public function decrypt(string $payload, string $aad = ''): string
    {
        $parts = \explode(':', $payload);
        if (\count($parts) !== 4 || !\str_starts_with($parts[0], 'v')) {
            throw new DecryptException(messageId: 'crypt.invalid_format');
        }
        $version = (int) \substr($parts[0], 1);
        $key = $this->keys->key($version);        // ممکن است کلید قدیمی باشد (برای rotation)
        if ($key === null) {
            throw new KeyMissingException(messageId: 'crypt.key_missing', context: ['version' => $version]);
        }

        $iv = \base64_decode($parts[1], true);
        $ct = \base64_decode($parts[2], true);
        $tag= \base64_decode($parts[3], true);
        if ($iv === false || $ct === false || $tag === false || \strlen($tag) !== 16) {
            throw new DecryptException(messageId: 'crypt.invalid_payload');
        }

        $plain = \openssl_decrypt($ct, $this->cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        if ($plain === false) {
            throw new DecryptException(messageId: 'crypt.decrypt_failed');   // 🔒 tamper detected
        }
        return $plain;
    }
}
```

### ۴٫۱ HKDF (بدون ext خاص)
```php
public function deriveKey(string $ikm, string $info, int $length = 32, string $salt = ''): string
{
    if (\function_exists('hash_hkdf')) {
        return \hash_hkdf('sha256', $ikm, $length, $info, $salt);
    }
    // fallback: HKDF دستی (RFC 5869)
    $salt = $salt !== '' ? $salt : \str_repeat("\0", 32);
    $prk  = \hash_hmac('sha256', $ikm, $salt, true);
    $okm  = ''; $t = ''; $i = 1;
    while (\strlen($okm) < $length) {
        $t = \hash_hmac('sha256', $t . $info . \chr($i), $prk, true);
        $okm .= $t; $i++;
    }
    return \substr($okm, 0, $length);
}
```

## ۵. Key Management و Rotation
```php
final class KeyRing
{
    /** @var array<int, string> version → raw key */
    private array $keys;
    private int $current;

    public function __construct(string $configKey)   // "base64:....,base64:...."
    {
        $versions = \explode(',', $configKey);
        foreach ($versions as $i => $v) {
            $this->keys[$i + 1] = $this->decode($v);
        }
        $this->current = \array_key_last($this->keys);   // آخرین = جاری
    }
    public function currentVersion(): int { return $this->current; }
    public function key(int $version): ?string { return $this->keys[$version] ?? null; }
}
```
🔒 رمزنگاری همیشه با **جدیدترین** کلید؛ رمزگشایی با کلید **همان نسخه** (در payload).
🔒 Rotation = افزودن کلید جدید به انتها؛ دادهٔ قدیمی با کلید قدیمی خوانده می‌شود.
🔒 `tvn key:rotate` → کلید جدید + بازرمزنگاری تدریجی داده‌ها در پس‌زمینه.

## ۶. APP_KEY
```
APP_KEY=base64:<44 char base64 of 32 random bytes>
```
```php
// tvn key:generate
$bytes = \random_bytes(32);
$key = 'base64:' . \base64_encode($bytes);
```
🔒 در `.env`، هرگز در سورس. 🔒 اگر در PROD نبود → `KeyMissingException` در بوت.

## ۷. Column-Level Encryption (برای PII + Blind Index)
```php
// ORM cast: 'encrypted'
// برای جست‌وجو روی فیلد رمزنگاری‌شده:
final class BlindIndex
{
    public static function compute(string $plaintext, string $field): string
    {
        // HMAC با کلید جدا → ایندکس قابل جست‌وجو بدون نشت مقدار
        return \hash_hmac('sha256', $field . '|' . $plaintext, $this->blindKey);
    }
}
// schema: mobile_enc (ciphertext), mobile_idx (blind index, searchable)
```

## ۸. امضا (Signature)
```php
// Ed25519 (با sodium یا openssl 8.1+)
$keypair = Crypt::generateSignatureKeyPair();     // private + public
$sig = Crypt::sign($payload, $privateKey);
$ok = Crypt::verify($payload, $sig, $publicKey);

// RSA (fallback اگر sodium نبود)
openssl_sign / openssl_verify با OPENSSL_ALGO_SHA256
```
🔒 برای امضای پلاگین، Webhook، JWT (RS256/EdDSA).

## ۹. امنیت
| تهدید | کنترل |
|---|---|
| IV Reuse | IV تصادفی ۱۲ بایت برای هر رمزنگاری |
| Tampering | GCM auth tag + بررسی در decrypt |
| Key نشت | فقط ENV، `#[\SensitiveParameter]`، mask در لاگ |
| Padding Oracle | GCM (بدون padding) |
| Downgrade | نسخهٔ کلید پین‌شده، `alg` ثابت |
| Weak Key | ۳۲ بایت تصادفی با `random_bytes` |
| Timing | `hash_equals` برای MAC/tag |

🔒 **هرگز** AES-CBC بدون HMAC. فقط GCM (AEAD).
🔒 **هرگز** `MCRYPT_*` (حذف‌شده از PHP 7.2).

## ۱۰. کارایی
| عملیات | هدف |
|---|---|
| encrypt ۱KB | ≤ ۲۰µs |
| decrypt ۱KB | ≤ ۲۰µs |
| HKDF derive | ≤ ۵۰µs |
| HMAC SHA-256 | ≤ ۵µs |

## ۱۱. تست
| ID | سناریو |
|---|---|
| T-01 | encrypt/decrypt roundtrip |
| T-02 | tamper در ciphertext → `DecryptException` |
| T-03 | IV منحصر برای دو encrypt یکسان |
| T-04 | Rotation: decrypt با کلید قدیمی |
| T-05 | Test Vectors استاندارد AES-GCM |
| T-06 | HKDF در برابر RFC 5869 vectors |
| T-07 | AAD mismatch → fail |
| T-08 | BlindIndex deterministic |
| T-09 | Ed25519 sign/verify |

## ۱۲. معیار پذیرش 🧪
- AC-CRYPT-01..09 (§11).
- AC-CRYPT-10: Test Vectors رسمی (NIST) پاس شود.
- AC-CRYPT-11: بدون `MCRYPT`/`AES-CBC` در کد.

## ۱۳. مراجع
| منبع | تفاوت |
|---|---|
| Laravel `encrypter` | افزودن KeyRing/Rotation داخلی، HKDF، BlindIndex، Ed25519 |
| `defuse/php-encryption` | بدون وابستگی، openssl-only |
| NIST SP 800-38D (GCM) | Test Vectors |
| RFC 5869 (HKDF) | — |

## ۱۴. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.crypt.v1
engine:
  id: tavaan.engine.crypt
  namespace: Tavaan\Engine\Crypt
  path: engines/crypt
  priority: P0
  phase: M4
  depends_on: [config]
  extensions: {required: [openssl], optional: [{name: sodium, fallback: openssl}]}
  facade: {name: Crypt, accessor: encrypter}
  tables: []
  config_file: config/app.php (key)
  public_api:
    - class: Encrypter
      methods: [encrypt, decrypt, encryptString, encryptJson, decryptJson, hmac, deriveKey]
    - class: Signer
      methods: [sign, verify, generateKeyPair]
    - class: BlindIndex
      methods: [compute, verify]
  loc_estimate: 600
  acceptance:
    - {id: AC-CRYPT-10, test: 'engines/crypt/tests/Unit/TestVectorsTest.php'}
```
