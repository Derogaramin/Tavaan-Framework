# DOC-083E — موتور Compat | سازگاری و Polyfill

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-083E |
| Engine ID | `tavaan.engine.compat` |
| Namespace | `Tavaan\Engine\Compat` |
| اولویت | **P0** — فاز M1 (زیرساخت همهٔ موتورها) |
| وابستگی | هیچ |
| LOC | ۴۰۰ |

---

## ۱. هدف

فراهم‌کردن **پیاده‌سازی خالص PHP** برای افزونه‌ها و توابعی که ممکن است موجود نباشند،
تا اصل «خودکفایی» (P1) حفظ شود و موتورهای دیگر بدون `if (extension_loaded)` پراکنده بنویسند.

> 🔒 قاعده: کد موتور باید `Compat::xxx()` را صدا بزند؛ تشخیص و Fallback در این موتور متمرکز است.

---

## ۲. فهرست Polyfillها

| تابع/قابلیت | افزونه | Fallback خالص | موتور مصرف‌کننده |
|---|---|---|---|
| `mb_str_split`, `mb_substr`, `mb_strlen`, `mb_strtolower/upper`, `mb_convert_case` | mbstring | جدول Unicode ساده + `iconv` | همه |
| `mb_str_pad`, `mb_str_split_utf8` | — | پیاده‌سازی UTF-8 | Str |
| `bcadd/bcsub/bcmul/bcdiv/bcmod/bccomp/bcscale` | bcmath | `Decimal` (Long Arithmetic) | Support\Math |
| `random_bytes`, `random_int` | random (built-in 7+) | اگر نبود → `openssl_random_pseudo_bytes` | Crypt |
| `hash_hkdf` | hash (7.1.2+) | HMAC-based HKDF دستی | Crypt |
| `sodium_crypto_*` | sodium | openssl EVP | Crypt |
| `zip open/add/extract` | zip | `ZipArchivePure` (Store + Deflate با `gzdeflate`) | Backup, Report |
| `gzencode/gzdeflate/gzcompress` | zlib | بدون فشرده‌سازی (Store) | Http, Backup |
| `curl_*` | curl | `fopen` Stream Wrapper با `stream_context` | HttpClient |
| `gd imagecreate*` | gd | بدون پردازش؛ فقط metadata | Media |
| `imagick` | imagick | gd → بدون پردازش | Media |
| `intl IntlDateFormatter/NumberFormatter/Transliterator` | intl | `Jalali`, `PersianNumber`, `PluralRules` خودنوشته | I18n |
| `finfo_*` | fileinfo | Magic Bytes Table (۱۲۰ امضا) | Media, Filesystem |
| `exif_read_data` | exif | پارس JPEG APP1 خودنوشته | Media |
| `apcu_*` | apcu | file/array | Cache |
| `dom/xmlwriter` | dom | HTML/Text Converter ساده | Mail, Report |
| `pcntl_signal/fork` | pcntl | بدون Signal → polling | Queue, Console |
| `posix_getpid/getuid` | posix | `getmypid()`, `getmyuid()` | Console |
| `opcache_*` | opcache | no-op | Optimizer |
| `socket_*` | sockets | `stream_socket_server` | Realtime |
| `json_validate` (8.3) | — | `json_decode` + بررسی خطا | Validation, Api |
| `array_find/any/all` (8.4) | — | `foreach` | Support\Arr |
| `mb_trim/mb_ltrim/mb_rtrim` (8.4) | — | regex UTF-8 | Support\Str |
| `#[\Override]` (8.3) | — | Attribute تعریف‌شده ولی no-op در 8.2 | همه |
| Property Hooks (8.4) | — | getter/setter متد | همه |
| `fpow`, `str_increment` (8.3/8.4) | — | پیاده‌سازی | Support |

---

## ۳. API

```php
use Tavaan\Engine\Compat\Compat;

Compat::has('redis');                    // bool
Compat::hasAny(['imagick', 'gd']);       // bool
Compat::require('gd', 'image.resize');   // throws EngineCapabilityException
Compat::report();                        // array<string, array{required, loaded, fallback}>
Compat::driver('image');                 // 'imagick'|'gd'|'none'
Compat::driver('zip');                   // 'zip'|'pure'|'none'
Compat::driver('http');                  // 'curl'|'stream'
Compat::driver('math');                  // 'bcmath'|'gmp'|'decimal'
Compat::driver('unicode');               // 'intl'|'mbstring'|'native'
```

