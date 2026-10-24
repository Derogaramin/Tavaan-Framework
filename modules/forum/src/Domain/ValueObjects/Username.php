<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * Username — هندل یکتای کاربر برای @mention و پروفایل عمومی.
 */

namespace Modules\Forum\Domain\ValueObjects;

/**
 * Username — قواعد:
 * - ۳..۳۰ کاراکتر؛ حروف لاتین/فارسی، رقم، `_` و `.`
 * - شروع با حرف؛ بدون دو نقطه/زیرخط متوالی
 * - case-insensitive یکتا (ذخیره همان‌طور که کاربر نوشته).
 *
 * @api 1.0
 */
final readonly class Username
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value, int $min = 3, int $max = 30): self
    {
        $value = \trim($value);

        if (!self::isValid($value, $min, $max)) {
            throw new \InvalidArgumentException('username نامعتبر است.');
        }

        return new self($value);
    }

    public static function isValid(string $value, int $min = 3, int $max = 30): bool
    {
        if ($value === '' || \mb_strlen($value) < $min || \mb_strlen($value) > $max) {
            return false;
        }

        // حروف لاتین/فارسی + رقم + _ و . — شروع با حرف
        $pattern = '/^[\p{L}][\p{L}\p{Nd}_.]*$/u';

        if (\preg_match($pattern, $value) !== 1) {
            return false;
        }

        return !\preg_match('/[_.]{2}/u', $value);
    }

    /** پیشنهاد username یکتا از نام نمایشی (برای seed/ثبت‌نام خودکار). */
    public static function suggest(string $displayName, callable $exists, int $userId = 0): string
    {
        // نرمال‌سازی: فقط حروف/رقم؛ فضای خالی → _
        $base = \trim((string) \preg_replace('/[^\p{L}\p{Nd}]+/u', '_', $displayName), '_');
        if ($base === '') {
            $base = 'user';
        }
        $base = \mb_substr($base, 0, 20);
        if (!\preg_match('/^[\p{L}]/u', $base)) {
            $base = 'u' . $base;
        }

        $candidate = $base;
        $suffix = $userId > 0 ? $userId : 1;
        while ($exists($candidate)) {
            $candidate = \mb_substr($base, 0, 26) . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
