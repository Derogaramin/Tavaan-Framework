<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM;

/**
 * تَوان | Tavaan — ORM
 * MorphMap — نگاشت alias ↔ class برای روابط چندریختی.
 *
 * 🔒 alias پایدار در DB ذخیره می‌شود (نه نام کامل کلاس) تا
 * جابه‌جایی namespace داده‌ها را نشکند.
 *
 * @api 1.0
 * @see docs/03-engines/orm.md
 */
final class MorphMap
{
    /** @var array<string, class-string> alias → class */
    private static array $map = [];

    /** @param array<string, class-string> $map */
    public static function register(array $map): void
    {
        foreach ($map as $alias => $class) {
            self::$map[(string) $alias] = $class;
        }
    }

    /** alias برای یک کلاس (در نبود ثبت → نام کامل کلاس). */
    public static function alias(string $class): string
    {
        $alias = \array_search($class, self::$map, true);

        return \is_string($alias) ? $alias : $class;
    }

    /** @return class-string|null */
    public static function classFor(string $alias): ?string
    {
        if (isset(self::$map[$alias])) {
            return self::$map[$alias];
        }

        return \class_exists($alias) ? $alias : null;
    }

    /** @return array<string, class-string> */
    public static function all(): array
    {
        return self::$map;
    }

    public static function flush(): void
    {
        self::$map = [];
    }
}
