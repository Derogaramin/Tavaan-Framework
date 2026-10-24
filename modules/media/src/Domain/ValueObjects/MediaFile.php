<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول رسانه
 * MediaFile — DTO ماژولی یک قلم رسانه (بدون وابستگی به موتور).
 */

namespace Modules\Media\Domain\ValueObjects;

/**
 * MediaFile — تصویر/فایل رسانه با URL و variantهایش.
 *
 * @api 1.0
 * @see modules/media/README.md
 */
final class MediaFile
{
    /**
     * @param array<string, string> $variants نام variant → URL
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $mimeType = '',
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly array $variants = [],
    ) {
    }

    public function variantUrl(string $name): ?string
    {
        return $this->variants[$name] ?? null;
    }

    public function isImage(): bool
    {
        return \str_starts_with($this->mimeType, 'image/');
    }
}
