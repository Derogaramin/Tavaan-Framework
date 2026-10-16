<?php

declare(strict_types=1);

namespace Tavaan\Engine\Media;

/**
 * تَوان | Tavaan — Media
 * Media — رکورد رسانهٔ ثبت‌شده در کتابخانه (DOC-074 §3/§6).
 *
 * @api 1.0
 * @see docs/03-engines/media.md
 */
final class Media
{
    /**
     * @param array<string, array{path: string, width?: int, height?: int, format?: string}> $variants
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $id,
        public readonly string $disk,
        public readonly string $path,
        public readonly ?string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly string $hash,
        public readonly ?string $mediableType = null,
        public readonly ?string $mediableId = null,
        public readonly array $variants = [],
        public readonly array $meta = [],
        public readonly ?string $createdAt = null,
        private readonly ?\Closure $urlResolver = null,
    ) {
    }

    /** URL عمومی روی disk. */
    public function url(): string
    {
        return $this->urlResolver !== null
            ? (string) ($this->urlResolver)($this->path)
            : '/storage/' . $this->path;
    }

    public function variantUrl(string $name): ?string
    {
        $variant = $this->variants[$name] ?? null;
        if (!\is_array($variant) || !isset($variant['path'])) {
            return null;
        }

        return $this->urlResolver !== null
            ? (string) ($this->urlResolver)((string) $variant['path'])
            : '/storage/' . $variant['path'];
    }

    public function isImage(): bool
    {
        return \str_starts_with($this->mimeType, 'image/');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'disk' => $this->disk,
            'path' => $this->path,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'hash' => $this->hash,
            'mediable_type' => $this->mediableType,
            'mediable_id' => $this->mediableId,
            'variants' => $this->variants,
            'meta' => $this->meta,
            'created_at' => $this->createdAt,
        ];
    }
}
