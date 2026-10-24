<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade کوکی (Cookie — DOC-042).
 *
 * @method static \Tavaan\Engine\Http\Cookie\Cookie make(string $name, string $value, int $minutes = 60, string $path = '/', ?string $domain = null, ?bool $secure = null, bool $httpOnly = true, string $sameSite = '', bool $encrypted = false)
 * @method static \Tavaan\Engine\Http\Cookie\Cookie forever(string $name, string $value, bool $encrypted = false)
 * @method static \Tavaan\Engine\Http\Cookie\Cookie forget(string $name, string $path = '/', ?string $domain = null)
 * @method static void queue(\Tavaan\Engine\Http\Cookie\Cookie $cookie)
 * @method static bool hasQueued(string $name)
 * @mixin \Tavaan\Engine\Http\Cookie\CookieJar
 * @api 1.0
 * @see docs/03-engines/cookie.md
 */
final class Cookie extends Facade
{
    protected static function accessor(): string
    {
        return 'cookie';
    }
}
