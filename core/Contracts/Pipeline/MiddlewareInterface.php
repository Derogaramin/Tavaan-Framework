<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Pipeline;

/**
 * قرارداد Pipe/Middleware عمومی (برای HTTP/Queue/Console).
 *
 * terminate() اختیاری است — Pipeline با method_exists بررسی می‌کند.
 *
 * @api 1.0
 * @see docs/03-engines/pipeline.md
 */
interface MiddlewareInterface
{
    /**
     * @template TPassable
     * @param TPassable                    $passable
     * @param \Closure(TPassable): mixed   $next
     */
    public function handle(mixed $passable, \Closure $next, string ...$params): mixed;
}
