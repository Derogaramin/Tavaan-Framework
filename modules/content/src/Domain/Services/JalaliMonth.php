<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول محتوا
 * JalaliMonth — ماه جلالی و بازهٔ میلادی متناظر (آرشیو — DOC-091 §3/§7 E2E-05).
 */

namespace Modules\Content\Domain\Services;

/**
 * JalaliMonth — VO ماه جلالی با تبدیل دقیق به بازهٔ میلادی [start, end).
 *
 * 🔒 مرز معماری (ARCH-003): ماژول نمی‌تواند به موتور i18n ارجاع دهد؛ لذا
 * زیرمجموعهٔ الگوریتم jalaali (breaks-based، مطابق jalaali-js) بازپیاده‌سازی
 * شده است — همان BREAKS و arithmetic موتور Jalali (۱۰۰٪ هم‌نتیجه؛ تست‌شده).
 *
 * @api 1.0
 * @see modules/content/README.md
 */
final class JalaliMonth
{
    /** @var list<int> */
    private const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    private const MONTHS_FA = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    private const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    public function __construct(public readonly int $year, public readonly int $month)
    {
        if ($month < 1 || $month > 12) {
            throw new \OutOfRangeException('ماه جلالی نامعتبر: ' . $month);
        }
        $breaks = self::BREAKS;
        if ($year < $breaks[0] || $year >= $breaks[\count($breaks) - 1]) {
            throw new \OutOfRangeException('سال جلالی خارج از بازهٔ پشتیبانی: ' . $year);
        }
    }

    /** ساخت از پارامترهای روت (رشته‌ای) با اعتبارسنجی — ارقام فارسی/عربی پذیرفته می‌شوند. */
    public static function fromRoute(string $year, string $month): self
    {
        $year = self::toLatinDigits($year);
        $month = self::toLatinDigits($month);

        if (\preg_match('/^\d{1,4}$/', $year) !== 1 || \preg_match('/^\d{1,2}$/', $month) !== 1) {
            throw new \OutOfRangeException('سال/ماه جلالی نامعتبر.');
        }

        return new self((int) $year, (int) $month);
    }

    /** تبدیل ارقام فارسی/عربی → لاتین. */
    private static function toLatinDigits(string $value): string
    {
        return \str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $value,
        );
    }

    /** نخستین لحظهٔ روز اول ماه (میلادی، UTC). */
    public function start(): \DateTimeImmutable
    {
        [$gy, $gm, $gd] = self::jalaliToGregorian($this->year, $this->month, 1);

        return new \DateTimeImmutable(\sprintf('%04d-%02d-%02d 00:00:00', $gy, $gm, $gd), new \DateTimeZone('UTC'));
    }

    /** نخستین لحظهٔ ماه بعد (میلادی، UTC) — انتهای باز (exclusive). */
    public function endExclusive(): \DateTimeImmutable
    {
        return $this->next()->start();
    }

    public function next(): self
    {
        return $this->month === 12
            ? new self($this->year + 1, 1)
            : new self($this->year, $this->month + 1);
    }

    public function previous(): self
    {
        return $this->month === 1
            ? new self($this->year - 1, 12)
            : new self($this->year, $this->month - 1);
    }

    /** طول ماه به روز (اسفند کبیسه = ۳۰). */
    public function length(): int
    {
        if ($this->month <= 6) {
            return 31;
        }
        if ($this->month <= 11) {
            return 30;
        }

        return self::isLeap($this->year) ? 30 : 29;
    }

    public static function isLeap(int $jy): bool
    {
        return self::jalCal($jy)['leap'] === 0;
    }

    /** برچسب فارسی با ارقام فارسی — مثال: «مهر ۱۴۰۳». */
    public function labelFa(): string
    {
        return self::MONTHS_FA[$this->month] . ' ' . self::faDigits((string) $this->year);
    }

    /**
     * جلالی → میلادی.
     *
     * @return array{0: int, 1: int, 2: int} [gy, gm, gd]
     */
    public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        return self::d2g(self::j2d($jy, $jm, $jd));
    }

    /**
     * میلادی → جلالی.
     *
     * @return array{0: int, 1: int, 2: int} [jy, jm, jd]
     */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        return self::d2j(self::g2d($gy, $gm, $gd));
    }

    private static function faDigits(string $value): string
    {
        return \str_replace(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], self::FA_DIGITS, $value);
    }

    // ─── الگوریتم jalaali (مطابق موتور Jalali) ────────────────────────────

    private static function j2d(int $jy, int $jm, int $jd): int
    {
        $r = self::jalCal($jy);

        return self::g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - \intdiv($jm, 7) * ($jm - 7) + $jd - 1;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function d2j(int $jdn): array
    {
        $gy = self::d2g($jdn)[0];
        $jy = $gy - 621;
        $r = self::jalCal($jy);
        $jdn1f = self::g2d($gy, 3, $r['march']);

        $k = $jdn - $jdn1f;
        if ($k >= 0) {
            if ($k <= 185) {
                $jm = 1 + \intdiv($k, 31);
                $jd = ($k % 31) + 1;

                return [(int) $jy, (int) $jm, (int) $jd];
            }
            $k -= 186;
        } else {
            $jy -= 1;
            $k += 179;
            if ($r['leap'] === 1) {
                $k += 1;
            }
        }

        $jm = 7 + \intdiv($k, 30);
        $jd = ($k % 30) + 1;

        return [(int) $jy, (int) $jm, (int) $jd];
    }

    private static function g2d(int $gy, int $gm, int $gd): int
    {
        $d = \intdiv(($gy + \intdiv($gm - 8, 6) + 100100) * 1461, 4)
            + \intdiv(153 * (($gm + 9) % 12) + 2, 5)
            + $gd - 34840408;

        return $d - \intdiv(\intdiv($gy + 100100 + \intdiv($gm - 8, 6), 100) * 3, 4) + 752;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function d2g(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j = $j + \intdiv(\intdiv(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = \intdiv($j % 1461, 4) * 5 + 308;
        $gd = \intdiv($i % 153, 5) + 1;
        $gm = (int) (\intdiv($i, 153) % 12) + 1;
        $gy = \intdiv($j, 1461) - 100100 + \intdiv(8 - $gm, 6);

        return [(int) $gy, (int) $gm, (int) $gd];
    }

    /** @return array{leap: int, gy: int, march: int} */
    private static function jalCal(int $jy): array
    {
        $breaks = self::BREAKS;
        $bl = \count($breaks);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = $breaks[0];
        $jump = 0;

        if ($jy < $jp || $jy >= $breaks[$bl - 1]) {
            throw new \OutOfRangeException('Invalid Jalaali year ' . $jy);
        }

        for ($i = 1; $i < $bl; $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ = $leapJ + \intdiv($jump, 33) * 8 + \intdiv($jump % 33, 4);
            $jp = $jm;
        }

        $n = $jy - $jp;
        $leapJ = $leapJ + \intdiv($n, 33) * 8 + \intdiv($n % 33 + 3, 4);
        if (($jump % 33) === 4 && ($jump - $n) === 4) {
            $leapJ += 1;
        }

        $leapG = \intdiv($gy, 4) - \intdiv((\intdiv($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;

        if ($jump - $n < 6) {
            $n = $n - $jump + \intdiv($jump + 4, 33) * 33;
        }
        $leap = (($n + 1) % 33) - 1;
        $leap %= 4;
        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }
}
