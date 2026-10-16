<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Support;

use Tavaan\Engine\Compat\Decimal;

/**
 * تَوان | Tavaan — ORM
 * Money — Value Object پول با int minor-unit (ADR-0013: هرگز float).
 *
 * ### مثال
 * ```php
 * $price = Money::of(150000, 'IRR');          // ۱۵۰٬۰۰۰ ریال (minor = ریال)
 * $price = $price->multiply(Percentage::of(10));
 * echo $price->format();                       // ۱۵۰٬۰۰۰ IRR (ارقام فارسی در locale fa)
 * ```
 *
 * @api 1.0
 * @see docs/adr/ADR-0013-money-representation.md
 */
final readonly class Money implements \Stringable
{
    /** @var array<string, int> تعداد ارقام اعشار هر ارز (minor unit) */
    private const DECIMALS = [
        'IRR' => 0, 'IRT' => 0,      // ریال/تومان بدون اعشار
        'USD' => 2, 'EUR' => 2, 'AED' => 2, 'GBP' => 2, 'TRY' => 2,
    ];

    /**
     * @param int $amount مقدار در minor unit (هرگز float)
     */
    public function __construct(
        public int $amount,
        public string $currency = 'IRR',
    ) {
        if (\preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Invalid currency code [%s].', $currency));
        }
    }

    /** ساخت از مقدار major (مثلاً ۱۲٫۵۰ دلار → ۱۲۵۰ minor). */
    public static function fromMajor(string|int|float|Decimal $major, string $currency = 'IRR'): self
    {
        $decimal = $major instanceof Decimal ? $major : Decimal::of($major);
        $factor = 10 ** self::decimals($currency);
        $minor = $decimal->mul(Decimal::of($factor))->rescale(0)->toInt();

        return new self($minor, $currency);
    }

    public static function of(int $amount, string $currency = 'IRR'): self
    {
        return new self($amount, $currency);
    }

    public static function zero(string $currency = 'IRR'): self
    {
        return new self(0, $currency);
    }

    public static function decimals(string $currency): int
    {
        return self::DECIMALS[$currency] ?? 2;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    /** ضرب در ضریب (مثل درصد) با rounding half-up — بدون float. */
    public function multiply(Decimal|int|string $factor): self
    {
        $decimal = $factor instanceof Decimal ? $factor : Decimal::of($factor);
        $result = Decimal::of($this->amount)->mul($decimal)->rescale(0);

        return new self($result->toInt(), $this->currency);
    }

    public function divide(int $divisor): self
    {
        if ($divisor === 0) {
            throw new \DivisionByZeroError('Money division by zero.');
        }
        $result = Decimal::of($this->amount)->div(Decimal::of($divisor), 0);

        return new self($result->toInt(), $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->amount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amount <=> $other->amount;
    }

    /** مقدار major (برای نمایش) — Decimal بدون float. */
    public function toMajor(): Decimal
    {
        $factor = 10 ** self::decimals($this->currency);

        return Decimal::of($this->amount)->div(Decimal::of($factor), self::decimals($this->currency));
    }

    /** نمایش با جداکنندهٔ هزارگان (+ ارقام فارسی در locale فارسی) — بدون float (دقت کامل). */
    public function format(?string $locale = null): string
    {
        $locale ??= \function_exists('config') ? (string) (\config('app.locale', 'fa') ?? 'fa') : 'fa';

        $negative = $this->amount < 0;
        $digits = (string) \abs($this->amount);
        $decimals = self::decimals($this->currency);

        if ($decimals > 0) {
            $digits = \str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);
            $intPart = \substr($digits, 0, -$decimals);
            $decPart = \substr($digits, -$decimals);
        } else {
            $intPart = $digits;
            $decPart = '';
        }

        $formatted = ($negative ? '-' : '') . self::groupDigits($intPart)
            . ($decPart !== '' ? '.' . $decPart : '')
            . ' ' . $this->currency;

        if ($locale === 'fa') {
            return \strtr(
                $formatted,
                ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                    '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', ',' => '٬'],
            );
        }

        return $formatted;
    }

    /** گروه‌بندی سه‌رقمی روی رشتهٔ رقم (بدون float). */
    private static function groupDigits(string $digits): string
    {
        $result = '';
        $length = \strlen($digits);
        for ($i = 0; $i < $length; $i++) {
            if ($i > 0 && ($length - $i) % 3 === 0) {
                $result .= ',';
            }
            $result .= $digits[$i];
        }

        return $result;
    }

    /** @return array{amount: int, currency: string} */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(\sprintf(
                'Currency mismatch: %s vs %s.',
                $this->currency,
                $other->currency,
            ));
        }
    }
}
