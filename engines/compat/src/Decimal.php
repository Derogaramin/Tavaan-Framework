<?php

declare(strict_types=1);

namespace Tavaan\Engine\Compat;

/**
 * تَوان | Tavaan — Compat
 * Decimal — حساب اعشار دقیق بدون bcmath (Long Arithmetic).
 *
 * نمایش داخلی: عدد صحیح بزرگ (unscaled) + scale
 * (value = unscaled / 10^scale) — همان الگوی BigDecimal جاوا.
 *
 * 🔒 AC-CMP-04: دقت ۱۸ رقم اعشار در ضرب/تقسیم بدون bcmath.
 *
 * ### مثال
 * ```php
 * $a = Decimal::of('0.1');
 * $b = Decimal::of('0.2');
 * echo $a->add($b);            // 0.3 (نه 0.30000000000000004)
 * echo Decimal::of('1')->div(Decimal::of('3'));  // 0.333333333333333333
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/compat.md
 */
final readonly class Decimal
{
    public const DEFAULT_SCALE = 18;

    /**
     * @param string $unscaled رقم‌های قدرمطلق (بدون علامت، بدون leading zero اضافی)
     * @param bool   $negative علامت
     * @param int    $scale    تعداد ارقام اعشار
     */
    private function __construct(
        public string $unscaled,
        public bool $negative,
        public int $scale,
    ) {
    }

    /** ساخت از رشته/عدد. */
    public static function of(string|int|float|self $value, ?int $scale = null): self
    {
        if ($value instanceof self) {
            return $scale === null ? $value : $value->rescale($scale);
        }

        $s = \is_float($value) ? self::floatToString($value) : (string) $value;
        $s = \trim($s);

        $negative = false;
        if ($s !== '' && ($s[0] === '-' || $s[0] === '+')) {
            $negative = $s[0] === '-';
            $s = \substr($s, 1);
        }

        if (\preg_match('/^(\d*)(?:\.(\d*))?$/', $s, $m) !== 1) {
            throw new \InvalidArgumentException("Invalid decimal string: [{$s}]");
        }

        $intPart = $m[1] !== '' ? $m[1] : '0';
        $fracPart = $m[2] ?? '';

        $digits = \ltrim($intPart . $fracPart, '0');
        if ($digits === '') {
            $digits = '0';
        }

        return new self($digits, $negative && $digits !== '0', \strlen($fracPart));
    }

    public static function zero(int $scale = self::DEFAULT_SCALE): self
    {
        return new self('0', false, $scale);
    }

    // ─── عملیات ───────────────────────────────────────────────────────────

    public function add(self $other): self
    {
        [$a, $b, $scale] = $this->align($other);

        if ($this->negative === $other->negative) {
            return new self(self::addDigits($a, $b), $this->negative && (self::addDigits($a, $b) !== '0'), $scale);
        }

        $cmp = self::cmpDigits($a, $b);
        if ($cmp === 0) {
            return new self('0', false, $scale);
        }

        return $cmp > 0
            ? new self(self::subDigits($a, $b), $this->negative, $scale)
            : new self(self::subDigits($b, $a), $other->negative, $scale);
    }

    public function sub(self $other): self
    {
        return $this->add($other->negate());
    }

    public function mul(self $other): self
    {
        $digits = self::mulDigits($this->unscaled, $other->unscaled);

        return new self(
            $digits,
            $digits !== '0' && $this->negative !== $other->negative,
            $this->scale + $other->scale,
        );
    }

    /**
     * تقسیم با scale مشخص و rounding.
     *
     * @throws \DivisionByZeroError
     */
    public function div(self $other, int $scale = self::DEFAULT_SCALE, RoundingMode $rounding = RoundingMode::HalfUp): self
    {
        if ($other->unscaled === '0') {
            throw new \DivisionByZeroError('Division by zero');
        }

        // value = (A/10^sa) / (B/10^sb) = (A * 10^(sr + sb - sa)) / B  با scale = sr
        $shift = $scale + $other->scale - $this->scale;
        $numerator = $shift >= 0 ? $this->unscaled . \str_repeat('0', $shift) : self::shiftDown($this->unscaled, -$shift);

        [$quotient, $remainder] = self::divDigits($numerator, $other->unscaled);

        // rounding بر اساس باقی‌مانده
        if ($remainder !== '0' && self::shouldRoundUp($remainder, $other->unscaled, $rounding)) {
            $quotient = self::addDigits($quotient, '1');
        }

        return new self($quotient, $quotient !== '0' && $this->negative !== $other->negative, $scale);
    }

    public function negate(): self
    {
        return new self($this->unscaled, $this->unscaled !== '0' ? !$this->negative : false, $this->scale);
    }

    public function abs(): self
    {
        return new self($this->unscaled, false, $this->scale);
    }

    /** -1 | 0 | 1 */
    public function comp(self $other): int
    {
        if ($this->negative !== $other->negative) {
            return $this->negative ? -1 : 1;
        }

        [$a, $b] = $this->align($other);
        $cmp = self::cmpDigits($a, $b);

        return $this->negative ? -$cmp : $cmp;
    }

    public function isZero(): bool
    {
        return $this->unscaled === '0';
    }

    public function rescale(int $scale, RoundingMode $rounding = RoundingMode::HalfUp): self
    {
        if ($scale === $this->scale) {
            return $this;
        }

        if ($scale > $this->scale) {
            return new self($this->unscaled . \str_repeat('0', $scale - $this->scale), $this->negative, $scale);
        }

        $drop = $this->scale - $scale;
        $kept = self::shiftDown($this->unscaled, $drop);
        $dropped = \strlen($this->unscaled) > $drop ? \substr($this->unscaled, -$drop) : \str_pad($this->unscaled, $drop, '0', STR_PAD_LEFT);

        if ($dropped !== \str_repeat('0', $drop) && self::shouldRoundUp($dropped, '1' . \str_repeat('0', $drop), $rounding)) {
            $kept = self::addDigits($kept, '1');
        }

        return new self($kept, $kept !== '0' && $this->negative, $scale);
    }

    public function toFloat(): float
    {
        return (float) $this->toString();
    }

    public function toInt(): int
    {
        return (int) $this->rescale(0, RoundingMode::Down)->unscaled * ($this->negative ? -1 : 1);
    }

    public function toString(): string
    {
        $digits = $this->unscaled;
        if ($this->scale > 0) {
            $digits = \str_pad($digits, $this->scale + 1, '0', STR_PAD_LEFT);
            $intPart = \substr($digits, 0, -$this->scale);
            $fracPart = \rtrim(\substr($digits, -$this->scale), '0');
            $digits = $fracPart === '' ? $intPart : $intPart . '.' . $fracPart;
        }

        return ($this->negative ? '-' : '') . $digits;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    // ─── حساب روی رشتهٔ ارقام ─────────────────────────────────────────────

    /** @return array{0: string, 1: string, 2: int} [a, b, scale] هم‌تراز */
    private function align(self $other): array
    {
        $scale = \max($this->scale, $other->scale);
        $a = $this->unscaled . \str_repeat('0', $scale - $this->scale);
        $b = $other->unscaled . \str_repeat('0', $scale - $other->scale);

        return [$a, $b, $scale];
    }

    private static function addDigits(string $a, string $b): string
    {
        $result = '';
        $carry = 0;
        $i = \strlen($a) - 1;
        $j = \strlen($b) - 1;

        while ($i >= 0 || $j >= 0 || $carry > 0) {
            $sum = $carry + ($i >= 0 ? \ord($a[$i--]) - 48 : 0) + ($j >= 0 ? \ord($b[$j--]) - 48 : 0);
            $carry = \intdiv($sum, 10);
            $result = (string) ($sum % 10) . $result;
        }

        return \ltrim($result, '0') === '' ? '0' : \ltrim($result, '0');
    }

    /** فرض: $a >= $b */
    private static function subDigits(string $a, string $b): string
    {
        $result = '';
        $borrow = 0;
        $i = \strlen($a) - 1;
        $j = \strlen($b) - 1;

        while ($i >= 0) {
            $diff = (\ord($a[$i--]) - 48) - $borrow - ($j >= 0 ? \ord($b[$j--]) - 48 : 0);
            if ($diff < 0) {
                $diff += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result = (string) $diff . $result;
        }

        $trimmed = \ltrim($result, '0');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private static function mulDigits(string $a, string $b): string
    {
        if ($a === '0' || $b === '0') {
            return '0';
        }

        $lenA = \strlen($a);
        $lenB = \strlen($b);
        $pos = \array_fill(0, $lenA + $lenB, 0);

        for ($i = $lenA - 1; $i >= 0; $i--) {
            for ($j = $lenB - 1; $j >= 0; $j--) {
                $mul = (\ord($a[$i]) - 48) * (\ord($b[$j]) - 48);
                $p1 = $i + $j;
                $p2 = $i + $j + 1;
                $sum = $mul + $pos[$p2];

                $pos[$p2] = $sum % 10;
                $pos[$p1] += \intdiv($sum, 10);
            }
        }

        $result = \ltrim(\implode('', $pos), '0');

        return $result === '' ? '0' : $result;
    }

    /** تقسیم صحیح اعداد بزرگ → [quotient, remainder] */
    private static function divDigits(string $a, string $b): array
    {
        if ($b === '0') {
            throw new \DivisionByZeroError('Division by zero');
        }

        if (self::cmpDigits($a, $b) < 0) {
            return ['0', $a];
        }

        $quotient = '';
        $current = '';
        $lenA = \strlen($a);

        for ($i = 0; $i < $lenA; $i++) {
            $current = \ltrim($current . $a[$i], '0');
            if ($current === '') {
                $current = '0';
            }

            $digit = 0;
            while (self::cmpDigits($current, $b) >= 0) {
                $current = self::subDigits($current, $b);
                $digit++;
            }
            $quotient .= (string) $digit;
        }

        $quotient = \ltrim($quotient, '0');

        return [$quotient === '' ? '0' : $quotient, $current === '' ? '0' : $current];
    }

    private static function cmpDigits(string $a, string $b): int
    {
        $a = \ltrim($a, '0') ?: '0';
        $b = \ltrim($b, '0') ?: '0';

        if (\strlen($a) !== \strlen($b)) {
            return \strlen($a) <=> \strlen($b);
        }

        return \strcmp($a, $b) <=> 0;
    }

    /** حذف $n رقم سمت راست (بدون rounding). */
    private static function shiftDown(string $digits, int $n): string
    {
        if ($n >= \strlen($digits)) {
            return '0';
        }

        $result = \substr($digits, 0, -$n);

        return ($result === '' || $result === false) ? '0' : (\ltrim($result, '0') ?: '0');
    }

    /** تصمیم rounding بر اساس باقی‌مانده/مقسوم‌علیحه. */
    private static function shouldRoundUp(string $remainder, string $divisor, RoundingMode $rounding): bool
    {
        $double = self::mulDigits($remainder, '2');
        $cmp = self::cmpDigits($double, $divisor);

        return match ($rounding) {
            RoundingMode::HalfUp   => $cmp >= 0,
            RoundingMode::HalfDown => $cmp > 0,
            RoundingMode::HalfEven => $cmp > 0,   // ساده‌شده
            RoundingMode::Up       => $remainder !== '0',
            RoundingMode::Down     => false,
        };
    }

    /** float → string بدون نماد علمی (دقت ۱۸ رقم). */
    private static function floatToString(float $f): string
    {
        if (\is_nan($f) || \is_infinite($f)) {
            throw new \InvalidArgumentException('NaN/Inf cannot be represented as Decimal.');
        }

        return \number_format($f, self::DEFAULT_SCALE, '.', '');
    }
}
