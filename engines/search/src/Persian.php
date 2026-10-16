<?php

declare(strict_types=1);

namespace Tavaan\Engine\Search;

/**
 * تَوان | Tavaan — Search
 * Persian — نرمال‌سازی و stemming واژهٔ فارسی (DOC-052 §4).
 *
 * 🔒 نرمال‌سازی فارسی حیاتی است: یکسان‌سازی ي/ی و ك/ک، حذف اعراب،
 * حذف نیم‌فاصله، یکسان‌سازی ارقام.
 *
 * @api 1.0
 * @see docs/03-engines/search.md
 */
final class Persian
{
    /**
     * پسوندهای رایج فارسی — به ترتیب بلندترین اول.
     * 🔒 هر پسوند در دو فرم فارسی (ی) و عربی (ي) آمده، چون متن پیش از
     * stemming با Persian::normalize یکسان‌سازی می‌شود (ی→ي).
     */
    private const SUFFIXES = [
        'هایی', 'هایم', 'هایت', 'ترین',
        'هايي', 'هايم', 'هايت', 'ترين',
        'های', 'گری', 'شان', 'مان', 'تان', 'اند',
        'هاي', 'گري',
        'ها', 'تر', 'یم', 'ید', 'گر',
        'يم', 'يد',
        'م', 'ت', 'ش',
    ];

    /** واژه‌های ایست (پس از نرمال‌سازی مقایسه می‌شوند) */
    private const STOP_WORDS = [
        'از', 'با', 'در', 'به', 'که', 'این', 'آن', 'یک', 'تا', 'برای', 'روی', 'هر',
        'نیز', 'و', 'یا', 'اما', 'اگر', 'چون', 'هنگام', 'هیچ', 'دیگر', 'هم', 'نه',
        'را', 'چه', 'کی', 'کجا', 'چگونه', 'چرا', 'کدام', 'است', 'هستند', 'بود',
        'بودند', 'شد', 'شدند', 'کرد', 'کردند', 'دارد', 'دارند', 'داشت', 'داشتند',
        'خواهد', 'خواهند', 'می', 'نمی', 'ها', 'های', 'همین', 'همان', 'پس', 'قبل',
        'بعد', 'بین', 'بدون', 'مگر', 'ولی', 'بلکه', 'زیرا', 'اند', 'ای', 'هایم',
        'هایت', 'مان', 'تان', 'شان', 'بودم', 'بودی', 'هستم', 'هستی', 'باش', 'باشد',
        'کن', 'کند', 'کنند', 'کردم', 'کردی', 'ده', 'دهد', 'دهند', 'وی', 'ایشان',
    ];

    /** @var array<string, true>|null کش واژه‌های ایست نرمال‌شده */
    private static ?array $normalizedStopWords = null;

    private function __construct()
    {
    }

    /**
     * نرمال‌سازی متن فارسی:
     * - یکسان‌سازی ی/ي و ک/ك (به سمت فرم عربی — DOC-052 §4)
     * - حذف اعراب و تحریر (tatweel)
     * - حذف نیم‌فاصله (ZWNJ) و ZWJ
     * - یکسان‌سازی ارقام فارسی/عربی → لاتین
     * - تبدیل ة/ۀ → ه، آ/أ/إ → ا
     */
    public static function normalize(string $text): string
    {
        // ارقام → لاتین
        $text = \strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        // حروف هم‌ارز
        $text = \strtr($text, [
            'ی' => 'ي',   // ی → ي
            'ک' => 'ك',   // ک → ك
            'ة' => 'ه',
            'ۀ' => 'ه',
            'آ' => 'ا',
            'أ' => 'ا',
            'إ' => 'ا',
            'ؤ' => 'و',
        ]);

        // حذف اعراب (U+064B..U+065F, U+0670) + تحریر (U+0640)
        $text = (string) \preg_replace('/[\x{0640}\x{064B}-\x{065F}\x{0670}]/u', '', $text);

        // حذف نیم‌فاصله (U+200C) و ZWJ (U+200D)
        $text = \str_replace(["\u{200C}", "\u{200D}"], '', $text);

        return $text;
    }

    /** حذف پسوند رایج (یک‌مرحله‌ای، با محافظ طول ریشه). */
    public static function stem(string $word): string
    {
        foreach (self::SUFFIXES as $suffix) {
            if (\mb_strlen($word) > \mb_strlen($suffix) + 2 && \str_ends_with($word, $suffix)) {
                return \mb_substr($word, 0, -\mb_strlen($suffix));
            }
        }

        return $word;
    }

    /**
     * نرمال‌سازی + تفکیک + stemming + حذف ایست‌واژه‌ها.
     *
     * @return list<string>
     */
    public static function tokenize(string $text, int $minLength = 2): array
    {
        $out = [];
        foreach (self::tokenizeDetailed($text, $minLength) as [$raw, $stemmed]) {
            $out[] = $stemmed;
        }

        return $out;
    }

    /**
     * مانند tokenize اما جفت (خام، stem‌شده) برمی‌گرداند —
     * برای display-map در suggest.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function tokenizeDetailed(string $text, int $minLength = 2): array
    {
        $text = self::normalize($text);
        $text = \mb_strtolower($text);
        $text = (string) (\preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '');
        $raw = \preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stop = self::stopWords();
        $out = [];
        foreach ($raw as $token) {
            if (\mb_strlen($token) < $minLength) {
                continue;
            }
            $stemmed = self::stem($token);
            if ($stemmed === '' || \mb_strlen($stemmed) < $minLength) {
                continue;
            }
            if (isset($stop[$stemmed]) || isset($stop[$token])) {
                continue;
            }
            $out[] = [$token, $stemmed];
        }

        return $out;
    }

    /** @return array<string, true> */
    public static function stopWords(): array
    {
        if (self::$normalizedStopWords === null) {
            $set = [];
            foreach (self::STOP_WORDS as $w) {
                $n = \mb_strtolower(self::normalize($w));
                $set[$n] = true;
            }
            self::$normalizedStopWords = $set;
        }

        /** @var array<string, true> */
        return self::$normalizedStopWords;
    }

    /**
     * فاصلهٔ Levenshtein سازگار با UTF-8 (برای suggest).
     * 🔒 طول ورودی‌ها محدود می‌شود (DoS).
     */
    public static function mbLevenshtein(string $a, string $b, int $maxLength = 24): int
    {
        $a = \mb_substr($a, 0, $maxLength);
        $b = \mb_substr($b, 0, $maxLength);
        $la = \mb_strlen($a);
        $lb = \mb_strlen($b);
        if ($la === 0) {
            return $lb;
        }
        if ($lb === 0) {
            return $la;
        }

        $prev = \range(0, $lb);
        $curr = \array_fill(0, $lb + 1, 0);
        for ($i = 1; $i <= $la; $i++) {
            $curr[0] = $i;
            $ca = \mb_substr($a, $i - 1, 1);
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $ca === \mb_substr($b, $j - 1, 1) ? 0 : 1;
                $curr[$j] = \min($prev[$j] + 1, $curr[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            [$prev, $curr] = [$curr, $prev];
        }

        return (int) $prev[$lb];
    }
}
