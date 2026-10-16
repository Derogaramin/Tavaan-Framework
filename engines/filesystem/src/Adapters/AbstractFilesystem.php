<?php

declare(strict_types=1);

namespace Tavaan\Engine\Filesystem\Adapters;

use Tavaan\Core\Contracts\Filesystem\FilesystemInterface;
use Tavaan\Core\Exceptions\Filesystem\FileNotFoundException;

/**
 * تَوان | Tavaan — Filesystem
 * AbstractFilesystem — پایهٔ مشترک درایورها (کم‌دوباره‌کدی؛ DOC-063).
 *
 * @api 1.0
 * @see docs/03-engines/filesystem.md
 */
abstract class AbstractFilesystem implements FilesystemInterface
{
    protected string $urlBase = '';

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    /** @throws FileNotFoundException */
    protected function fileNotFound(string $path): never
    {
        throw new FileNotFoundException('filesystem.file_not_found', ['path' => $path]);
    }

    /**
     * تشخیص MIME — finfo و در fallback از MagicBytes موتور compat (DOC-074 §5).
     */
    protected function detectMime(string $realPath, string $originalPath): string
    {
        if (\function_exists('finfo_open')) {
            $finfo = \finfo_open(\FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = \finfo_file($finfo, $realPath);
                \finfo_close($finfo);
                // 🔒 octet-stream یعنی finfo مطمئن نیست → magic bytes دقیق‌تر است
                if (\is_string($mime) && $mime !== '' && $mime !== 'application/octet-stream') {
                    return $mime;
                }
            }
        }

        // 🔒 fallback صفر‌وابستگی: magic bytes (موتور compat)
        if (\class_exists(\Tavaan\Engine\Compat\MagicBytesDetector::class)) {
            $detected = \Tavaan\Engine\Compat\MagicBytesDetector::detectFromFile($realPath);
            if ($detected !== null) {
                return $detected;
            }
        }

        return self::mimeFromExtension($originalPath);
    }

    /** نقشهٔ extension → MIME (بدون ابزار تشخیص). */
    protected static function mimeFromExtension(string $path): string
    {
        $ext = \strtolower(\pathinfo($path, \PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'txt', 'log' => 'text/plain',
            'html', 'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'csv' => 'text/csv',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            default => 'application/octet-stream',
        };
    }
}
