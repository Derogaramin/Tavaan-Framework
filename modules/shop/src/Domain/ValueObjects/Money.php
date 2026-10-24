<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول فروشگاه
 * Money — VO پول با int minor-unit (ADR-0013: هرگز float).
 */

namespace Modules\Shop\Domain\ValueObjects;

/**
 * Money — مقدار پول تغییرناپذیر.
 *
 * 🔒 مطابق ADR-0013 مبلغ همیشه int minor-unit است (IRR → ریال).
 * مرز ماژول (ARCH-003) اجازهٔ استفاده از Money موتور ORM را نمی‌دهد؛
 * این VO هم‌معنی و هم‌سازگار با آن است (of/minor/currency).
 *
 * @api 1.0
 * @see modules/shop/README.md
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
    }

    public static function of(int $minor, string $currency = 'IRR'): self
    {
        return new self($minor, $currency);
    }

    public static function zero(string $currency = 'IRR'): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    /** درصدی از مبلغ (گرد به پایین — به نفع مشتری). */
    public function percent(float $rate): self
    {
        $rate = \max(0.0, \min(100.0, $rate));

        return new self((int) \floor($this->minor * $rate / 100), $this->currency);
    }

    public function min(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->minor <= $other->minor ? $this : $other;
    }

    public function max(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->minor >= $other->minor ? $this : $other;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    /** نمایش با جداکنندهٔ هزارگان (بدون تبدیل ارقام — ویو trans_number می‌زند). */
    public function format(): string
    {
        return \number_format($this->minor);
    }

    public function __toString(): string
    {
        return (string) $this->minor;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(\sprintf('ارز یکسان نیست: %s در برابر %s', $this->currency, $other->currency));
        }
    }
}
