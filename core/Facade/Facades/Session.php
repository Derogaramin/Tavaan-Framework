<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade نشست.
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void put(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static mixed pull(string $key, mixed $default = null)
 * @method static void flash(string $key, mixed $value)
 * @method static void regenerate(bool $destroy = false)
 * @method static string id()
 * @method static string token()
 * @mixin \Tavaan\Engine\Session\Session
 * @api 1.0
 * @see docs/03-engines/session.md
 */
final class Session extends Facade
{
    protected static function accessor(): string
    {
        return 'session';
    }
}
