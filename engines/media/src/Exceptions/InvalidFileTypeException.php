<?php

declare(strict_types=1);

namespace Tavaan\Engine\Media\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Media
 * InvalidFileTypeException — MIME/extension نامعتبر یا ناقض allowlist (DOC-074 §5).
 *
 * 🔒 این استثنا سپر اصلی «آپلود shell» است:
 * mime_mismatch (magic bytes)، extension_mismatch و polyglot.
 *
 * @api 1.0
 * @see docs/03-engines/media.md
 */
final class InvalidFileTypeException extends TavaanException
{
    /** @param array<string, mixed> $context */
    public function __construct(string $messageId = 'media.mime_mismatch', array $context = [], ?string $hint = null)
    {
        parent::__construct($messageId, $context, $hint ?? 'نوع فایل با allowlist رسانه مطابقت ندارد.');
    }

    /** @param list<string> $allowed */
    public static function mimeMismatch(string $detected, array $allowed, string $name = ''): self
    {
        return new self('media.mime_mismatch', [
            'detected' => $detected,
            'allowed' => $allowed,
            'name' => $name,
        ]);
    }

    public static function extensionMismatch(string $extension, string $detectedMime): self
    {
        return new self('media.extension_mismatch', [
            'extension' => $extension,
            'mime' => $detectedMime,
        ], 'extension اعلامی با MIME واقعی (magic bytes) نمی‌خواند.');
    }

    public static function polyglot(string $name): self
    {
        return new self('media.polyglot_detected', ['name' => $name], 'فایل حاوی کد اجرایی جاسازی‌شده است.');
    }

    public static function imageTooLarge(int $width, int $height, int $maxDimension): self
    {
        return new self('media.image_bomb', [
            'width' => $width,
            'height' => $height,
            'max' => $maxDimension,
        ], 'ابعاد تصویر از سقف مجاز گذشت (Image Bomb protection).');
    }
}
