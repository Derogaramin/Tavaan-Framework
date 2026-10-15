<?php

declare(strict_types=1);

namespace Tavaan\Engine\I18n\Tests\Unit;

use Tavaan\Engine\I18n\Jalali\Jalali;
use Tavaan\Engine\I18n\Jalali\JalaliDate;
use Tavaan\Testing\TestCase;

/**
 * تست تقویم جلالی — DOC-070 §9 (AC-JALALI-01..04).
 */
final class JalaliTest extends TestCase
{
    /** @return list<array{0: array{0:int,1:int,2:int}, 1: array{0:int,1:int,2:int}}> */
    private function vectors(): array
    {
        return [
            [[2026, 10, 15], [1405, 7, 23]],
            [[2025, 3, 21], [1404, 1, 1]],    // نوروز ۱۴۰۴ (جمعه)
            [[2024, 3, 20], [1403, 1, 1]],
            [[2021, 3, 21], [1400, 1, 1]],
            [[2020, 3, 20], [1399, 1, 1]],
            [[1979, 2, 11], [1357, 11, 22]],  // انقلاب
            [[2000, 1, 1], [1378, 10, 11]],
        ];
    }

    /** AC-JALALI-04: Test Vectors */
    public function testReferenceVectors(): void
    {
        foreach ($this->vectors() as [$g, $j]) {
            $this->assertSame($j, Jalali::gregorianToJalali(...$g), \implode('-', $g) . ' → jalali');
            $this->assertSame($g, Jalali::jalaliToGregorian(...$j), \implode('-', $j) . ' → gregorian');
        }
    }

    /** AC-JALALI-01: ۱۰۰۰۰ تبدیل دوطرفه */
    public function testTenThousandRoundtrips(): void
    {
        $jd = Jalali::gregorianToJulianDay(1950, 1, 1);
        $fails = 0;
        for ($i = 0; $i < 10000; $i++) {
            $g = Jalali::julianDayToGregorian($jd + $i);
            $j = Jalali::gregorianToJalali(...$g);
            $g2 = Jalali::jalaliToGregorian(...$j);
            if ($g !== $g2) {
                $fails++;
            }
        }
        $this->assertSame(0, $fails);
    }

    /** roundtrip از سمت جلالی (۱۳۰۰..۱۴۵۰) */
    public function testJalaliSideRoundtrip(): void
    {
        $fails = 0;
        for ($jy = 1300; $jy <= 1450; $jy++) {
            foreach (\range(1, 12) as $jm) {
                $len = Jalali::monthLength($jy, $jm);
                for ($d = 1; $d <= $len; $d += 7) {
                    $g = Jalali::jalaliToGregorian($jy, $jm, $d);
                    $back = Jalali::gregorianToJalali(...$g);
                    if ($back !== [$jy, $jm, $d]) {
                        $fails++;
                    }
                }
            }
        }
        $this->assertSame(0, $fails);
    }

    /** AC-JALALI-02: تطبیق با intl (در صورت وجود) */
    public function testIntlParity(): void
    {
        if (!\class_exists(\IntlCalendar::class)) {
            $this->assertTrue(true, 'intl not available — skipped');

            return;
        }

        $cal = \IntlCalendar::createInstance('UTC', 'fa_IR@calendar=persian');
        \assert($cal instanceof \IntlCalendar);
        $bad = 0;
        $ts = \mktime(12, 0, 0, 1, 1, 1970);
        for ($i = 0; $i < 3000; $i++) {
            $g = [(int) \date('Y', $ts), (int) \date('n', $ts), (int) \date('j', $ts)];
            $cal->setTime($ts * 1000.0);
            $iy = $cal->get(\IntlCalendar::FIELD_EXTENDED_YEAR);
            $im = $cal->get(\IntlCalendar::FIELD_MONTH) + 1;
            $id = $cal->get(\IntlCalendar::FIELD_DAY_OF_MONTH);
            if (Jalali::gregorianToJalali(...$g) !== [$iy, $im, $id]) {
                $bad++;
            }
            $ts += 86400 * 5;
        }
        $this->assertSame(0, $bad, 'intl parity must be 100%');
    }

    /** AC-JALALI-03: کبیسه ۳۳‌ساله */
    public function testLeapYears(): void
    {
        $this->assertTrue(Jalali::isLeap(1399));
        $this->assertFalse(Jalali::isLeap(1400));
        $this->assertTrue(Jalali::isLeap(1403));
        $this->assertFalse(Jalali::isLeap(1404));
        $this->assertTrue(Jalali::isLeap(1408));
        // اسفند
        $this->assertSame(30, Jalali::monthLength(1399, 12));
        $this->assertSame(29, Jalali::monthLength(1400, 12));
        // طول ماه‌ها
        $this->assertSame(31, Jalali::monthLength(1405, 1));
        $this->assertSame(31, Jalali::monthLength(1405, 6));
        $this->assertSame(30, Jalali::monthLength(1405, 7));
        $this->assertSame(30, Jalali::monthLength(1405, 11));
    }

