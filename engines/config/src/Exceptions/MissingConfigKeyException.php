<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config\Exceptions;

use Tavaan\Core\Exceptions\Core\ConfigException;

/**
 * TCF006 — کلید الزامی پیکربندی موجود نیست.
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class MissingConfigKeyException extends ConfigException
{
    /** @param list<string> $keys */
    public static function for(array $keys): self
    {
        return new self(
            messageId: 'config.missing_key',
            context: ['keys' => $keys],
            hint: 'کلیدهای الزامی را در config/ یا .env تعریف کنید.',
        );
    }
}
