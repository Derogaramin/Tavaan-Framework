<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http;

use Tavaan\Core\Contracts\Http\UploadedFileInterface;
use Tavaan\Core\Exceptions\Filesystem\PathTraversalException;
use Tavaan\Engine\Compat\MagicBytesDetector;

/**
 * تَوان | Tavaan — Http
 * UploadedFile — فایل آپلود‌شده با انتقال امن (sandbox + realpath).
 *
 * @api 1.0
 * @see docs/03-engines/http.md
 */
final class UploadedFile implements UploadedFileInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $original,
        private readonly string $clientMimeType,
        private readonly int $size,
        private readonly int $error,
    ) {
    }

    public function name(): string
    {
        return \basename($this->path);
    }

    public function originalName(): string
    {
        return $this->original;
    }

    /** نام فایل امن (بدون path traversal؛ فقط کاراکترهای مجاز). */
    public function safeName(): string
    {
        $name = \pathinfo($this->original, PATHINFO_FILENAME);
        $name = (string) \preg_replace('/[^\p{L}\p{N}_\-. ]+/u', '', $name);

        return $name === '' ? 'file' : $name;
    }

    public function extension(): string
    {
        return \strtolower(\pathinfo($this->original, PATHINFO_EXTENSION) ?: '');
    }

    public function size(): int
    {
        return $this->size;
    }

    /** MIME واقعی از magic bytes (نه client-supplied). */
    public function mimeType(): string
    {
        return MagicBytesDetector::detectFromFile($this->path) ?? $this->clientMimeType;
    }

    public function clientMimeType(): string
    {
        return $this->clientMimeType;
    }

    public function error(): int
    {
        return $this->error;
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && \is_file($this->path);
    }

    /**
     * انتقال امن به مقصد.
     *
     * @throws PathTraversalException
     */
    public function move(string $directory, ?string $name = null): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException(\sprintf('Cannot move invalid uploaded file (error=%d).', $this->error));
        }

        $target = \rtrim($directory, '/');
        if (!\is_dir($target) && !\mkdir($target, 0775, true) && !\is_dir($target)) {
            throw new \RuntimeException(\sprintf('Unable to create directory: %s', $target));
        }

        $finalName = $name ?? $this->safeName() . '-' . \bin2hex(\random_bytes(4)) . ($this->extension() !== '' ? '.' . $this->extension() : '');

        // 🔒 sandbox: مقصد باید داخل directory بماند
        $full = $target . '/' . $finalName;
        $realDir = (string) \realpath($target);
        $normalized = (string) \realpath(\dirname($full));
        if (!\str_starts_with($normalized, $realDir)) {
            throw new PathTraversalException('filesystem.path_traversal', ['path' => $finalName]);
        }

        $moved = \is_uploaded_file($this->path)
            ? \move_uploaded_file($this->path, $full)
            : \rename($this->path, $full);   // حالت تست/CLI

        if (!$moved) {
            throw new \RuntimeException(\sprintf('Failed to move uploaded file to %s', $full));
        }

        \chmod($full, 0640);

        return $full;
    }

    public function path(): string
    {
        return $this->path;
    }
}
