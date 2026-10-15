# DOC-074 — موتور Media | آپلود و پردازش رسانه

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-074 |
| Engine ID | `tavaan.engine.media` |
| Namespace | `Tavaan\Engine\Media` |
| اولویت | **P1** — فاز M5 |
| وابستگی | `filesystem`, `crypt`, `validation` |
| Facade | `Media` |
| افزونه | `gd`/`imagick` (اختیاری + fallback)، `fileinfo`(اختیاری) |
| LOC | ۱۵۰۰ |

## ۱. هدف
آپلود امن فایل، پردازش تصویر (Resize/Crop/Watermark/WebP/AVIF)، تولید Thumbnail،
مدیریت Media Library و Metadata (EXIF) — با fallback وقتی `gd`/`imagick` نیست.

## ۲. جریان آپلود امن
```
UploadedFile
  │
  ├─ 1. اعتبارسنجی: size, extension (allowlist)
  ├─ 2. MIME واقعی: MagicBytes (نه content-type کاربر) 🔒
  ├─ 3. تطبیق extension ↔ MIME (جلوگیری از .php با image/*)
  ├─ 4. Rename با random (حذف نام اصلی) 🔒
  ├─ 5. ذخیره خارج از DocumentRoot (یا public با execution off)
  ├─ 6. پردازش تصویر (در صورت نیاز)
  └─ 7. ثبت در Media Library + hash (dedup)
```

## ۳. API
```php
// آپلود
$media = Media::upload($request->file('image'), disk: 'public', folder: 'products');
$media->url(); $media->path(); $media->size(); $media->mimeType();

// پردازش تصویر
$img = Image::make($path);
$img->resize(800, 600, keepAspect: true);
$img->crop(200, 200, x: 10, y: 10);
$img->thumbnail(150, 150);
$img->watermark($logoPath, position: 'bottom-right', opacity: 50);
$img->convert('webp', quality: 85);
$img->rotate(90); $img->flip();
$img->save($newPath);

// چند نسخه (Responsive)
Media::upload($file)->variants([
    'thumb'  => ['w'=>150,'h'=>150,'fit'=>'crop'],
    'medium' => ['w'=>600],
    'large'  => ['w'=>1200],
    'webp'   => ['format'=>'webp','quality'=>80],
]);

// EXIF / Metadata
$img->exif();          // اگر ext-exif یا پارس خودنوشته
$img->dimensions();
```

## ۴. پردازش تصویر — Driver انتخابی
```php
final class ImageManager
{
    public function __construct(private readonly string $driver = 'auto') {}

    public function driver(): string
    {
        return match ($this->driver) {
            'imagick' => 'imagick',
            'gd' => 'gd',
            'auto' => match (true) {
                \extension_loaded('imagick') => 'imagick',
                \extension_loaded('gd') => 'gd',
                default => 'none',       // 🔒 بدون پردازش، فقط ذخیره
            },
        };
    }

    public function resize(string $path, int $w, int $h, bool $keepAspect = true): void
    {
        $driver = $this->driver();
        if ($driver === 'none') {
            throw new EngineCapabilityException(
                messageId: 'engine.capability_missing',
                context: ['feature'=>'image.resize'],
                hint: 'برای پردازش تصویر، ext-gd یا ext-imagick نصب کنید.',
            );
        }
        // dispatch به GdDriver یا ImagickDriver
    }
}
```

### ۴٫۱ WebP/AVIF Conversion
```php
// GD
\imagewebp($image, $path, quality: 85);
\imageavif($image, $path, quality: 70);   // PHP 8.1+

// Imagick
$im->setImageFormat('webp');
$im->setImageCompressionQuality(85);
```
🔒 اگر `imagewebp` نبود → fallback به JPEG (با هشدار).

## ۵. Magic Bytes MIME Detection
→ از موتور `compat` (§۴٫۳). 🔒 هرگز به `$_FILES['type']` (که کاربر کنترل می‌کند) اعتماد نکن.

