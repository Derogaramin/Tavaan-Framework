<?php

declare(strict_types=1);

namespace Tavaan\Engine\Api;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * تَوان | Tavaan — Api
 * ApiResolver — دسترسی ایستا (اختیاری) به Container برای JsonResource.
 *
 * توسط ApiServiceProvider هنگام register مقداردهی می‌شود؛ بدون اپ
 * (unit testهای ایزوله) null برمی‌گرداند.
 *
 * @api 1.0
 * @see docs/03-engines/api.md
 */
final class ApiResolver
{
    private static ?ContainerInterface $container = null;

    private function __construct()
    {
    }

    public static function set(?ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function container(): ?ContainerInterface
    {
        return self::$container;
    }
}
