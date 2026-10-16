<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search;

/**
 * تَوان | Tavaan — Search
 * SearchResolver — دسترسی ایستا (اختیاری) به SearchManager برای trait.
 *
 * توسط SearchServiceProvider هنگام boot مقداردهی می‌شود؛ بدون اپ
 * (مثل unit testهای ایزوله) null برمی‌گرداند.
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class SearchResolver
{
    private static ?SearchManager $manager = null;

    private function __construct()
    {
    }

    public static function set(?SearchManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function manager(): ?SearchManager
    {
        return self::$manager;
    }
}
