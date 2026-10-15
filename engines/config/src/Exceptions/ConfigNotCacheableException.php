<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config\Exceptions;

use Tavaan\Core\Exceptions\Core\ConfigException;

/**
 * TCF003 — مقدار غیرقابل سریال‌سازی (Closure/resource) در Config.
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class ConfigNotCacheableException extends ConfigException
{
    public static function for(string $keyPath, string $type): self
    {
        return new self(
            messageId: 'config.not_cacheable',
            context: ['key' => $keyPath, 'type' => $type],
            hint: 'Closure/resource/object را از فایل‌های config حذف کنید (Config باید cache شود).',
        );
    }
}
