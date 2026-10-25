<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول LMS
 * AnswerNormalizer — نرمال‌سازی پاسخ متنی برای مقایسهٔ خودکار (فارسی‌آگاه).
 */

namespace Modules\Lms\Domain\Services;

/**
 * AnswerNormalizer — یکی‌سازی ی/ک، ارقام فارسی/عربی → لاتین، حذف نیم‌فاصله/
 * فاصله‌های اضافی، lowercase — برای نمره‌دهی خودکار short_answer
 * و مقایسهٔ true_false (DOC-095 §3.3).
 *
 * تصمیم بدون I/O — Unit-testable (الگوی FloodControl انجمن).
 *
 * @api 1.0
 */
final class AnswerNormalizer
{
    private const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const AR_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /** نرمال‌سازی کامل متن پاسخ. */
    public function normalize(string $value): string
    {
        $value = \str_replace(["\u{200C}", "\u{200F}", "\u{200E}"], ' ', $value);   // نیم‌فاصله + LRM/RLM
        $value = \str_replace(self::FA_DIGITS, self::LATIN_DIGITS, $value);
        $value = \str_replace(self::AR_DIGITS, self::LATIN_DIGITS, $value);
        $value = \str_replace(['ي', 'ى'], 'ی', $value);
        $value = \str_replace('ك', 'ک', $value);
        $value = \mb_strtolower(\trim($value));
        $value = (string) \preg_replace('/\s+/u', ' ', $value);

        return \trim($value);
    }

    /** مقایسهٔ دو پاسخ متنی (short_answer). */
    public function textEquals(string $given, string $expected): bool
    {
        $given = $this->normalize($given);
        $expected = $this->normalize($expected);

        if ($given === '' || $expected === '') {
            return false;
        }

        // پذیرش پاسخ‌های چندگانه با جداکننده «|» در پاسخ مرجع
        foreach (\explode('|', $expected) as $variant) {
            if (\trim($variant) !== '' && $given === \trim($variant)) {
                return true;
            }
        }

        return false;
    }

    /** مقایسهٔ true_false — ورودی‌های رایج فرم به true/false. */
    public function booleanEquals(string $given, string $expected): bool
    {
        $map = [
            'true' => 'true', '1' => 'true', 'yes' => 'true', 'صحیح' => 'true', 'درست' => 'true', 'بله' => 'true',
            'false' => 'false', '0' => 'false', 'no' => 'false', 'غلط' => 'false', 'نادرست' => 'false', 'خیر' => 'false',
        ];

        $g = $map[\mb_strtolower(\trim($given))] ?? null;
        $e = $map[\mb_strtolower(\trim($expected))] ?? null;

        return $g !== null && $g === $e;
    }
}
