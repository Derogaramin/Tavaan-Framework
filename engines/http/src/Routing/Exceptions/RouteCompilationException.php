<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * خطای کامپایل روت (regex ناامن/طولانی — ReDoS guard).
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class RouteCompilationException extends TavaanException
{
    public static function regexTooLong(string $param): self
    {
        return new self('router.regex.too_long', ['param' => $param], 'طول regex حداکثر ۲۰۰ کاراکتر.');
    }

    public static function potentialRedos(string $regex): self
    {
        return new self('router.regex.potential_redos', ['regex' => \substr($regex, 0, 100)], 'quantifier تودرتو (مانند (a+)+) ممنوع است.');
    }

    public static function collision(string $a, string $b): self
    {
        return new self('router.collision', ['route_a' => $a, 'route_b' => $b], 'دو روت یک مسیر+متد یکسان دارند.');
    }
}
