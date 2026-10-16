<?php

declare(strict_types=1);

namespace Tavaan\Engine\Http\Routing;

/**
 * تَوان | Tavaan — Routing
 * RouteSegment — بخش پارامتریک یک روت (نمایش فشردهٔ حافظه).
 *
 * بخش‌های static به‌صورت string ساده در list ذخیره می‌شوند و بخش‌های
 * پارامتریک با این کلاس — به‌جای آرایه‌های کلیددار (هر آرایهٔ hashed در PHP
 * حداقل ~۳۴۴ بایت هزینه دارد؛ این کلاس ~۵۶ بایت). هدف: DOC-040 §12
 * (حافظهٔ ۱۰۰۰ روت ≤ ۲MB).
 *
 * @api 1.0 (internal)
 * @see docs/03-engines/router.md
 */
final class RouteSegment
{
    public function __construct(
        /** نام پارامتر (مثلاً id) */
        public readonly string $name,
        /** regex محدودیت یا null (پیش‌فرض matcher: [^/]+) */
        public readonly ?string $regex,
        /** آیا چند segment را می‌بلعد (wildcard edge) */
        public readonly bool $wildcard = false,
    ) {
    }
}
