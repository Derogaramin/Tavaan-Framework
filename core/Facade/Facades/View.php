<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade ویو.
 *
 * @method static \Tavaan\Engine\View\View make(string $template, array $data = [])
 * @method static string render(string $template, array $data = [])
 * @method static bool exists(string $template)
 * @method static void share(string|array $key, mixed $value = null)
 * @method static void directive(string $name, \Closure $handler)
 * @mixin \Tavaan\Engine\View\ViewFactory
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final class View extends Facade
{
    protected static function accessor(): string
    {
        return 'view';
    }
}
