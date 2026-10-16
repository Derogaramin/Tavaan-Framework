<?php

declare(strict_types=1);

namespace Tavaan\Engine\Media;

use Tavaan\Core\Exceptions\Engine\EngineCapabilityException;

/**
 * تَوان | Tavaan — Media
 * ImageManager — انتخاب درایور پردازش تصویر (imagick → gd → none؛ DOC-074 §4).
 *
 * @api 1.0
 * @see docs/03-engines/media.md
 */
final class ImageManager
{
    public function __construct(private readonly string $configured = 'auto')
    {
    }

    /** درایور مؤثر فعلی. */
    public function driver(): string
    {
        return Image::resolveDriver($this->configured);
    }

    public function make(string $path): Image
    {
        Image::configure($this->configured);

        return Image::make($path);
    }

    /**
     * resize مستقیم (DOC-074 §4) — بدون درایور → EngineCapabilityException.
     */
    public function resize(string $path, int $width, int $height, bool $keepAspect = true, ?string $out = null): void
    {
        $this->assertDriver('image.resize');
        $this->make($path)->resize($width, $height, $keepAspect)->save($out ?? $path);
    }

    public function thumbnail(string $path, int $width, int $height, ?string $out = null): void
    {
        $this->assertDriver('image.thumbnail');
        $this->make($path)->thumbnail($width, $height)->save($out ?? $path);
    }

    public function convert(string $path, string $format, int $quality = 85, ?string $out = null): void
    {
        $this->assertDriver('image.convert');
        $this->make($path)->convert($format, $quality)->save($out ?? $path);
    }

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
}