### ۳٫۱ تابع‌های Polyfill سراسری
```php
// engines/compat/src/functions.php  — با function_exists محافظت‌شده
namespace Tavaan\Engine\Compat;

if (!\function_exists(__NAMESPACE__ . '\\bcadd')) {
    function bcadd(string $a, string $b, ?int $scale = null): string
    {
        return \function_exists('bcadd')
            ? \bcadd($a, $b, $scale ?? Decimal::defaultScale())
            : Decimal::of($a)->add(Decimal::of($b))->rescale($scale)->toString();
    }
}
```
🔒 این توابع **فقط** در namespace `Tavaan\Engine\Compat` تعریف می‌شوند (نه global) تا
با توابع واقعی تداخل نکنند. موتورهای دیگر با `use function Tavaan\Engine\Compat\bcadd;` استفاده می‌کنند.

---

## ۴. پیاده‌سازی‌های کلیدی

### ۴٫۱ UTF-8 بدون mbstring
```php
final class Utf8
{
    public static function length(string $s): int
    {
        if (\function_exists('mb_strlen')) { return \mb_strlen($s, 'UTF-8'); }
        return \preg_match_all('/./us', $s) ?: 0;
    }

    public static function substr(string $s, int $start, ?int $length = null): string
    {
        if (\function_exists('mb_substr')) { return \mb_substr($s, $start, $length, 'UTF-8'); }
        $pattern = $length === null
            ? '/^.{' . $start . '}(.*)$/us'
            : '/^.{' . $start . '}(.{0,' . $length . '})/us';
        return \preg_match($pattern, $s, $m) === 1 ? $m[1] : '';
    }

    /** @return list<string> */
    public static function split(string $s): array
    {
        if (\function_exists('mb_str_split')) { return \mb_str_split($s, 1, 'UTF-8'); }
        \preg_match_all('/./us', $s, $m);
        return $m[0];
    }

    public static function lower(string $s): string
    {
        if (\function_exists('mb_strtolower')) { return \mb_strtolower($s, 'UTF-8'); }
        return \strtr($s, self::UPPER_MAP);     // جدول نگاشت لاتین + فارسی/عربی
    }

    public static function isValid(string $s): bool
    {
        return \mb_check_encoding($s, 'UTF-8')
            || \preg_match('!!u', $s) !== false;
    }

    public static function normalize(string $s, string $form = 'NFC'): string
    {
        if (\class_exists(\Normalizer::class)) { return \Normalizer::normalize($s, constant("Normalizer::FORM_" . $form)) ?: $s; }
        return $s;      // ⚠️ بدون intl، نرمال‌سازی کامل ممکن نیست — مستند شود
    }
}
```

### ۴٫۲ ZIP خالص PHP (بدون ext-zip)
```php
final class ZipWriterPure
{
    /** @var list<array{name:string, data:string, crc:int, size:int, offset:int}> */
    private array $entries = [];
    private string $buffer = '';

    public function addFromString(string $name, string $data, int $level = 6): void
    {
        $compressed = \function_exists('gzdeflate') && $level > 0
            ? \gzdeflate($data, $level)
            : $data;
        $method = $compressed !== $data && \strlen($compressed) < \strlen($data) ? 8 : 0;   // 8=Deflate, 0=Store
        $final  = $method === 8 ? $compressed : $data;

        $offset = \strlen($this->buffer);
        $crc    = \crc32($data);
        $size   = \strlen($data);
        $csize  = \strlen($final);

        // Local File Header
        $this->buffer .= "PK\x03\x04"
            . \pack('v', 20) . \pack('v', 0) . \pack('v', $method)
            . \pack('v', 0) . \pack('v', 0)
            . \pack('V', $crc) . \pack('V', $csize) . \pack('V', $size)
            . \pack('v', \strlen($name)) . \pack('v', 0)
            . $name . $final;

        $this->entries[] = ['name' => $name, 'crc' => $crc, 'size' => $size,
                            'csize' => $csize, 'method' => $method, 'offset' => $offset];
    }

    public function addFile(string $path, ?string $name = null): void
    {
        $this->addFromString($name ?? \basename($path), (string) \file_get_contents($path));
    }

    public function output(): string
    {
        $cd = '';
        foreach ($this->entries as $e) {
            $cd .= "PK\x01\x02" . \pack('v', 20) . \pack('v', 20) . \pack('v', 0)
                 . \pack('v', $e['method']) . \pack('v', 0) . \pack('v', 0)
                 . \pack('V', $e['crc']) . \pack('V', $e['csize']) . \pack('V', $e['size'])
                 . \pack('v', \strlen($e['name'])) . \pack('v', 0) . \pack('v', 0)
                 . \pack('v', 0) . \pack('v', 0) . \pack('V', 0)
                 . \pack('V', $e['offset']) . $e['name'];
        }
        $cdOffset = \strlen($this->buffer);
        $cdSize   = \strlen($cd);

        return $this->buffer . $cd . "PK\x05\x06"
             . \pack('v', 0) . \pack('v', 0)
             . \pack('v', \count($this->entries)) . \pack('v', \count($this->entries))
             . \pack('V', $cdSize) . \pack('V', $cdOffset) . \pack('v', 0);
    }

    public function saveTo(string $path): void
    {
        Filesystem::atomicWrite($path, $this->output());
    }
}
```
🔒 تست با بازکردن ZIP تولیدشده توسط `ext-zip` و مقایسهٔ CRC.

