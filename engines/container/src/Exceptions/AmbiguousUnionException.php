<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container\Exceptions;

use Tavaan\Core\Exceptions\Core\ContainerException;

/**
 * TCR003 — Union Type با چند کاندیدای قابل‌ساخت (قطعیت نقض شده).
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final class AmbiguousUnionException extends ContainerException
{
    /** @param list<string> $candidates */
    public static function for(string $concrete, string $parameter, array $candidates): self
    {
        return new self(
            messageId: 'container.ambiguous_union',
            context: ['class' => $concrete, 'parameter' => $parameter, 'candidates' => $candidates],
            hint: 'با Contextual Binding (when/needs/give) یا ارسال صریح parameter، کاندیدا را قطعی کنید.',
        );
    }
}
