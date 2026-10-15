<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade پیکربندی.
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(array|string $key, mixed $value = null)
 * @method static bool has(string $key)
 * @mixin \Tavaan\Engine\Config\Repository
 * @api 1.0
 * @see docs/03-engines/config.md
 */
final class Config extends Facade
{
    protected static function accessor(): string
    {
        return 'config';
    }
}
