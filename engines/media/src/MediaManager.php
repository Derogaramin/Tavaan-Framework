<?php

declare(strict_types=1);

namespace Tavaan\Engine\Media;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Engine\Filesystem\FilesystemManager;
use Tavaan\Engine\Media\Exceptions\MediaException;
use Tavaan\Support\Time\Clock;
use Tavaan\Support\Ulid;

/**
 * تَوان | Tavaan — Media
 * MediaManager — آپلود امن + کتابخانهٔ رسانه (Facade: Media؛ DOC-074 §2/§3).
 *
 * جریان آپلود امن (DOC-074 §2):
 * ```
 * 1. اعتبارسنجی: size, extension (allowlist)
 * 2. MIME واقعی: MagicBytes (نه content-type کاربر) 🔒
 * 3. تطبیق extension ↔ MIME (جلوگیری از .php با image/*)
 * 4. Rename با random (حذف نام اصلی) 🔒
 * 5. ذخیره روی disk (خارج از DocumentRoot اجرایی)
 * 6. پردازش تصویر (در صورت نیاز)
 * 7. ثبت در Media Library + hash (dedup)
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/media.md
 */
final class MediaManager
{
    private readonly MimeValidator $validator;

    private readonly string $table;

    public function __construct(
        private readonly ?ConfigRepository $config = null,
        private readonly ?ContainerInterface $container = null,
        private readonly ?FilesystemManager $filesystem = null,
        private readonly ?ConnectionInterface $db = null,
        private readonly ?ImageManager $images = null,
    ) {
        $this->validator = new MimeValidator();
        $this->table = (string) ($config?->get('media.table', 'tvn_media') ?? 'tvn_media');
    }

    // ─── آپلود (DOC-074 §3) ───────────────────────────────────────────────

    /**
     * آپلود فایل (UploadedFile موتور http یا هر شیء با path()/originalName()).
     */
    public function upload(object $file, ?string $disk = null, string $folder = ''): Media
    {
        $path = $this->fileLocalPath($file);
        $originalName = \method_exists($file, 'originalName')
            ? (string) $file->originalName()
            : \basename($path);

        return $this->fromPath($path, $disk, $folder, $originalName);
    }

