<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config\Exceptions;

use Tavaan\Core\Exceptions\Core\ConfigException;

/**
 * TCF002 — تلاش برای تغییر Config در حالت Frozen (PROD).
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class ConfigFrozenException extends ConfigException
{
    public static function for(string $key): self
    {
        return new self(
            messageId: 'config.frozen',
            context: ['key' => $key],
            hint: 'در production پیکربندی فقط‌خواندنی است؛ مقدار را در config/ یا .env تغییر دهید.',
        );
    }
}
