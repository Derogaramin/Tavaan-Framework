<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container\Exceptions;

use Tavaan\Core\Exceptions\Core\ContainerException;

/**
 * TCR002 — وابستگی چرخه‌ای در Auto-Wiring.
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final class CircularDependencyException extends ContainerException
{
    /** @param list<string> $path مسیر کامل چرخه (A → B → C → A) */
    public static function detected(array $path): self
    {
        return new self(
            messageId: 'container.circular_dependency',
            context: ['path' => $path],
            hint: 'یکی از وابستگی‌ها را با Interface + Lazy Proxy بشکنید یا از Setter Injection استفاده کنید.',
            docsUrl: 'docs/02-architecture/container-di.md#circular',
        );
    }
}
