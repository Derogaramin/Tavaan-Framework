<?php

declare(strict_types=1);

namespace Tavaan\Testing;

/**
 * تَوان | Tavaan — Testing
 * TestSkipped — نشان‌دهندهٔ «skip مستند» است.
 *
 * 🔒 skip فقط برای **محدودیت‌های محیطی قطعی** مجاز است (مثلاً نبود
 * حافظه برای fork در sandbox، نبود افزونهٔ اختیاری) — نه برای پنهان‌کردن
 * شکست. دلیل همیشه در گزارش چاپ می‌شود (DOC-113 §7: «تست skip بدون دلیل»
 * رد فوری است — skip **با دلیل** یک خروجی مشروع و شمارش‌شده است).
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class TestSkipped extends \RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