```php
final class MimeValidator
{
    private const array IMAGE_MIMES = ['image/jpeg','image/png','image/gif','image/webp','image/avif','image/bmp'];

    public function validate(UploadedFile $file, array $allowedMimes): void
    {
        $realMime = $this->detectRealMime($file->getRealPath());   // magic bytes
        if (!\in_array($realMime, $allowedMimes, true)) {
            throw new InvalidFileTypeException(
                messageId: 'media.mime_mismatch',
                context: ['detected'=>$realMime, 'allowed'=>$allowedMimes, 'name'=>$file->getClientOriginalName()],
            );
        }
        // 🔒 تطبیق extension با MIME
        $ext = \strtolower($file->getClientOriginalExtension());
        $expectedExts = $this->extensionsFor($realMime);
        if (!\in_array($ext, $expectedExts, true)) {
            throw new InvalidFileTypeException(messageId: 'media.extension_mismatch');
        }
    }
}
```

## ۶. Media Library (جدول)
```sql
CREATE TABLE tvn_media (
  id CHAR(26) PRIMARY KEY,
  disk VARCHAR(64) NOT NULL,
  path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255),
  mime_type VARCHAR(100) NOT NULL,
  size BIGINT UNSIGNED NOT NULL,
  width INT NULL, height INT NULL,
  hash CHAR(64) NOT NULL,          -- SHA-256 برای dedup
  mediable_type VARCHAR(190) NULL,
  mediable_id VARCHAR(64) NULL,
  variants JSON NULL,              -- {thumb:{path,w,h}, …}
  meta JSON NULL,                  -- EXIF
  created_at DATETIME(3) NOT NULL,
  KEY idx_mediable (mediable_type, mediable_id),
  KEY idx_hash (hash)
) ENGINE=InnoDB;
```

## ۷. امنیت
| تهدید | کنترل |
|---|---|
| آپلود shell (`.php`) | extension allowlist + MIME واقعی + ذخیره خارج از web |
| Path Traversal | rename با random + filesystem sandbox |
| MIME Spoofing | magic bytes |
| Image Bomb (decompression) | محدودیت ابعاد (max 20000x20000) + حافظه |
| EXIF نشت (GPS) | حذف EXIF در پردازش (اختیاری) |
| Polyglot file | بررسی ساختار (JPEG با PHP داخل) |

🔒 فایل‌های آپلودی در `storage/app/public` (خارج از `public/` اجرایی) یا با `location` که PHP اجرا نکند.
🔒 `nginx`: در مسیر uploads، `location ~ \.php$ { deny all; }`.

## ۸. کارایی
| عملیات | هدف |
|---|---|
| MIME detection (magic) | ≤ ۱ms |
| Resize ۱۹۲۰×۱۰۸۰ → ۸۰۰ | ≤ ۱۰۰ms |
| WebP convert | ≤ ۱۵۰ms |
| Thumbnail generation | ≤ ۵۰ms |

## ۹. تست
| T-01 | آپلود تصویر معتبر |
| T-02 | `.php` با image MIME → رد |
| T-03 | magic bytes detection |
| T-04 | resize/crop/thumbnail (با gd) |
| T-05 | WebP/AVIF |
| T-06 | watermark |
| T-07 | image bomb → رد |
| T-08 | بدون gd → EngineCapabilityException |
| T-09 | dedup با hash |
| T-10 | EXIF |

## ۱۰. معیار پذیرش 🧪
- AC-MEDIA-01..10. AC-MEDIA-11: ۰ آپلود shell در تست‌های امنیتی.

## ۱۱. مراجع
| منبع | تفاوت |
|---|---|
| Intervention Image | API پردازش | خودنوشته، بدون وابستگی |
| Laravel Media Library | Media model | — |
| Flysystem | Abstraction | (در filesystem) |

## ۱۲. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.media.v1
engine:
  id: tavaan.engine.media
  namespace: Tavaan\Engine\Media
  path: engines/media
  priority: P1
  phase: M5
  depends_on: [filesystem, crypt, validation]
  extensions:
    optional: [{name: gd, fallback: none}, {name: imagick, fallback: gd}, {name: fileinfo, fallback: magic-bytes}, {name: exif, fallback: native-parser}]
  facade: {name: Media, accessor: media}
  tables: [tvn_media]
  config_file: config/media.php
  public_api:
    - class: MediaManager
      methods: [upload, fromPath, variants]
    - class: Image
      methods: [make, resize, crop, thumbnail, watermark, convert, rotate, flip, save, exif]
  loc_estimate: 1500
```
