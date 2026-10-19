<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade پیشخان مدیریت (Admin).
 *
 * @method static \Tavaan\Engine\Admin\Resource resource(string $model, ?\Closure $configure = null)
 * @method static array resources()
 * @method static ?\Tavaan\Engine\Admin\Resource find(string $key)
 * @method static \Tavaan\Engine\Admin\Menu\Menu menu(?\Closure $configure = null)
 * @method static \Tavaan\Engine\Admin\Dashboard\Dashboard dashboard(?\Closure $configure = null)
 * @method static void widget(string $class, array $params = [], int $width = 1)
 * @method static void registerRoutes(\Tavaan\Engine\Http\Routing\Router $router)
 * @mixin \Tavaan\Engine\Admin\AdminManager
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class Admin extends Facade
{
    protected static function accessor(): string
    {
        return 'admin';
    }
}
