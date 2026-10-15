<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade ماژول‌ها.
 *
 * @method static array all()
 * @method static \Tavaan\Engine\Module\Module get(string $id)
 * @method static bool enabled(string $id)
 * @mixin \Tavaan\Engine\Module\ModuleRegistry
 * @api 1.0
 * @see docs/03-engines/module.md
 */
final class Module extends Facade
{
    protected static function accessor(): string
    {
        return 'modules';
    }
}
