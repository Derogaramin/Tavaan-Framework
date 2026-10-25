<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * Money — VO پول با int minor-unit (ADR-0013: هرگز float).
 */

namespace Modules\Lms\Domain\ValueObjects;

/**
 * Money — مقدار پول تغییرناپذیر (قیمت دوره).
 *
 * 🔒 مطابق ADR-0013 مبلغ همیشه int minor-unit است (IRR → ریال).
 * هم‌معنی و هم‌سازگار با Money ماژول shop (of/minor/currency) —
 * مرز ماژول (ARCH-003) اجازهٔ اشتراک کلاس نمی‌دهد.
 *
 * @api 1.0
 * @see modules/lms/README.md
 */
final readonly class Money
{
    private function __construct(
        public int $minor,
        public string $currency,
    ) {
        if ($currency === '') {
            throw new \InvalidArgumentException('currency نمی‌تواند خالی باشد.');
        }
        if ($minor < 0) {
            throw new \InvalidArgumentException('مبلغ نمی‌تواند منفی باشد.');
        }
    }

    public static function of(int $minor, string $currency = 'IRR'): self
    {
        return new self($minor, $currency);
    }

    public static function zero(string $currency = 'IRR'): self
    {
        return new self(0, $currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }
}
