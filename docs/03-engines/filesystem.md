# DOC-063 — موتور Filesystem | سامانهٔ فایل

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-063 |
| Engine ID | `tavaan.engine.filesystem` |
| Namespace | `Tavaan\Engine\Filesystem` |
| اولویت | **P1** — فاز M5 |
| وابستگی | `config`, `crypt`؛ نرم: `media` |
| Facade | `Storage` → `filesystem` |
| افزونه | `curl`(اختیاری برای S3)، `fileinfo`(اختیاری) |
| LOC | ۱۲۰۰ |

## ۱. هدف
انتزاع یکپارچهٔ فایل با درایورهای Local/S3-compatible/FTP، Disk چندگانه،
Stream Wrapper، Sandbox امن و عملیات اتمیک.

## ۲. درایورها
| درایور | توضیح |
|---|---|
| `local` | فایل‌سیستم محلی |
| `s3` | S3-compatible (AWS/Minio/ArvanCloud) با signing خودنوشته (بدون SDK) |
| `ftp` | FTP/FTPS با stream wrapper |
| `memory` | در حافظه (تست) |

## ۳. API
```php
Storage::disk('public')->put('avatars/1.jpg', $content);
Storage::get('file.txt');
Storage::exists('file.txt');
Storage::delete('file.txt');
Storage::copy($src, $dst); Storage::move($src, $dst);
Storage::size('file.txt'); Storage::lastModified('file.txt');
Storage::mimeType('file.jpg');
Storage::append('log.txt', $line);
Storage::prepend('log.txt', $line);
Storage::files('dir'); Storage::allFiles('dir');       // recursive
Storage::directories('dir'); Storage::allDirectories('dir');
Storage::makeDirectory('dir'); Storage::deleteDirectory('dir');
Storage::url('avatars/1.jpg');
Storage::temporaryUrl('private/file.pdf', now()->addMinutes(10));   // signed
Storage::download('file.pdf', 'name.pdf');
Storage::putFile('avatars', $uploadedFile);           // از UploadedFile
Storage::readStream('video.mp4'); Storage::writeStream('out.mp4', $stream);
Storage::visibility('file'); Storage::setVisibility('file', 'private');
```

## ۴. Disk Configuration
```php
// config/filesystem.php
return [
    'default' => env_string('TVN_FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local', 'root' => storage_path('app'),
            'visibility' => 'private', 'throw' => true,
        ],
        'public' => [
            'driver' => 'local', 'root' => storage_path('app/public'),
            'url' => env_string('APP_URL').'/storage', 'visibility' => 'public',
        ],
        'private' => [
            'driver' => 'local', 'root' => storage_path('app/private'),
            'visibility' => 'private',
        ],
        's3' => [
            'driver' => 's3', 'key' => env_string('AWS_KEY'), 'secret' => env_string('AWS_SECRET'),
            'region' => env_string('AWS_REGION'), 'bucket' => env_string('AWS_BUCKET'),
            'endpoint' => env_string('AWS_ENDPOINT'),   // برای S3-compatible
            'use_path_style' => true, 'visibility' => 'private',
        ],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],   // symlink
];
```

## ۵. امنیت — Path Sandbox 🔒
```php
final class LocalFilesystem implements FilesystemInterface
{
    public function __construct(private readonly string $root) {}

    private function path(string $relative): string
    {
        // 🔒 جلوگیری از Path Traversal
        $normalized = $this->normalize($relative);
        if (\str_contains($normalized, '..') || \str_starts_with($normalized, '/')
            || \str_contains($normalized, "\0")) {
            throw new PathTraversalException(
                messageId: 'security.path_traversal',
                context: ['path' => $relative],
            );
        }
        $full = $this->root . '/' . $normalized;
        $real = \realpath($full);

        // 🔒 پس از resolve symlink، باید داخل root باشد
        if ($real !== false && !\str_starts_with($real, \realpath($this->root))) {
            throw new PathTraversalException(messageId: 'security.path_escape', context: ['path' => $relative]);
        }
        return $full;
    }

    private function normalize(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $path = \preg_replace('#/+#', '/', $path) ?? $path;
        return \trim($path, '/');
    }
}
```