### ۴٫۳ finfo بدون ext-fileinfo (Magic Bytes)
```php
final class MagicBytesDetector
{
    private const array SIGNATURES = [
        'image/jpeg'      => [["\xFF\xD8\xFF", 0]],
        'image/png'       => [["\x89PNG\r\n\x1A\n", 0]],
        'image/gif'       => [["GIF87a", 0], ["GIF89a", 0]],
        'image/webp'      => [["RIFF", 0], ["WEBP", 8]],
        'image/avif'      => [["ftypavif", 4], ["ftypmif1", 4]]],
        'image/bmp'       => [["BM", 0]],
        'image/svg+xml'   => [["<svg", 0, 'textual']],
        'application/pdf' => [["%PDF-", 0]],
        'application/zip' => [["PK\x03\x04", 0], ["PK\x05\x06", 0]],
        'application/gzip'=> [["\x1F\x8B", 0]],
        'application/x-rar' => [["Rar!\x1A\x07\x00", 0], ["Rar!\x1A\x07\x01\x00", 0]],
        'application/x-7z-compressed' => [["7z\xBC\xAF\x27\x1C", 0]],
        'video/mp4'       => [["ftypisom", 4], ["ftypmp42", 4]]],
        'audio/mpeg'      => [["ID3", 0], ["\xFF\xFB", 0]],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [["PK\x03\x04", 0]],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [["PK\x03\x04", 0]],
        'text/plain'      => [[null, 0, 'textual']],
    ];

    /** @param string $head ۶۴ بایت اول فایل */
    public static function detect(string $head, ?string $hintName = null): ?string
    {
        foreach (self::SIGNATURES as $mime => $sigs) {
            foreach ($sigs as [$needle, $offset, $kind]) {
                if ($needle === null) { continue; }
                if (($kind ?? '') === 'textual') {
                    if (\str_contains(\strtolower($head), \strtolower($needle))) { return $mime; }
                    continue;
                }
                if (\substr($head, $offset, \strlen($needle)) === $needle) { return $mime; }
            }
        }
        // text detection
        if (\preg_match('//u', $head) === 1 && \preg_match('/[^\x09\x0A\x0D\x20-\x7E\x80-\xBF\xC0-\xFF]/', $head) === 0) {
            return 'text/plain';
        }
        return null;
    }
}
```
🔒 تفاوت مهم: برای `xlsx`/`docx`/`zip` امضا یکسان است → باید داخل ZIP با `ZipReaderPure` بررسی شود
(`[Content_Types].xml`, `word/`, `xl/`).