    /**
     * ثبت یک فایل محلی موجود در کتابخانهٔ رسانه.
     */
    public function fromPath(string $path, ?string $disk = null, string $folder = '', ?string $originalName = null): Media
    {
        if (!\is_file($path) || !\is_readable($path)) {
            throw MediaException::unreadable($path);
        }

        $originalName ??= \basename($path);
        $disk ??= (string) ($this->config?->get('media.disk', 'public') ?? 'public');

        // 1) اندازه
        $size = (int) \filesize($path);
        $maxSize = (int) ($this->config?->get('media.max_size', 10485760) ?? 10485760);
        if ($size > $maxSize) {
            throw MediaException::tooLarge($size, $maxSize);
        }

        // 1b) extension allowlist
        $extension = \strtolower(\pathinfo($originalName, \PATHINFO_EXTENSION));
        $allowedExtensions = (array) ($this->config?->get('media.allowed_extensions', []) ?? []);
        if ($allowedExtensions !== [] && !\in_array($extension, \array_map(strval(...), $allowedExtensions), true)) {
            throw MediaException::extensionNotAllowed($extension);
        }

        // 2+3) 🔒 MIME واقعی با magic bytes + تطبیق extension
        $allowedMimes = \array_values(\array_map(strval(...), (array) ($this->config?->get('media.allowed_mimes', []) ?? [])));
        $realMime = $this->validator->validate($path, $extension, $allowedMimes, $originalName);

        $content = (string) \file_get_contents($path);

        // 🔒 polyglot: کد PHP جاسازی‌شده (وقتی re-encode ممکن نیست حیاتی است)
        if ((bool) ($this->config?->get('media.paranoid_scan', true) ?? true)) {
            $this->validator->scanForEmbeddedCode($content, $originalName);
        }

        // 🔒 Image Bomb: ابعاد از هدر (بدون decode)
        $isImage = $this->validator->isImage($realMime);
        $maxDimension = (int) ($this->config?->get('media.image.max_dimension', 20000) ?? 20000);
        if ($isImage) {
            $this->validator->assertWithinDimensions($path, $maxDimension);
        }

        // dedup با SHA-256 (DOC-074 §6)
        $hash = \hash('sha256', $content);
        if ((bool) ($this->config?->get('media.dedup', true) ?? true)) {
            $existing = $this->findByHash($hash);
            if ($existing !== null) {
                return $existing;
            }
        }

        // 4) 🔒 rename با random — نام اصلی هرگز ذخیره نمی‌شود
        $safeExtension = $this->validator->extensionForMime($realMime);
        $name = \bin2hex(\random_bytes(16)) . '.' . $safeExtension;
        $folder = \trim($folder !== '' ? $folder : (string) ($this->config?->get('media.folder', 'media') ?? 'media'), '/');
        $target = ($folder !== '' ? $folder . '/' : '') . $name;

        // پردازش/re-encode تصویر (در صورت درایور موجود)
        [$width, $height] = $isImage ? ($this->validator->imageDimensions($path) ?? [null, null]) : [null, null];
        if ($isImage && (bool) ($this->config?->get('media.image.reencode', true) ?? true)) {
            $content = $this->reencodeImage($path, $content, $safeExtension);
        }

        // 5) ذخیره روی disk
        $fs = $this->disk($disk);
        if (!$fs->put($target, $content)) {
            throw MediaException::storeFailed($target);
        }

        // meta (EXIF خلاصه — فقط کلیدهای غیرحساس در صورت strip)
        $meta = [];
        if ($isImage) {
            $meta = $this->safeExif($path);
        }

        // 7) ثبت در کتابخانه
        $id = (string) Ulid::generate();
        $createdAt = Clock::now()->format('Y-m-d H:i:s.v');

        $this->insertRecord([
            'id' => $id,
            'disk' => $disk,
            'path' => $target,
            'original_name' => $this->sanitizeOriginalName($originalName),
            'mime_type' => $realMime,
            'size' => \strlen($content),
            'width' => $width,
            'height' => $height,
            'hash' => $hash,
            'mediable_type' => null,
            'mediable_id' => null,
            'variants' => null,
            'meta' => $meta === [] ? null : \json_encode($meta, \JSON_UNESCAPED_UNICODE),
            'created_at' => $createdAt,
        ]);

        return $this->makeMedia([
            'id' => $id,
            'disk' => $disk,
            'path' => $target,
            'original_name' => $this->sanitizeOriginalName($originalName),
            'mime_type' => $realMime,
            'size' => \strlen($content),
            'width' => $width,
            'height' => $height,
            'hash' => $hash,
            'variants' => [],
            'meta' => $meta,
            'created_at' => $createdAt,
        ]);
    }

    // ─── Variants (چند نسخهٔ responsive — DOC-074 §3) ─────────────────────

    /**
     * تولید نسخه‌های مشتق (thumb/medium/large/webp…).
     *
     * تعریف: `['thumb' => ['w'=>150,'h'=>150,'fit'=>'crop'], 'webp' => ['format'=>'webp','quality'=>80]]`
     *
     * @param array<string, array{w?: int, h?: int, fit?: string, format?: string, quality?: int}> $definitions
     *
     * @return array<string, string> variant → path
     */
    public function variants(Media $media, array $definitions): array
    {
        if (!$media->isImage()) {
            throw MediaException::storeFailed($media->path, 'variants فقط برای تصویر');
        }

        $fs = $this->disk($media->disk);
        $content = $fs->get($media->path);
        if ($content === null) {
            throw MediaException::unreadable($media->path);
        }

        $tmpDir = \sys_get_temp_dir() . '/tvn-media-' . \bin2hex(\random_bytes(4));
        \mkdir($tmpDir, 0700, true);
        $source = $tmpDir . '/source';
        \file_put_contents($source, $content);

        $results = [];
        $records = $media->variants;

        try {
            foreach ($definitions as $name => $definition) {
                $format = (string) ($definition['format'] ?? \pathinfo($media->path, \PATHINFO_EXTENSION) ?: 'jpg');
                $quality = (int) ($definition['quality'] ?? $this->config?->get('media.image.quality', 85) ?? 85);

                $image = Image::make($source);

                $fit = (string) ($definition['fit'] ?? 'contain');
                $w = (int) ($definition['w'] ?? 0);
                $h = (int) ($definition['h'] ?? $w);

                if ($w > 0 && $h > 0 && $fit === 'crop') {
                    $image->thumbnail($w, $h);
                } elseif ($w > 0) {
                    $image->resize($w, $h > 0 ? $h : $w, keepAspect: true);
                }

                if ($format !== '') {
                    $image->convert($format, $quality);
                }

                $variantName = \pathinfo($media->path, \PATHINFO_FILENAME) . '-' . $name . '.' . $format;
                $variantPath = \trim(\dirname($media->path), '.') . '/' . $variantName;
                $variantPath = \str_replace('//', '/', \ltrim($variantPath, '/'));

                $localOut = $tmpDir . '/out-' . $name;
                $image->save($localOut);

                $variantContent = (string) \file_get_contents($localOut);
                $fs->put($variantPath, $variantContent);

                [$vw, $vh] = $this->validator->imageDimensions($localOut) ?? [null, null];

                $records[$name] = \array_filter([
                    'path' => $variantPath,
                    'width' => $vw,
                    'height' => $vh,
                    'format' => $format,
                ], static fn ($v): bool => $v !== null);
                $results[$name] = $variantPath;
            }
        } finally {
            $this->rrmdir($tmpDir);
        }

        // به‌روزرسانی رکورد کتابخانه
        $this->updateVariants($media->id, $records);

        return $results;
    }

