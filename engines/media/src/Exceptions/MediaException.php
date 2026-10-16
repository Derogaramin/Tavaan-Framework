<?php

declare(strict_types=1);

namespace Tavaan\Engine\Media\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * تَوان | Tavaan — Media
 * MediaException — خطاهای موتور رسانه (DOC-074).
 *
 * @api 1.0
 * @see docs/03-engines/media.md
 */
class MediaException extends TavaanException
{
    public static function tooLarge(int $size, int $max): static
    {
        return new static('media.too_large', ['size' => $size, 'max' => $max]);
    }

    public static function extensionNotAllowed(string $extension): static
    {
        return new static('media.extension_not_allowed', ['extension' => $extension]);
    }

    public static function unreadable(string $path): static
    {
        return new static('media.unreadable', ['path' => $path]);
    }

    public static function storeFailed(string $path, string $reason = ''): static
    {
        return new static('media.store_failed', ['path' => $path, 'reason' => $reason]);
    }
}
