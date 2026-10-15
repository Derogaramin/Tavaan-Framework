# DOC-070 — موتور Jalali | تقویم جلالی

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-070 |
| Engine ID | `tavaan.engine.jalali` |
| Namespace | `Tavaan\Engine\I18n\Jalali` |
| اولویت | **P0** — فاز M2 |
| وابستگی | `i18n` |
| افزونه | `intl` (اختیاری؛ الگوریتم دقیق داخلی دارد) |
| LOC | ۸۰۰ |

## ۱. هدف
تبدیل دقیق و دوطرفهٔ تاریخ **جلالی ↔ میلادی ↔ قمری**، محاسبهٔ سال کبیسه،
نام ماه‌ها، روز هفته، و Date/Time کامل برای فارسی‌زبانان — الگوریتم خالص PHP
(بدون `intl`/`pdatetime`) با دقت ۱۰۰٪.

## ۲. الگوریتم تبدیل (جلالی ↔ میلادی)

### ۲٫۱ الگوریتم مرجع (Ahmad Jalali / jdf)
```php
/**
 * تبدیل میلادی به جلالی — الگوریتم دقیق (بدون نیاز به intl).
 *
 * @return array{0:int,1:int,2:int} [jalaliYear, jalaliMonth, jalaliDay]
 */
public static function gregorianToJalali(int $gy, int $gm, int $gd): array
{
    $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + \intdiv($gy2 + 3, 4) - \intdiv($gy2 + 99, 100)
          + \intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];

    $jy = -1595 + (33 * \intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * \intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += \intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + \intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + \intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

/**
 * تبدیل جلالی به میلادی.
 *
 * @return array{0:int,1:int,2:int} [gregorianYear, gregorianMonth, gregorianDay]
 */
public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (\intdiv($jy, 33) * 8) + \intdiv(($jy % 33) + 3, 4)
          + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);

    $gy = 400 * \intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * \intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) { $days++; }
    }
    $gy += 4 * \intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += \intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    $gd = $days + 1;
    $salA = [0,31,(($gy%4===0 && $gy%100!==0) || ($gy%400===0)) ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
    $gm = 0;
    for ($i = 1; $i <= 12 && $gd > $salA[$i]; $i++) { $gd -= $salA[$i]; $gm = $i; }
    return [$gy, $gm, $gd];
}
```

### ۲٫۲ سال کبیسهٔ جلالی
```php
public static function isLeapJalali(int $jy): bool
{
    // الگوریتم ۳۳‌ساله (دقیق‌تر از ۴‌ساله)
    $mod = (($jy - ($jy > 0 ? 474 : 473)) % 2820) + 474 + 38;
    return ((($mod * 682) % 2816) < 682);
}
public static function jalaliMonthLength(int $jy, int $jm): int
{
    if ($jm <= 6) { return 31; }
    if ($jm <= 11) { return 30; }
    return self::isLeapJalali($jy) ? 30 : 29;   // اسفند
}
```

## ۳. API
```php
$date = JalaliDate::now();                          // امروز
$date = JalaliDate::fromGregorian('2026-10-15');
$date = JalaliDate::parse('1405/07/23');
$date = JalaliDate::fromTimestamp(time());

$date->year(); $date->month(); $date->day();        // 1405, 7, 23
$date->format('Y/m/d H:i');                          // ۱۴۰۵/۰۷/۲۳ ۱۲:۳۰
$date->toGregorian();                                // [2026,10,15]
$date->toDateTimeImmutable();                        // DateTimeImmutable
$date->addDays(10); $date->subMonths(2);
$date->diffInDays($other);
$date->isLeap(); $date->dayOfYear(); $date->weekOfYear();
$date->monthName();    // 'مهر'
$date->dayName();      // 'پنج‌شنبه'
$date->startOfMonth(); $date->endOfMonth();

// DateTime-compatible
Jalali::format($timestamp, 'Y/m/d');
Jalali::toJalali($gy, $gm, $gd);
Jalali::toGregorian($jy, $jm, $jd);
Jalali::isLeap($jy);
Jalali::monthLength($jy, $jm);
```

## ۴. نام ماه‌ها و روزها
```php
public const array MONTHS_FA = [
    1=>'فروردین', 2=>'اردیبهشت', 3=>'خرداد', 4=>'تیر', 5=>'مرداد', 6=>'شهریور',
    7=>'مهر', 8=>'آبان', 9=>'آذر', 10=>'دی', 11=>'بهمن', 12=>'اسفند',
];
public const array DAYS_FA = ['شنبه','یک‌شنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه'];
public const array MONTHS_AF = [1=>'حمل',2=>'ثور',3=>'جوزا',/*… دری/افغانستان */];
```

## ۵. فرمت (مثل `date()`)
| کاراکتر | خروجی |
|---|---|
| `Y` | ۱۴۰۵ |
| `y` | ۰۵ |
| `m` | ۰۷ |
| `n` | ۷ |
| `d` | ۲۳ |
| `j` | ۲۳ |
| `H`/`i`/`s` | ساعت/دقیقه/ثانیه |
| `l` | پنج‌شنبه |
| `F` | مهر |
| `t` | تعداد روز ماه |
| `z` | روز سال |
| `w` | شمارهٔ روز هفته |

