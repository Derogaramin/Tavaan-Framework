<?php

declare(strict_types=1);

namespace Tavaan\Engine\Media;

use Tavaan\Core\Exceptions\Engine\EngineCapabilityException;

/**
 * تَوان | Tavaan — Media
 * Image — پردازش تصویر fluent با درایور انتخابی gd/imagick (DOC-074 §3/§4).
 *
 * ```php
 * $img = Image::make($path);
 * $img->resize(800, 600, keepAspect: true);
 * $img->crop(200, 200, x: 10, y: 10);
 * $img->thumbnail(150, 150);
 * $img->watermark($logoPath, position: 'bottom-right', opacity: 50);
 * $img->convert('webp', quality: 85);
 * $img->rotate(90); $img->flip();
 * $img->save($newPath);
 * ```
 *
 * 🔒 بدون gd/imagick → عملیات پیکسلی با EngineCapabilityException رد می‌شود
 * (driver = none)؛ اما exif()/dimensions() همیشه کار می‌کنند
 * (getimagesize/exif_read_data هستهٔ PHP هستند).
 * 🔒 re-encode با gd/imagick عمداً EXIF را حذف می‌کند (نشت GPS — DOC-074 §7).
 *
 * @api 1.0
 * @see docs/03-engines/media.md
 */
final class Image
{
    /** درایور پیش‌فرض سراسری (از config media.image.driver) */
    private static string $configuredDriver = 'auto';

    private ?string $targetFormat = null;

    private int $quality = 85;

    /** @var list<array{0: string, 1: array<int, mixed>}> عملیات صف‌شده (اجرا در save) */
    private array $operations = [];

    private function __construct(private readonly string $path)
    {
        if (!\is_file($path) || !\is_readable($path)) {
            throw Exceptions\MediaException::unreadable($path);
        }
    }

    /** تنظیم درایور پیش‌فرض (auto|gd|imagick|none) — توسط Provider. */
    public static function configure(string $driver): void
    {
        self::$configuredDriver = $driver;
    }

    public static function make(string $path): self
    {
        return new self($path);
    }

    /** درایور مؤثر: imagick|gd|none (DOC-074 §4). */
    public function driver(): string
    {
        return self::resolveDriver(self::$configuredDriver);
    }

