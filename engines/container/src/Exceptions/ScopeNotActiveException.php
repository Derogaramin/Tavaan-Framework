<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container\Exceptions;

use Tavaan\Core\Exceptions\Core\ContainerException;

/**
 * TCR005 — resolve سرویس Scoped بدون Scope فعال.
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final class ScopeNotActiveException extends ContainerException
{
    public static function for(string $abstract): self
    {
        return new self(
            messageId: 'container.scope_not_active',
            context: ['abstract' => $abstract],
            hint: 'قبل از resolve، beginScope() را فراخوانی کنید (مثلاً در Middleware شروع درخواست).',
        );
    }
}
