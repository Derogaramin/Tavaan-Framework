<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config\Exceptions;

use Tavaan\Core\Exceptions\Core\ConfigException;

/**
 * TCF004 — خطای پارس فایل .env (کلید نامعتبر و …).
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class EnvParseException extends ConfigException
{
    public static function invalidKey(int $line, string $key): self
    {
        return new self(
            messageId: 'core.env.parse_failed',
            context: ['line' => $line, 'key' => \substr($key, 0, 40)],
            hint: 'کلید .env فقط می‌تواند [A-Z_][A-Z0-9_]* باشد.',
        );
    }
}