    // ─── کتابخانه ─────────────────────────────────────────────────────────

    public function find(string $id): ?Media
    {
        if ($this->db === null) {
            return null;
        }

        $row = $this->db->table($this->table)->where('id', $id)->first();

        return $row === null ? null : $this->makeMedia($row);
    }

    public function findByHash(string $hash): ?Media
    {
        if ($this->db === null) {
            return null;
        }

        $row = $this->db->table($this->table)->where('hash', $hash)->orderBy('created_at')->first();

        return $row === null ? null : $this->makeMedia($row);
    }

    /** اتصال به موجودیت دیگر (product/user/…). */
    public function attach(Media $media, string $type, int|string $id): Media
    {
        $this->db?->table($this->table)
            ->where('id', $media->id)
            ->update(['mediable_type' => $type, 'mediable_id' => (string) $id]);

        return $this->find($media->id) ?? $media;
    }

    /** حذف فایل + رکورد. */
    public function delete(Media|string $media): bool
    {
        $media = $media instanceof Media ? $media : ($this->find($media) ?? throw MediaException::unreadable((string) $media));

        try {
            $fs = $this->disk($media->disk);
            $fs->delete($media->path);
            foreach ($media->variants as $variant) {
                if (\is_array($variant) && isset($variant['path'])) {
                    $fs->delete((string) $variant['path']);
                }
            }
        } catch (\Throwable) {
            // حذف فایل ناموفق — همچنان رکورد پاک می‌شود
        }

        $this->db?->table($this->table)->where('id', $media->id)->delete();

        return true;
    }

    // ─── داخلی ────────────────────────────────────────────────────────────

    private function disk(string $name): \Tavaan\Core\Contracts\Filesystem\FilesystemInterface
    {
        if ($this->filesystem === null) {
            throw MediaException::storeFailed('', 'موتور filesystem در دسترس نیست');
        }

        return $this->filesystem->disk($name);
    }

    /** مسیر محلی فایل آپلودشده (path() یا move به tmp). */
    private function fileLocalPath(object $file): string
    {
        if (\method_exists($file, 'path')) {
            $path = (string) $file->path();
            if ($path !== '' && \is_file($path)) {
                return $path;
            }
        }

        if (\method_exists($file, 'getRealPath')) {
            $path = (string) $file->getRealPath();
            if ($path !== '' && \is_file($path)) {
                return $path;
            }
        }

        if (\method_exists($file, 'move')) {
            $tmpDir = \sys_get_temp_dir() . '/tvn-upload-' . \bin2hex(\random_bytes(4));
            \mkdir($tmpDir, 0700, true);
            $moved = (string) $file->move($tmpDir);

            return $moved !== '' && \is_file($moved) ? $moved : $tmpDir . '/' . \basename($moved);
        }

        throw MediaException::unreadable($file::class);
    }