🔒 ارقام به‌صورت پیش‌فرض **فارسی** (`PersianFormatter::digits`)؛ با `latin_digits` قابل تغییر.

## ۶. تقویم قمری (Hijri)
```php
public static function gregorianToHijri(int $gy, int $gm, int $gd): array
{
    $jd = self::gregorianToJulianDay($gy, $gm, $gd);
    return self::julianDayToHijri($jd);    // الگوریتم tabular Islamic calendar
}
```
⚠️ تقویم قمری بر اساس رؤیت هلال تفاوت ±۱ روز دارد؛ برای موارد شرعی،
`HijriCalendar` با قابلیت تنظیم `offset` ارائه می‌شود.

## ۷. Julian Day (پل تبدیل)
```php
public static function gregorianToJulianDay(int $y, int $m, int $d): int { /* استاندارد */ }
public static function julianDayToGregorian(int $jd): array { /* … */ }
public static function jalaliToJulianDay(int $jy, int $jm, int $jd): int { /* … */ }
public static function julianDayToJalali(int $jd): array { /* … */ }
```
→ برای تبدیل بین همهٔ تقویم‌ها از Julian Day به‌عنوان پل استفاده می‌شود (دقت بالا).

## ۸. Clock و Timezone
```php
final class Clock
{
    public static function utcNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    public static function now(string $tz = 'Asia/Tehran'): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($tz));
    }
    public static function jalaliNow(): JalaliDate { /* … */ }
}
```
🔒 ذخیره در DB همیشه **UTC**؛ نمایش با timezone کاربر/ Jalali.

## ۹. تست (Vector-Based) 🧪
🔒 این موتور نیازمند تست با **Test Vectors** گسترده است (دقت ۱۰۰٪ حیاتی است).

| T-01 | ۱۰۰۰۰ تاریخ تصادفی: j2g(g2j(d)) == d |
| T-02 | مرزهای کبیسه: ۱۳۹۹ (کبیسه)، ۱۴۰۰، ۱۴۰۳ (کبیسه) |
| T-03 | اسفند کبیسه = ۳۰ روز |
| T-04 | تطبیق با `intl` (در صورت وجود) روی ۱۰۰۰۰ نمونه |
| T-05 | تطبیق با `jdf`/`jalaali-js` روی نمونه‌های مرجع |
| T-06 | نام ماه/روز |
| T-07 | فرمت با ارقام فارسی |
| T-08 | qمری ↔ میلادی |
| T-09 | `diffInDays` دوطرفه |
| T-10 | startOfMonth/endOfMonth |

### ۹٫۱ Test Vectors مرجع
```php
public const array VECTORS = [
    // [gregorian, jalali]
    [[2026,10,15], [1405,7,23]],
    [[2025,3,20], [1404,1,1]],      // نوروز
    [[2024,3,20], [1403,1,1]],
    [[2021,3,21], [1400,1,1]],
    [[2020,3,20], [1399,1,1]],      // ۱۳۹۹ کبیسه
    [[1979,2,11], [1357,11,22]],    // انقلاب
    [[2000,1,1], [1378,10,11]],
];
```

## ۱۰. معیار پذیرش 🧪
- AC-JALALI-01: ۱۰۰۰۰ تبدیل دوطرفه بدون خطا.
- AC-JALALI-02: تطبیق ۱۰۰٪ با `intl` (وقتی موجود است).
- AC-JALALI-03: محاسبهٔ کبیسه مطابق الگوریتم ۳۳‌ساله.
- AC-JALALI-04: همهٔ Test Vectors §9.1 پاس.

## ۱۱. مراجع
| منبع | الهام |
|---|---|
| `jalaali/jalaali-js` | الگوریتم j2g/g2j (پورت دقیق به PHP) |
| jdf.ir | الگوریتم احمد جلالی |
| `PHP intl` (IntlCalendar) | تأیید دقت |
| Persian Calendar (Android) | Test Vectors |

## ۱۲. بلوک ماشین‌خوان 🧠
```yaml
# machine-readable: tavaan.engine.jalali.v1
engine:
  id: tavaan.engine.jalali
  namespace: Tavaan\Engine\I18n\Jalali
  path: engines/jalali
  priority: P0
  phase: M2
  depends_on: [i18n]
  extensions: {required: [], optional: [{name: intl, fallback: native}]}
  calendars: [jalali, gregorian, hijri]
  public_api:
    - class: JalaliDate
      methods: [now, fromGregorian, parse, toGregorian, format, addDays, subMonths,
                diffInDays, isLeap, monthName, dayName, startOfMonth, endOfMonth]
    - class: Jalali
      methods: [toJalali, toGregorian, isLeap, monthLength, format]
    - class: Clock
      methods: [utcNow, now, jalaliNow]
  loc_estimate: 800
  acceptance:
    - {id: AC-JALALI-01, test: 'engines/jalali/tests/Unit/ConversionTest.php'}
    - {id: AC-JALALI-02, test: 'engines/jalali/tests/Unit/IntlParityTest.php'}
```