### ۵٫۱ Atomic Write
```php
public function put(string $path, string $content): bool
{
    $full = $this->path($path);
    $dir = \dirname($full);
    if (!\is_dir($dir)) { \mkdir($dir, 0775, true); }

    $tmp = $full . '.' . \bin2hex(\random_bytes(6)) . '.tmp';
    if (\file_put_contents($tmp, $content, LOCK_EX) === false) { return false; }
    \chmod($tmp, $this->permission);

    // 🔒 rename اتمیک است
    if (!\rename($tmp, $full)) { @\unlink($tmp); return false; }
    return true;
}
```

## ۶. S3 Signing (خودنوشته، بدون SDK)
```php
final class S3Adapter implements FilesystemInterface
{
    private function sign(string $method, string $path, array $headers, string $payloadHash): array
    {
        // AWS Signature Version 4
        $now = Clock::utcNow();
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');

        $canonicalRequest = $this->canonicalRequest($method, $path, $headers, $payloadHash);
        $scope = "{$dateStamp}/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . \hash('sha256', $canonicalRequest);

        $signingKey = $this->deriveKey($dateStamp);
        $signature = \hash_hmac('sha256', $stringToSign, $signingKey);

        $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$this->key}/{$scope}, "
            . "SignedHeaders=" . $this->signedHeaders($headers) . ", Signature={$signature}";
        $headers['x-amz-date'] = $amzDate;
        $headers['x-amz-content-sha256'] = $payloadHash;
        return $headers;
    }

    private function deriveKey(string $dateStamp): string
    {
        $k = \hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secret, true);
        $k = \hash_hmac('sha256', $this->region, $k, true);
        $k = \hash_hmac('sha256', 's3', $k, true);
        return \hash_hmac('sha256', 'aws4_request', $k, true);
    }
}
```

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| Path Traversal | `path()` با realpath + sandbox |
| Symlink Escape | بررسی realpath داخل root |
| Overwrite | permission + lock |
| S3 Credential نشت | فقط ENV، mask در لاگ |
| MIME Spoofing | magic bytes (موتور media) |
| Public access به private | visibility + signed URL |

## ۸. کارایی
| عملیات | هدف |
|---|---|
| put ۱MB (local) | ≤ ۵ms |
| get ۱MB | ≤ ۵ms |
| readStream (chunk) | حافظه ≤ ۲MB ثابت |
| S3 sign | ≤ ۰٫۵ms |

## ۹. تست
| T-01 | put/get/exists/delete در هر ۴ درایور |
| T-02 | Path traversal `../../etc/passwd` → رد |
| T-03 | Symlink escape → رد |
| T-04 | Atomic write (بدون partial) |
| T-05 | S3 signing با AWS test vectors |
| T-06 | readStream حافظه ثابت |
| T-07 | temporaryUrl امضاشده |
| T-08 | directories/files recursive |

## ۱۰. معیار پذیرش 🧪
- AC-FS-01..08. AC-FS-09: ۰ path traversal.

## ۱۱. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.filesystem.v1
engine:
  id: tavaan.engine.filesystem
  namespace: Tavaan\Engine\Filesystem
  path: engines/filesystem
  priority: P1
  phase: M5
  depends_on: [config, crypt]
  facade: {name: Storage, accessor: filesystem}
  drivers: [local, s3, ftp, memory]
  config_file: config/filesystem.php
  public_api:
    - class: FilesystemManager
      methods: [disk, get, put, exists, delete, copy, move, size, url, temporaryUrl,
                files, allFiles, directories, makeDirectory, deleteDirectory, readStream, writeStream]
  loc_estimate: 1200
```
