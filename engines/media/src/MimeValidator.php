<?php

declare(strict_types=1);

namespace Tavaan\Engine\Media;

use Tavaan\Engine\Compat\MagicBytesDetector;
use Tavaan\Engine\Media\Exceptions\InvalidFileTypeException;

/**
 * تَوان | Tavaan — Media
 * MimeValidator — تشخیص MIME واقعی با magic bytes و تطبیق extension (DOC-074 §5).
 *
 * 🔒 هرگز به `$_FILES['type']` (که کاربر کنترل می‌کند) اعتماد نمی‌شود.
 * ترتیب: magic bytes (موتور compat) → finfo → extension map.
 *
 * @api 1.0
 * @see docs/03-engines/media.md
 */
final class MimeValidator
{
    /** @var array<string, list<string>> mime → extensionهای مجاز */
    private const EXTENSION_MAP = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'image/bmp' => ['bmp'],
        'image/svg+xml' => ['svg'],
        'application/pdf' => ['pdf'],
        'application/zip' => ['zip'],
        'video/mp4' => ['mp4'],
        'audio/mpeg' => ['mp3'],
        'text/plain' => ['txt', 'log', 'csv'],
    ];

    /** @var list<string> MIMEهای تصویری */
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp'];

    /**
     * تشخیص MIME واقعی از magic bytes (بدون اعتماد به نام/هدر کاربر).
     */
    public function detectRealMime(string $path, ?string $hintName = null): ?string
    {
        if (\class_exists(MagicBytesDetector::class)) {
            $detected = MagicBytesDetector::detectFromFile($path);
            if ($detected !== null) {
                return $detected;
            }
        }

        if (\function_exists('finfo_open')) {
            $finfo = \finfo_open(\FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = \finfo_file($finfo, $path);
                \finfo_close($finfo);
                if (\is_string($mime) && $mime !== '' && $mime !== 'application/octet-stream') {
                    return $mime;
                }
            }
        }

        return null;
    }

    /**
     * اعتبارسنجی کامل: MIME واقعی در allowlist + تطبیق extension.
     *
     * @param list<string> $allowedMimes
     *
     * @return string MIME واقعی تشخیص‌داده‌شده
     *
     * @throws InvalidFileTypeException
     */
    public function validate(string $path, string $clientExtension, array $allowedMimes, string $clientName = ''): string
    {
        $realMime = $this->detectRealMime($path, $clientName);

        if ($realMime === null || !\in_array($realMime, $allowedMimes, true)) {
            throw InvalidFileTypeException::mimeMismatch($realMime ?? 'unknown', $allowedMimes, $clientName);
        }

        // 🔒 تطبیق extension با MIME — جلوگیری از `.php` با image/*
        $extension = \strtolower(\ltrim($clientExtension, '.'));
        $expected = self::EXTENSION_MAP[$realMime] ?? null;

        if ($expected !== null && $extension !== '' && !\in_array($extension, $expected, true)) {
            throw InvalidFileTypeException::extensionMismatch($extension, $realMime);
        }

        return $realMime;
    }

    /**
     * 🔒 بررسی polyglot — کد PHP جاسازی‌شده در فایل (DOC-074 §7).
     *
     * فقط وقتی re-encode ممکن نباشد (بدون gd/imagick) حیاتی است.
     */
    public function scanForEmbeddedCode(string $content, string $name = ''): void
    {
        $needles = ["<?php", "<?=", "<script language", "<?\n"];

        foreach ($needles as $needle) {
            if (\stripos($content, $needle) !== false) {
                throw InvalidFileTypeException::polyglot($name);
            }
        }
    }

    /**
     * 🔒 Image Bomb — بررسی ابعاد از هدر (بدون decode کامل؛ DOC-074 §7).
     *
     * @return array{0: int, 1: int}|null [width, height] یا null اگر تصویر نیست
     */
    public function imageDimensions(string $path): ?array
    {
        $info = @\getimagesize($path);
        if ($info === false || !isset($info[0], $info[1])) {
            return null;
        }

        return [(int) $info[0], (int) $info[1]];
    }

    public function assertWithinDimensions(string $path, int $maxDimension): void
    {
        $dimensions = $this->imageDimensions($path);
        if ($dimensions === null) {
            return;
        }

        [$width, $height] = $dimensions;
        if ($width > $maxDimension || $height > $maxDimension) {
            throw InvalidFileTypeException::imageTooLarge($width, $height, $maxDimension);
        }
    }

    /** extension امن از روی MIME واقعی (نه نام کاربر). */
    public function extensionForMime(string $mime): string
    {
        return (self::EXTENSION_MAP[$mime] ?? ['bin'])[0];
    }

    public function isImage(string $mime): bool
    {
        return \in_array($mime, self::IMAGE_MIMES, true);
    }
}