    /** T-06: نام ماه/روز */
    public function testMonthAndDayNames(): void
    {
        $this->assertSame('فروردین', Jalali::monthName(1));
        $this->assertSame('مهر', Jalali::monthName(7));
        $this->assertSame('اسفند', Jalali::monthName(12));
        $this->assertSame('حمل', Jalali::monthName(1, afghan: true));
        // 2026-10-15 = پنج‌شنبه
        $this->assertSame('پنج‌شنبه', Jalali::dayName(2026, 10, 15));
        // 2026-10-17 = شنبه
        $this->assertSame('شنبه', Jalali::dayName(2026, 10, 17));
    }

    /** T-07: فرمت با ارقام فارسی */
    public function testJalaliDateFormat(): void
    {
        $d = JalaliDate::fromGregorian('2026-10-15');
        $this->assertSame('۱۴۰۵/۰۷/۲۳', $d->format('Y/m/d'));
        $this->assertSame('1405-07-23', $d->format('Y-m-d', persianDigits: false));
        $this->assertSame('۲۳', $d->format('j'));
        $this->assertSame('مهر', $d->format('F'));
        $this->assertSame('پنج‌شنبه', $d->format('l'));
        $this->assertSame('۳۰', $d->format('t'));   // مهر ۳۰ روز
        $this->assertStringContainsString('۱۴۰۵', (string) $d);   // __toString
    }

    /** parse با ارقام فارسی */
    public function testParsePersianDigits(): void
    {
        $d = JalaliDate::parse('۱۴۰۵/۰۷/۲۳');
        $this->assertSame(1405, $d->year());
        $this->assertSame(7, $d->month());
        $this->assertSame(23, $d->day());

        $this->expectException(\InvalidArgumentException::class);
        JalaliDate::parse('1405/13/01');
    }

    /** addDays/subMonths و مرز سال */
    public function testArithmetic(): void
    {
        $d = JalaliDate::parse('1405/12/28');
        $this->assertSame('1405/12/29', $d->addDays(1)->format('Y/m/d', false));
        $this->assertSame('1406/01/01', $d->addDays(2)->format('Y/m/d', false));   // 1405 غیرکبیسه

        $leap = JalaliDate::parse('1403/12/29');
        $this->assertSame('1403/12/30', $leap->addDays(1)->format('Y/m/d', false));
        $this->assertSame('1404/01/01', $leap->addDays(2)->format('Y/m/d', false));

        $this->assertSame('1405/05/23', JalaliDate::parse('1405/07/23')->subMonths(2)->format('Y/m/d', false));
        // clamp در ماه کوتاه
        $this->assertSame('1405/06/31', JalaliDate::parse('1405/05/31')->addMonths(1)->format('Y/m/d', false));
    }

    /** T-09: diffInDays */
    public function testDiffInDays(): void
    {
        $a = JalaliDate::parse('1405/07/01');
        $b = JalaliDate::parse('1405/07/23');
        $this->assertSame(22, $a->diffInDays($b));
        $this->assertSame(-22, $b->diffInDays($a));
    }

    /** T-10: startOfMonth/endOfMonth */
    public function testMonthBoundaries(): void
    {
        $d = JalaliDate::parse('1405/07/15');
        $this->assertSame('1405/07/01', $d->startOfMonth()->format('Y/m/d', false));
        $this->assertSame('1405/07/30', $d->endOfMonth()->format('Y/m/d', false));
        $this->assertSame('1405/12/29', JalaliDate::parse('1405/12/01')->endOfMonth()->format('Y/m/d', false));
    }

    public function testDayOfYearAndWeek(): void
    {
        $this->assertSame(209, JalaliDate::parse('1405/07/23')->dayOfYear());   // 6×31 + 23
        $this->assertSame(1, JalaliDate::parse('1405/01/01')->dayOfYear());
        $this->assertGreaterThan(0, JalaliDate::parse('1405/07/23')->weekOfYear());
    }

    /** T-08: قمری sanity */
    public function testHijriSanity(): void
    {
        [$hy, $hm, $hd] = Jalali::gregorianToHijri(2026, 10, 15);
        $this->assertSame(1448, $hy);
        $this->assertTrue($hm >= 1 && $hm <= 12);
        $this->assertTrue($hd >= 1 && $hd <= 30);
    }

    /** toDateTimeImmutable */
    public function testToDateTimeImmutable(): void
    {
        $d = JalaliDate::fromGregorian('2026-10-15 12:30:00');
        $dt = $d->toDateTimeImmutable();
        $this->assertSame('2026-10-15', $dt->format('Y-m-d'));
        $this->assertSame([2026, 10, 15], $d->toGregorian());
    }
}
