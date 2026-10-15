<?php

declare(strict_types=1);

namespace Tavaan\Core\Autoloading;

/**
 * تَوان | Tavaan — Core
 * AutoloaderRegistry — نگهدار سراسری نمونهٔ Autoloader فعال.
 *
 * @api 1.0
 * @see docs/02-architecture/bootstrapping.md
 */
final class AutoloaderRegistry
{
    private static ?Autoloader $instance = null;

    private function __construct()
    {
    }

    public static function set(Autoloader $autoloader): void
    {
        self::$instance = $autoloader;
    }

    public static function get(): Autoloader
    {
        if (self::$instance === null) {
            throw new \LogicException('Autoloader has not been registered. Require core/bootstrap.php first.');
        }

        return self::$instance;
    }

    public static function has(): bool
    {
        return self::$instance !== null;
    }
}