### ۴٫۴ curl → stream fallback
```php
final class StreamHttpTransport implements HttpTransportInterface
{
    public function send(RawHttpRequest $req): RawHttpResponse
    {
        $context = \stream_context_create([
            'http' => [
                'method'           => $req->method,
                'header'           => $req->headersToString(),
                'content'          => $req->body,
                'timeout'          => $req->timeout,
                'follow_location'  => 0,                      // 🔒 خودمان redirect را کنترل می‌کنیم (SSRF)
                'ignore_errors'    => true,                   // body خطاها هم لازم است
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer'      => $req->verifyTls,
                'verify_peer_name' => $req->verifyTls,
                'cafile'           => $req->caBundle,
                'ciphers'          => 'ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384',
                'crypto_method'    => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            ],
        ]);

        $body = @\file_get_contents($req->url, false, $context);
        if ($body === false && !isset($http_response_header)) {
            throw new HttpTransportException(messageId: 'http_client.transport_failed');
        }
        return RawHttpResponse::fromPhpHeaders($http_response_header ?? [], (string) $body);
    }
}
```

---

## ۵. پیکربندی
```php
// engines/compat/config/compat.php
return [
    'force_fallbacks' => env_bool('TVN_COMPAT_FORCE_FALLBACK', false),   // برای تست fallbackها
    'drivers' => [
        'math'    => env_string('TVN_MATH_DRIVER', 'auto'),     // auto|bcmath|gmp|decimal
        'image'   => env_string('TVN_IMAGE_DRIVER', 'auto'),    // auto|imagick|gd|none
        'zip'     => env_string('TVN_ZIP_DRIVER', 'auto'),      // auto|zip|pure
        'http'    => env_string('TVN_HTTP_DRIVER', 'auto'),     // auto|curl|stream
        'unicode' => env_string('TVN_UNICODE_DRIVER', 'auto'),  // auto|intl|mbstring|native
        'mime'    => env_string('TVN_MIME_DRIVER', 'auto'),     // auto|fileinfo|magic
    ],
    'warn_on_fallback' => env_bool('TVN_COMPAT_WARN', true),
];
```

---

## ۶. تست
| ID | سناریو |
|---|---|
| T-01 | با `force_fallbacks=true` همهٔ توابع نتیجهٔ یکسان با ext می‌دهند |
| T-02 | `Decimal` در برابر `bcmath` روی ۱۰۰۰۰ حالت تصادفی (Property-Based) |
| T-03 | `Utf8::length/substr/split` روی متن فارسی/اموجی/چینشی |
| T-04 | `ZipWriterPure` → فایل تولیدی با `unzip -t` معتبر است |
| T-05 | `MagicBytesDetector` روی ۱۲۰ فایل نمونه |
| T-06 | `StreamHttpTransport` در برابر `CurlHttpTransport` (Mock Server) |
| T-07 | `Compat::report()` همهٔ افزونه‌های فهرست‌شده را پوشش می‌دهد |
| T-08 | نبود `intl` → `Jalali` نتیجهٔ یکسان می‌دهد |

---

## ۷. معیار پذیرش 🧪
- AC-CMP-01: پروژه روی یک PHP **minimal** (فقط `pdo`, `openssl`, `json`, `hash`, `mbstring`) اجرا شود.
- AC-CMP-02: با `force_fallbacks=true` همهٔ تست‌های دیگر پاس شوند.
- AC-CMP-03: `tvn doctor` گزارشی از افزونه‌ها + fallback فعال بدهد.
- AC-CMP-04: `Decimal` بدون bcmath، دقت ۱۸ رقم اعشار را در ضرب/تقسیم حفظ کند.
- AC-CMP-05: ZIP خالص با `unzip`/`7z`/`ext-zip` قابل باز شدن باشد.

---

## ۱۸. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.compat.v1
engine:
  id: tavaan.engine.compat
  namespace: Tavaan\Engine\Compat
  path: engines/compat
  priority: P0
  phase: M1
  depends_on: []
  facade: null
  tables: []
  config_file: engines/compat/config/compat.php
  public_api:
    - class: Compat
      methods: [has, hasAny, require, report, driver, version]
    - class: Utf8
      methods: [length, substr, split, lower, upper, isValid, normalize, pad, ucfirst]
    - class: ZipWriterPure
      methods: [addFromString, addFile, addDirectory, output, saveTo]
    - class: MagicBytesDetector
      methods: [detect, detectFromFile, detectFromStream]
  loc_estimate: 400
  acceptance:
    - {id: AC-CMP-01, test: 'engines/compat/tests/Integration/MinimalPhpTest.php'}
    - {id: AC-CMP-05, test: 'engines/compat/tests/Unit/ZipWriterPureTest.php'}
```
