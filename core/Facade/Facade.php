<?php

declare(strict_types=1);

namespace Tavaan\Core\Facade;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * تَوان | Tavaan — Core
 * Facade — دسترسی ایستای کنترل‌شده به سرویس‌های کانتینر.
 *
 * هر Facade فقط یک accessor (کلید resolve) دارد؛ همهٔ فراخوانی‌ها به
 * نمونهٔ واقعی delegate می‌شوند (بدون state ایستا جز کانتینر).
 *
 * @api 1.0
 * @see docs/04-structure/folder-structure.md
 */
abstract class Facade
{
    private static ?ContainerInterface $app = null;

    /** @var array<string, object> کش ریشه‌ها (per-process) */
    private static array $resolvedInstances = [];

    /** کلید resolve در کانتینر. */
    abstract protected static function accessor(): string;

    /** اتصال به کانتینر (در Bootstrapper RegisterFacades). */
    public static function setFacadeApplication(?ContainerInterface $app): void
    {
        self::$app = $app;
        self::$resolvedInstances = [];
    }

    public static function getFacadeApplication(): ContainerInterface
    {
        if (self::$app === null) {
            throw new \LogicException('Facade application has not been set.');
        }

        return self::$app;
    }

    /** ریشهٔ Facade (نمونهٔ واقعی سرویس). */
    public static function getFacadeRoot(): mixed
    {
        $accessor = static::accessor();

        return self::$resolvedInstances[$accessor] ??= self::getFacadeApplication()->make($accessor);
    }

    public static function clearResolvedInstances(): void
    {
        self::$resolvedInstances = [];
    }

    /**
     * @param list<mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $instance = static::getFacadeRoot();

        return $instance->{$method}(...$arguments);
    }
}
