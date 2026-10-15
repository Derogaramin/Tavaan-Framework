<?php

declare(strict_types=1);

namespace Tavaan\Engine\Config\Exceptions;

use Tavaan\Core\Exceptions\Core\ConfigException;

/**
 * TCF005 — نقض Schema پیکربندی.
 *
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class SchemaValidationException extends ConfigException
{
    /** @param list<string> $violations */
    public static function for(string $namespace, array $violations): self
    {
        return new self(
            messageId: 'config.schema_invalid',
            context: ['namespace' => $namespace, 'violations' => $violations],
            hint: 'مقادیر config را با schema تطبیق دهید (config/schema/*.schema.php).',
        );
    }
}