    public static function resolveDriver(string $configured): string
    {
        return match ($configured) {
            'imagick' => \extension_loaded('imagick') ? 'imagick' : 'none',
            'gd' => \extension_loaded('gd') ? 'gd' : 'none',
            'none' => 'none',
            default => match (true) {   // auto
                \extension_loaded('imagick') => 'imagick',
                \extension_loaded('gd') => 'gd',
                default => 'none',      // 🔒 بدون پردازش، فقط ذخیره
            },
        };
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return array{0: int, 1: int} */
    public function dimensions(): array
    {
        $info = @\getimagesize($this->path);
        if ($info === false || !isset($info[0], $info[1])) {
            return [0, 0];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    public function width(): int
    {
        return $this->dimensions()[0];
    }

    public function height(): int
    {
        return $this->dimensions()[1];
    }

    /**
     * EXIF — با ext-exif (در صورت وجود)؛ بدون آن آرایهٔ خالی.
     *
     * @return array<string, mixed>
     */
    public function exif(): array
    {
        if (!\function_exists('exif_read_data')) {
            return [];
        }

        $mime = (new MimeValidator())->detectRealMime($this->path);
        if (!\in_array($mime, ['image/jpeg', 'image/tiff'], true)) {
            return [];      // exif_read_data فقط JPEG/TIFF
        }

        $data = @\exif_read_data($this->path);

        return \is_array($data) ? $data : [];
    }

    // ─── عملیات (queued تا save) ──────────────────────────────────────────

    public function resize(int $width, int $height, bool $keepAspect = true): self
    {
        $this->assertDriver('image.resize');
        $this->operations[] = ['resize', [$width, $height, $keepAspect]];

        return $this;
    }

    public function crop(int $width, int $height, int $x = 0, int $y = 0): self
    {
        $this->assertDriver('image.crop');
        $this->operations[] = ['crop', [$width, $height, $x, $y]];

        return $this;
    }

    /** بندانگشتی cover — scale + center-crop به ابعاد دقیق. */
    public function thumbnail(int $width, int $height): self
    {
        $this->assertDriver('image.thumbnail');
        $this->operations[] = ['thumbnail', [$width, $height]];

        return $this;
    }

    public function watermark(string $logoPath, string $position = 'bottom-right', int $opacity = 50): self
    {
        $this->assertDriver('image.watermark');
        $this->operations[] = ['watermark', [$logoPath, $position, $opacity]];

        return $this;
    }

    /** تبدیل فرمت: jpeg|png|webp|avif (DOC-074 §4.1). */
    public function convert(string $format, int $quality = 85): self
    {
        $this->assertDriver('image.convert');
        $this->targetFormat = \strtolower($format);
        $this->quality = $quality;
        $this->operations[] = ['convert', [$this->targetFormat, $quality]];

        return $this;
    }

    public function rotate(float $degrees): self
    {
        $this->assertDriver('image.rotate');
        $this->operations[] = ['rotate', [$degrees]];

        return $this;
    }

    /** @param string $mode horizontal|vertical|both */
    public function flip(string $mode = 'both'): self
    {
        $this->assertDriver('image.flip');
        $this->operations[] = ['flip', [$mode]];

        return $this;
    }

    /** کیفیت ذخیره (بدون convert). */
    public function quality(int $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    /**
     * اجرا و ذخیره — بدون عملیات = کپی/re-encode ساده.
     *
     * 🔒 وقتی درایور none است و عملیاتی صف شده → EngineCapabilityException.
     */
    public function save(?string $path = null): self
    {
        $target = $path ?? $this->path;
        $driver = $this->driver();

        if ($driver === 'none') {
            if ($this->operations !== []) {
                $this->assertDriver('image.save');
            }
            // «فقط ذخیره» — کپی بدون پردازش (DOC-074 §4)
            if ($target !== $this->path && !\copy($this->path, $target)) {
                throw Exceptions\MediaException::storeFailed($target, 'copy failed');
            }

            return $this;
        }

        $dir = \dirname($target);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        if ($driver === 'imagick') {
            $this->executeImagick($target);
        } else {
            $this->executeGd($target);
        }

        return $this;
    }

    // ─── 🔒 بررسی قابلیت ──────────────────────────────────────────────────

    private function assertDriver(string $feature): void
    {
        if ($this->driver() === 'none') {
            throw new EngineCapabilityException(
                'engine.capability_missing',
                ['feature' => $feature],
                'برای پردازش تصویر، ext-gd یا ext-imagick نصب کنید.',
            );
        }
    }

    // ─── اجرای GD ─────────────────────────────────────────────────────────

    private function executeGd(string $target): void
    {
        $raw = \file_get_contents($this->path);
        if ($raw === false) {
            throw Exceptions\MediaException::unreadable($this->path);
        }

        $image = \imagecreatefromstring($raw);
        if ($image === false) {
            throw Exceptions\MediaException::unreadable($this->path . ' (not an image)');
        }

        try {
            foreach ($this->operations as [$op, $args]) {
                $image = match ($op) {
                    'resize' => $this->gdResize($image, (int) $args[0], (int) $args[1], (bool) $args[2]),
                    'crop' => $this->gdCrop($image, (int) $args[0], (int) $args[1], (int) $args[2], (int) $args[3]),
                    'thumbnail' => $this->gdThumbnail($image, (int) $args[0], (int) $args[1]),
                    'watermark' => $this->gdWatermark($image, (string) $args[0], (string) $args[1], (int) $args[2]),
                    'rotate' => $this->gdRotate($image, (float) $args[0]),
                    'flip' => $this->gdFlip($image, (string) $args[0]),
                    'convert' => $image,   // فرمت در save اعمال می‌شود
                    default => $image,
                };
            }

            $format = $this->targetFormat ?? $this->guessFormat($target);
            $this->gdSave($image, $target, $format);
        } finally {
            \imagedestroy($image);
        }
    }

    /** @return \GdImage */
    private function gdResize(\GdImage $image, int $width, int $height, bool $keepAspect): \GdImage
    {
        $cw = \imagesx($image);
        $ch = \imagesy($image);

        if ($keepAspect && $cw > 0 && $ch > 0) {
            $ratio = \min($width / $cw, $height / $ch);
            $width = \max(1, (int) \round($cw * $ratio));
            $height = \max(1, (int) \round($ch * $ratio));
        }

        $target = $this->gdCreate($width, $height);
        \imagecopyresampled($target, $image, 0, 0, 0, 0, $width, $height, $cw, $ch);

        return $target;
    }

    /** @return \GdImage */
    private function gdCrop(\GdImage $image, int $width, int $height, int $x, int $y): \GdImage
    {
        $cropped = \imagecrop($image, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);

        return $cropped === false ? $image : $cropped;
    }

    /** @return \GdImage */
    private function gdThumbnail(\GdImage $image, int $width, int $height): \GdImage
    {
        $cw = \imagesx($image);
        $ch = \imagesy($image);
        $scale = \max($width / \max(1, $cw), $height / \max(1, $ch));   // cover

        $scaledW = \max(1, (int) \round($cw * $scale));
        $scaledH = \max(1, (int) \round($ch * $scale));

        $scaled = $this->gdCreate($scaledW, $scaledH);
        \imagecopyresampled($scaled, $image, 0, 0, 0, 0, $scaledW, $scaledH, $cw, $ch);

        $x = (int) \max(0, ($scaledW - $width) / 2);
        $y = (int) \max(0, ($scaledH - $height) / 2);
        $cropped = \imagecrop($scaled, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        \imagedestroy($scaled);

        return $cropped === false ? $image : $cropped;
    }

    /** @return \GdImage */
    private function gdWatermark(\GdImage $image, string $logoPath, string $position, int $opacity): \GdImage
    {
        $logoRaw = \file_get_contents($logoPath);
        if ($logoRaw === false) {
            return $image;
        }
        $logo = \imagecreatefromstring($logoRaw);
        if ($logo === false) {
            return $image;
        }

        $cw = \imagesx($image);
        $ch = \imagesy($image);
        $lw = \imagesx($logo);
        $lh = \imagesy($logo);
        $margin = 10;

        [$x, $y] = match ($position) {
            'top-left' => [$margin, $margin],
            'top-right' => [$cw - $lw - $margin, $margin],
            'bottom-left' => [$margin, $ch - $lh - $margin],
            'center' => [(int) (($cw - $lw) / 2), (int) (($ch - $lh) / 2)],
            default => [$cw - $lw - $margin, $ch - $lh - $margin],   // bottom-right
        };

        \imagecopymerge($image, $logo, \max(0, $x), \max(0, $y), 0, 0, $lw, $lh, \max(0, \min(100, $opacity)));
        \imagedestroy($logo);

        return $image;
    }

    /** @return \GdImage */
    private function gdRotate(\GdImage $image, float $degrees): \GdImage
    {
        $rotated = \imagerotate($image, $degrees, 0);

        return $rotated === false ? $image : $rotated;
    }

    /** @return \GdImage */
    private function gdFlip(\GdImage $image, string $mode): \GdImage
    {
        $flag = match ($mode) {
            'horizontal' => \IMG_FLIP_HORIZONTAL,
            'vertical' => \IMG_FLIP_VERTICAL,
            default => \IMG_FLIP_BOTH,
        };
        \imageflip($image, $flag);

        return $image;
    }

    /** @return \GdImage */
    private function gdCreate(int $width, int $height): \GdImage
    {
        $target = \imagecreatetruecolor(\max(1, $width), \max(1, $height));
        if ($target === false) {
            throw Exceptions\MediaException::storeFailed('', 'imagecreatetruecolor failed');
        }

        // حفظ alpha برای png/webp
        \imagealphablending($target, false);
        \imagesavealpha($target, true);
        \imagefill($target, 0, 0, \imagecolorallocatealpha($target, 0, 0, 0, 127) ?: 0);
        \imagealphablending($target, true);

        return $target;
    }

    /** @param \GdImage $image */
    private function gdSave($image, string $target, string $format): void
    {
        $dir = \dirname($target);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0775, true);
        }

        $ok = match ($format) {
            'png' => \imagepng($image, $target),
            'gif' => \imagegif($image, $target),
            'webp' => \function_exists('imagewebp') ? \imagewebp($image, $target, $this->quality) : \imagejpeg($image, $this->fixExtension($target, 'jpg'), $this->quality),
            'avif' => \function_exists('imageavif') ? \imageavif($image, $target, $this->quality) : \imagejpeg($image, $this->fixExtension($target, 'jpg'), $this->quality),
            default => \imagejpeg($image, $target, $this->quality),   // jpeg
        };

        // 🔒 fallback وبپ/اویف (DOC-074 §4.1): اگر تابع نبود → JPEG (بالا انجام شد)
        if (!$ok) {
            throw Exceptions\MediaException::storeFailed($target, 'gd save failed');
        }

        @\chmod($target, 0664);
    }

    private function fixExtension(string $path, string $ext): string
    {
        return \preg_replace('/\.[^.\/]+$/', '', $path) . '.' . $ext;
    }

    private function guessFormat(string $target): string
    {
        return \strtolower(\pathinfo($target, \PATHINFO_EXTENSION) ?: 'jpg');
    }

    // ─── اجرای Imagick ────────────────────────────────────────────────────

    private function executeImagick(string $target): void
    {
        if (!\class_exists(\Imagick::class)) {
            throw new EngineCapabilityException('engine.capability_missing', ['feature' => 'image.imagick']);
        }

        $image = new \Imagick($this->path);

        try {
            foreach ($this->operations as [$op, $args]) {
                match ($op) {
                    'resize' => $image->resizeImage((int) $args[0], (int) $args[1], \Imagick::FILTER_LANCZOS, 1, (bool) $args[2]),
                    'crop' => $this->imagickCrop($image, (int) $args[0], (int) $args[1], (int) $args[2], (int) $args[3]),
                    'thumbnail' => $image->cropThumbnailImage((int) $args[0], (int) $args[1]),
                    'watermark' => $this->imagickWatermark($image, (string) $args[0], (string) $args[1], (int) $args[2]),
                    'rotate' => $image->rotateImage(new \ImagickPixel('transparent'), (float) $args[0]),
                    'flip' => match ((string) $args[0]) {
                        'horizontal' => $image->flopImage(),
                        'vertical' => $image->flipImage(),
                        default => [$image->flipImage(), $image->flopImage()],
                    },
                    'convert' => $this->imagickConvert($image, (string) $args[0], (int) $args[1]),
                    default => true,
                };
            }

            $format = $this->targetFormat ?? $this->guessFormat($target);
            if ($format === 'jpg') {
                $format = 'jpeg';
            }
            $image->setImageFormat($format);

            $dir = \dirname($target);
            if (!\is_dir($dir)) {
                \mkdir($dir, 0775, true);
            }
            $image->writeImage($target);
            @\chmod($target, 0664);
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function imagickCrop(\Imagick $image, int $width, int $height, int $x, int $y): void
    {
        $image->cropImage($width, $height, $x, $y);
        $image->setImagePage($width, $height, 0, 0);
    }

    private function imagickWatermark(\Imagick $image, string $logoPath, string $position, int $opacity): void
    {
        $logo = new \Imagick($logoPath);
        $logo->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity / 100, \Imagick::CHANNEL_ALPHA);

        $cw = $image->getImageWidth();
        $ch = $image->getImageHeight();
        $lw = $logo->getImageWidth();
        $lh = $logo->getImageHeight();
        $margin = 10;

        [$x, $y] = match ($position) {
            'top-left' => [$margin, $margin],
            'top-right' => [$cw - $lw - $margin, $margin],
            'bottom-left' => [$margin, $ch - $lh - $margin],
            'center' => [(int) (($cw - $lw) / 2), (int) (($ch - $lh) / 2)],
            default => [$cw - $lw - $margin, $ch - $lh - $margin],
        };

        $image->compositeImage($logo, \Imagick::COMPOSITE_OVER, \max(0, $x), \max(0, $y));
        $logo->clear();
        $logo->destroy();
    }

    private function imagickConvert(\Imagick $image, string $format, int $quality): void
    {
        $image->setImageFormat($format === 'jpg' ? 'jpeg' : $format);
        $image->setImageCompressionQuality($quality);
    }
}
