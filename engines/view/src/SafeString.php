<?php

declare(strict_types=1);

namespace Tavaan\Engine\View;

/**
 * تَوان | Tavaan — View
 * SafeString — رشتهٔ از پیش escape/sanitize شده (بدون escape مجدد).
 *
 * ⚠️ فقط خروجی Sanitizer مجاز موتور security می‌تواند SafeString باشد.
 *
 * @api 1.0
 * @see docs/03-engines/view.md
 */
final readonly class SafeString implements \Stringable
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