    /** re-encode با gd/imagick — 🔒 polyglot/EXIF پاک می‌شود؛ بدون درایور → محتوای خام. */
    private function reencodeImage(string $path, string $content, string $extension): string
    {
        $driver = $this->images?->driver() ?? Image::resolveDriver((string) ($this->config?->get('media.image.driver', 'auto') ?? 'auto'));
        if ($driver === 'none') {
            return $content;
        }

        $tmpDir = \sys_get_temp_dir() . '/tvn-reencode-' . \bin2hex(\random_bytes(4));
        \mkdir($tmpDir, 0700, true);

        try {
            $out = $tmpDir . '/re.' . $extension;
            $image = ($this->images ?? new ImageManager((string) ($this->config?->get('media.image.driver', 'auto') ?? 'auto')))->make($path);
            $image->quality((int) ($this->config?->get('media.image.quality', 85) ?? 85));
            $image->save($out);

            $reencoded = \file_get_contents($out);

            return $reencoded === false || $reencoded === '' ? $content : $reencoded;
        } catch (\Throwable) {
            return $content;   // degrade ایمن — فایل اصلی
        } finally {
            $this->rrmdir($tmpDir);
        }
    }

    /**
     * EXIF بدون دادهٔ حساس (DOC-074 §7: نشت GPS).
     *
     * @return array<string, mixed>
     */
    private function safeExif(string $path): array
    {
        if (!(bool) ($this->config?->get('media.image.strip_exif', true) ?? true)) {
            return [];
        }

        try {
            $exif = Image::make($path)->exif();
        } catch (\Throwable) {
            return [];
        }

        // 🔒 فقط ابعاد/فرمت — هرگز GPS/Device
        $safe = [];
        foreach (['ImageWidth', 'ImageLength', 'MimeType', 'Orientation'] as $key) {
            if (isset($exif[$key])) {
                $safe[$key] = $exif[$key];
            }
        }

        return $safe;
    }

    /** 🔒 نام اصلی sanitize می‌شود (فقط نمایشی — در path استفاده نمی‌شود). */
    private function sanitizeOriginalName(string $name): string
    {
        $name = \basename(\str_replace('\\', '/', $name));
        $name = (string) \preg_replace('/[\x00-\x1F\x7F\/]/', '_', $name);

        return \mb_substr($name, 0, 255);
    }

    /** @param array<string, mixed> $row */
    private function insertRecord(array $row): void
    {
        $this->db?->table($this->table)->insert($row);
    }

    /** @param array<string, array<string, mixed>> $variants */
    private function updateVariants(string $id, array $variants): void
    {
        $this->db?->table($this->table)
            ->where('id', $id)
            ->update(['variants' => \json_encode($variants, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)]);
    }

    /** @param array<string, mixed> $row */
    private function makeMedia(array $row): Media
    {
        // variants/meta می‌تواند آرایه (از حافظه) یا JSON (از DB) باشد
        $variants = $row['variants'] ?? [];
        if (\is_string($variants)) {
            $variants = \json_decode($variants, true);
        }
        $meta = $row['meta'] ?? [];
        if (\is_string($meta)) {
            $meta = \json_decode($meta, true);
        }

        return new Media(
            id: (string) ($row['id'] ?? ''),
            disk: (string) ($row['disk'] ?? ''),
            path: (string) ($row['path'] ?? ''),
            originalName: isset($row['original_name']) ? (string) $row['original_name'] : null,
            mimeType: (string) ($row['mime_type'] ?? ''),
            size: (int) ($row['size'] ?? 0),
            width: isset($row['width']) ? (int) $row['width'] : null,
            height: isset($row['height']) ? (int) $row['height'] : null,
            hash: (string) ($row['hash'] ?? ''),
            mediableType: isset($row['mediable_type']) ? (string) $row['mediable_type'] : null,
            mediableId: isset($row['mediable_id']) ? (string) $row['mediable_id'] : null,
            variants: \is_array($variants) ? $variants : [],
            meta: \is_array($meta) ? $meta : [],
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            urlResolver: $this->filesystem !== null
                ? fn (string $path): string => $this->filesystem->disk((string) ($row['disk'] ?? ''))->url($path)
                : null,
        );
    }

    private function rrmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        foreach (\scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            \is_dir($path) ? $this->rrmdir($path) : @\unlink($path);
        }
        @\rmdir($dir);
    }
}
