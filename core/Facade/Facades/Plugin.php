<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade\Facades;

use Tavaan\Core\Facade\Facade;

/**
 * Facade سیستم افزونه (Plugin).
 *
 * @method static \Tavaan\Engine\Plugin\Plugin install(string $sourceDir, bool $force = false)
 * @method static \Tavaan\Engine\Plugin\Plugin update(string $id, string $sourceDir, bool $force = false)
 * @method static void enable(string $id)
 * @method static void disable(string $id)
 * @method static void uninstall(string $id, bool $deleteFiles = false)
 * @method static array list()
 * @method static void verify(string $id)
 * @method static \Tavaan\Engine\Plugin\ScanReport scan(string $dir)
 * @method static bool isLoaded(string $id)
 * @mixin \Tavaan\Engine\Plugin\PluginManager
 * @api 1.0
 * @see docs/03-engines/plugin.md
 */
final class Plugin extends Facade
{
    protected static function accessor(): string
    {
        return 'plugins';
    }
}
