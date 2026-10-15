<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing\Exceptions;

use Tavaan\Core\Exceptions\TavaanException;

/**
 * خطای تولید URL (روت/پارامتر مفقود).
 *
 * @api 1.0
 * @see docs/03-engines/router.md
 */
final class UrlGenerationException extends TavaanException
{
    /** @param list<string> $missing */
    public static function missingParameters(string $name, array $missing): self
    {
        return new self('router.url.missing_parameters', ['route' => $name, 'missing' => $missing], 'پارامترهای الزامی روت را بدهید.');
    }

    public static function routeNotFound(string $name): self
    {
        return new self('router.url.route_not_found', ['route' => $name], 'روتی با این نام ثبت نشده است.');
    }
}
