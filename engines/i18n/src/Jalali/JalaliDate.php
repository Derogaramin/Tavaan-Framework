<?php

declare(strict_types=1);

namespace Tavaan\Engine\I18n\Jalali;

use Tavaan\Engine\I18n\PersianFormatter;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Jalali
 * JalaliDate — شیء تاریخ جلالی (immutable) با format سازگار با date().
 *
 * ### مثال
 * ```php
 * $d = JalaliDate::fromGregorian('2026-10-15');
 * echo $d->format('Y/m/d l');        // ۱۴۰۵/۰۷/۲۳ پنج‌شنبه
 * echo $d->addDays(10)->format('j F'); // ۲ آبان
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/jalali.md
 */
final readonly class JalaliDate implements \Stringable, \JsonSerializable
{
    /**
     * @param \DateTimeImmutable $time زمان پایه (برای ساعت/دقیقه/zone)
     */
    public function __construct(
        public int $jy,
        public int $jm,
        public int $jd,
        public \DateTimeImmutable $time,
    ) {
    }

    public static function now(?\DateTimeZone $tz = null): self
    {
        return self::fromDateTime(Clock::now()->setTimezone($tz ?? new \DateTimeZone(\date_default_timezone_get())));
    }

    public static function fromGregorian(string $date, ?\DateTimeZone $tz = null): self
    {
        return self::fromDateTime(new \DateTimeImmutable($date, $tz ?? new \DateTimeZone(\date_default_timezone_get())));
    }

    public static function fromDateTime(\DateTimeImmutable $dt): self
    {
        [$jy, $jm, $jd] = Jalali::gregorianToJalali(
            (int) $dt->format('Y'),
            (int) $dt->format('n'),
            (int) $dt->format('j'),
        );

        return new self($jy, $jm, $jd, $dt);
    }

    /** پارس `1405/07/23` یا `1405-07-23` (با ساعت اختیاری). */
    public static function parse(string $jalali, ?\DateTimeZone $tz = null): self
    {
        $tz ??= new \DateTimeZone(\date_default_timezone_get());
        $normalized = PersianFormatter::toLatinDigits(\trim($jalali));

        if (\preg_match('#^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$#', $normalized, $m) !== 1) {
            throw new \InvalidArgumentException("Invalid jalali date format: [{$jalali}]");
        }

        $jy = (int) $m[1];
        $jm = (int) $m[2];
        $jd = (int) $m[3];

        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > Jalali::monthLength($jy, $jm)) {
            throw new \InvalidArgumentException("Out of range jalali date: [{$jalali}]");
        }

        [$gy, $gm, $gd] = Jalali::jalaliToGregorian($jy, $jm, $jd);
        $time = new \DateTimeImmutable(
            \sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0)),
            $tz,
        );

        return new self($jy, $jm, $jd, $time);
    }

    public static function fromTimestamp(int $timestamp, ?\DateTimeZone $tz = null): self
    {
        return self::fromDateTime((new \DateTimeImmutable('@' . $timestamp))->setTimezone($tz ?? new \DateTimeZone(\date_default_timezone_get())));
    }

    // ─── دسترسی ────────────────────────────────────────────────────────────

    public function year(): int
    {
        return $this->jy;
    }

    public function month(): int
    {
        return $this->jm;
    }

    public function day(): int
    {
        return $this->jd;
    }

    /** @return array{0: int, 1: int, 2: int} */
    public function toGregorian(): array
    {
        return Jalali::jalaliToGregorian($this->jy, $this->jm, $this->jd);
    }

    public function toDateTimeImmutable(): \DateTimeImmutable
    {
        return $this->time;
    }

    public function timestamp(): int
    {
        return $this->time->getTimestamp();
    }

    public function isLeap(): bool
    {
        return Jalali::isLeap($this->jy);
    }

    public function monthName(bool $afghan = false): string
    {
        return Jalali::monthName($this->jm, $afghan);
    }

    public function dayName(): string
    {
        [$gy, $gm, $gd] = $this->toGregorian();

        return Jalali::dayName($gy, $gm, $gd);
    }

    public function dayOfWeek(): int
    {
        [$gy, $gm, $gd] = $this->toGregorian();

        return Jalali::dayOfWeek($gy, $gm, $gd);
    }

    /** روز سال (۱..۳۶۶). */
    public function dayOfYear(): int
    {
        $day = $this->jd;
        for ($m = 1; $m < $this->jm; $m++) {
            $day += Jalali::monthLength($this->jy, $m);
        }

        return $day;
    }

    /** هفتهٔ سال (شنبه شروع هفته). */
    public function weekOfYear(): int
    {
        $start = self::parse(\sprintf('%04d/01/01', $this->jy), $this->time->getTimezone());
        $offset = $start->dayOfWeek();

        return (int) \floor(($this->dayOfYear() + $offset - 1) / 7) + 1;
    }

    // ─── تغییر (immutable) ────────────────────────────────────────────────

    public function addDays(int $days): self
    {
        return self::fromDateTime($this->time->modify('+' . $days . ' days'));
    }

    public function subDays(int $days): self
    {
        return self::fromDateTime($this->time->modify('-' . $days . ' days'));
    }

    public function addMonths(int $months): self
    {
        $total = ($this->jy * 12 + $this->jm - 1) + $months;
        $jy = \intdiv($total, 12);
        $jm = ($total % 12) + 1;
        $jd = \min($this->jd, Jalali::monthLength($jy, $jm));

        return self::fromParts($jy, $jm, $jd, $this->time);
    }

    public function subMonths(int $months): self
    {
        return $this->addMonths(-$months);
    }

    public function startOfMonth(): self
    {
        return self::fromParts($this->jy, $this->jm, 1, $this->time->setTime(0, 0));
    }

    public function endOfMonth(): self
    {
        return self::fromParts($this->jy, $this->jm, Jalali::monthLength($this->jy, $this->jm), $this->time->setTime(23, 59, 59));
    }

    /** اختلاف روز (مثبت اگر $other بعد از این باشد). */
    public function diffInDays(self $other): int
    {
        $a = Jalali::jalaliToJulianDay($this->jy, $this->jm, $this->jd);
        $b = Jalali::jalaliToJulianDay($other->jy, $other->jm, $other->jd);

        return $b - $a;
    }

    // ─── فرمت ─────────────────────────────────────────────────────────────

    /**
     * فرمت مانند date() با حروف جلالی (Y/m/d/j/n/l/F/t/z/w/H/i/s — DOC-070 §5).
     *
     * @param bool $persianDigits ارقام فارسی (پیش‌فرض true)
     */
    public function format(string $format, bool $persianDigits = true): string
    {
        $out = '';
        $len = \strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $ch = $format[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $out .= $format[++$i];
                continue;
            }

            $out .= match ($ch) {
                'Y' => (string) $this->jy,
                'y' => \str_pad((string) ($this->jy % 100), 2, '0', STR_PAD_LEFT),
                'm' => \str_pad((string) $this->jm, 2, '0', STR_PAD_LEFT),
                'n' => (string) $this->jm,
                'd' => \str_pad((string) $this->jd, 2, '0', STR_PAD_LEFT),
                'j' => (string) $this->jd,
                'l' => $this->dayName(),
                'D' => \mb_substr($this->dayName(), 0, 3),
                'F' => $this->monthName(),
                'M' => \mb_substr($this->monthName(), 0, 3),
                't' => (string) Jalali::monthLength($this->jy, $this->jm),
                'z' => (string) $this->dayOfYear(),
                'w' => (string) $this->dayOfWeek(),
                'H', 'i', 's', 'G' => $this->time->format($ch),
                default => $ch,
            };
        }

        return $persianDigits ? PersianFormatter::digits($out) : $out;
    }

    public function __toString(): string
    {
        return $this->format('Y/m/d');
    }

    /** @return array<string, int|string> */
    public function jsonSerialize(): array
    {
        return [
            'jalali' => \sprintf('%04d-%02d-%02d', $this->jy, $this->jm, $this->jd),
            'gregorian' => $this->time->format('Y-m-d'),
            'timestamp' => $this->time->getTimestamp(),
        ];
    }

    private static function fromParts(int $jy, int $jm, int $jd, \DateTimeImmutable $ref): self
    {
        [$gy, $gm, $gd] = Jalali::jalaliToGregorian($jy, $jm, $jd);

        return new self(
            $jy,
            $jm,
            $jd,
            $ref->setDate($gy, $gm, $gd),
        );
    }
}
