<?php

declare(strict_types=1);

namespace Tavaan\Engine\Compat;

/**
 * تَوان | Tavaan — Compat
 * Utf8 — عملیات UTF-8 با fallback خالص (بدون mbstring).
 *
 * @api 1.0
 * @see docs/03-engines/compat.md
 */
final class Utf8
{
    private function __construct()
    {
    }

    public static function length(string $s): int
    {
        if (\function_exists('mb_strlen')) {
            return \mb_strlen($s, 'UTF-8');
        }

        return \preg_match_all('/./us', $s) ?: 0;
    }

    public static function substr(string $s, int $start, ?int $length = null): string
    {
        if (\function_exists('mb_substr')) {
            return \mb_substr($s, $start, $length, 'UTF-8');
        }

        $pattern = $length === null
            ? '/^.{' . \max(0, $start) . '}(.*)$/us'
            : '/^.{' . \max(0, $start) . '}(.{0,' . \max(0, $length) . '})/us';

        return \preg_match($pattern, $s, $m) === 1 ? $m[1] : '';
    }

    /** @return list<string> */
    public static function split(string $s): array
    {
        if (\function_exists('mb_str_split')) {
            return \mb_str_split($s, 1, 'UTF-8');
        }
        \preg_match_all('/./us', $s, $m);

        return $m[0];
    }

    public static function lower(string $s): string
    {
        if (\function_exists('mb_strtolower')) {
            return \mb_strtolower($s, 'UTF-8');
        }

        return \strtr($s, [
            'آ' => 'ا', 'إ' => 'ا', 'أ' => 'ا', 'ؤ' => 'و', 'ئ' => 'ی', 'ة' => 'ه',
            'پ' => 'پ', 'ی' => 'ی', 'ک' => 'ک', 'ۀ' => 'ه',
        ]) === $s ? \strtolower($s) : \strtolower(\strtr($s, ['آ' => 'ا', 'إ' => 'ا', 'أ' => 'ا']));
    }

    public static function upper(string $s): string
    {
        return \function_exists('mb_strtoupper') ? \mb_strtoupper($s, 'UTF-8') : \strtoupper($s);
    }

    public static function isValid(string $s): bool
    {
        if (\function_exists('mb_check_encoding')) {
            return \mb_check_encoding($s, 'UTF-8');
        }

        return \preg_match('//u', $s) === 1;
    }

    public static function normalize(string $s, string $form = 'NFC'): string
    {
        if (\class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($s, \constant('Normalizer::FORM_' . $form));

            return $normalized === false ? $s : $normalized;
        }

        // ⚠️ بدون intl نرمال‌سازی کامل ممکن نیست — ی/ک عربی↔فارسی در حد نیاز یکدست می‌شود
        return \strtr($s, ["\u{064A}" => "\u{06CC}", "\u{0643}" => "\u{06A9}"]);
    }

    public static function pad(string $s, int $length, string $pad = ' ', bool $left = true): string
    {
        $diff = $length - self::length($s);
        if ($diff <= 0) {
            return $s;
        }

        $padding = \str_repeat($pad, (int) \ceil($diff / \max(1, self::length($pad))));
        $padding = self::substr($padding, 0, $diff);

        return $left ? $padding . $s : $s . $padding;
    }

    /** تبدیل ارقام فارسی/عربی → لاتین. */
    public static function digitsToLatin(string $s): string
    {
        return \strtr($s, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /** تبدیل ارقام لاتین → فارسی. */
    public static function digitsToPersian(string $s): string
    {
        return \strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}
