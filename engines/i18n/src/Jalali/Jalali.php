<?php

declare(strict_types=1);

namespace Tavaan\Engine\I18n\Jalali;

/**
 * تَوان | Tavaan — Jalali
 * Jalali — تبدیل‌های خالص PHP جلالی↔میلادی↔قمری.
 *
 * الگوریتم: پورت دقیق `jalaali-js` (جلال‌کی/شکیبا) بر پایهٔ جدول شکست‌های
 * ۳۳‌ساله — مطابق تقویم رسمی ایران (مرجع DOC-070 §11).
 * 🔍 تفاوت با مستند: فرمول سادهٔ ۲۸۲۰‌ساله برای برخی سال‌ها (مانند ۱۴۰۳)
 * خطا دارد؛ این پیاده‌سازی از الگوریتم breaks استفاده می‌کند (اصلاحیهٔ DOC-070).
 *
 * @api 1.0
 * @see docs/03-engines/jalali.md
 */
final class Jalali
{
    /** @var array<int, string> */
    public const MONTHS_FA = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /** @var array<int, string> */
    public const MONTHS_AF = [
        1 => 'حمل', 2 => 'ثور', 3 => 'جوزا', 4 => 'سرطان', 5 => 'اسد', 6 => 'سنبله',
        7 => 'میزان', 8 => 'عقرب', 9 => 'قوس', 10 => 'جدی', 11 => 'دلو', 12 => 'حوت',
    ];

    /** @var list<string> ایندکس ۰ = شنبه */
    public const DAYS_FA = ['شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

    /** @var list<int> جدول شکست‌های الگوریتم جلالی (jalaali-js) */
    private const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    private function __construct()
    {
    }

    // ─── تبدیل اصلی ───────────────────────────────────────────────────────

    /**
     * میلادی → جلالی.
     *
     * @return array{0: int, 1: int, 2: int} [jy, jm, jd]
     */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        return self::d2j(self::g2d($gy, $gm, $gd));
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

    /** @return array{0: int, 1: int, 2: int} */
    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        return self::gregorianToJalali($gy, $gm, $gd);
    }

    /** @return array{0: int, 1: int, 2: int} */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        return self::jalaliToGregorian($jy, $jm, $jd);
    }

    // ─── کبیسه و طول ماه ──────────────────────────────────────────────────

    /**
     * کبیسهٔ جلالی بر پایهٔ الگوریتم breaks (دقیق برای ۱۱۷۸..۳۱۷۷).
     */
    public static function isLeap(int $jy): bool
    {
        return self::jalCal($jy)['leap'] === 0;
    }

    public static function monthLength(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }

        return self::isLeap($jy) ? 30 : 29;   // اسفند
    }

    // ─── Julian Day (پل تبدیل) ────────────────────────────────────────────

    public static function gregorianToJulianDay(int $gy, int $gm, int $gd): int
    {
        return self::g2d($gy, $gm, $gd);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function julianDayToGregorian(int $jd): array
    {
        return self::d2g($jd);
    }

    public static function jalaliToJulianDay(int $jy, int $jm, int $jd): int
    {
        return self::j2d($jy, $jm, $jd);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function julianDayToJalali(int $jd): array
    {
        return self::d2j($jd);
    }

    // ─── قمری (Tabular Islamic — ±۱ روز نسبت به رؤیت هلال) ────────────────

    /**
     * Julian Day → قمری (الگوریتم محاسباتی کویتی/جدولی).
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function julianDayToHijri(int $jd): array
    {
        $days = $jd - 1948439;          // مبدأ: ۱ محرم سال ۱ هجری
        $cycle = \intdiv($days - 1, 10631);
        $days -= $cycle * 10631;
        $years = \intdiv($days - 1, 354);
        $days -= $years * 354;
        $year = $cycle * 30 + $years;
        $month = (int) \floor(($days * 11 + 330) / 325);
        $month = \max(1, \min(12, $month));
        $day = $days - self::hijriDayOfYear($month) + 1;

        if ($day < 1) {
            $month--;
            $day += $month % 2 === 1 ? 30 : 29;
        }

        return [$year + 1, $month, $day];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function gregorianToHijri(int $gy, int $gm, int $gd): array
    {
        return self::julianDayToHijri(self::g2d($gy, $gm, $gd));
    }

    // ─── نام‌ها و روز هفته ────────────────────────────────────────────────

    public static function monthName(int $jm, bool $afghan = false): string
    {
        $table = $afghan ? self::MONTHS_AF : self::MONTHS_FA;

        return $table[$jm] ?? '';
    }

    /** نام روز هفتهٔ ایرانی (شنبه اول) از تاریخ میلادی. */
    public static function dayName(int $gy, int $gm, int $gd): string
    {
        return self::DAYS_FA[self::dayOfWeek($gy, $gm, $gd)];
    }

    /** ایندکس روز هفته: ۰=شنبه … ۶=جمعه. */
    public static function dayOfWeek(int $gy, int $gm, int $gd): int
    {
        // JDN mod 7: 0=Monday … 6=Sunday → ایرانی: شنبه=(jd+2)%7
        return (self::g2d($gy, $gm, $gd) + 2) % 7;
    }

    // ─── هستهٔ الگوریتم (jalaali-js) ──────────────────────────────────────

    /** میلادی → Julian Day. */
    private static function g2d(int $gy, int $gm, int $gd): int
    {
        $d = \intdiv(($gy + \intdiv($gm - 8, 6) + 100100) * 1461, 4)
            + \intdiv(153 * (($gm + 9) % 12) + 2, 5)
            + $gd - 34840408;

        return $d - \intdiv(\intdiv($gy + 100100 + \intdiv($gm - 8, 6), 100) * 3, 4) + 752;
    }

    /** Julian Day → میلادی. @return array{0:int,1:int,2:int} */
    private static function d2g(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j = $j + \intdiv(\intdiv(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = \intdiv($j % 1461, 4) * 5 + 308;
        $gd = \intdiv($i % 153, 5) + 1;
        $gm = (\intdiv($i, 153) % 12) + 1;
        $gy = \intdiv($j, 1461) - 100100 + \intdiv(8 - $gm, 6);

        return [(int) $gy, (int) $gm, (int) $gd];
    }

    /** جلالی → Julian Day. */
    private static function j2d(int $jy, int $jm, int $jd): int
    {
        $r = self::jalCal($jy);

        return self::g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - \intdiv($jm, 7) * ($jm - 7) + $jd - 1;
    }

    /** Julian Day → جلالی. @return array{0:int,1:int,2:int} */
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
            // 🔒 leap از jalCal سال «قبل از کاهش» (مطابق jalaali-js)
            if ($r['leap'] === 1) {
                $k += 1;
            }
        }

        $jm = 7 + \intdiv($k, 30);
        $jd = ($k % 30) + 1;

        return [(int) $jy, (int) $jm, (int) $jd];
    }

    /**
     * محاسبهٔ پارامترهای سال جلالی (leap, gy, march) — الگوریتم breaks.
     *
     * @return array{leap: int, gy: int, march: int}
     */
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

    /** روز سال قمری برای ابتدای ماه (1-based). */
    private static function hijriDayOfYear(int $month): int
    {
        $day = 0;
        for ($m = 1; $m < $month; $m++) {
            $day += $m % 2 === 1 ? 30 : 29;
        }

        return $day;
    }
}
