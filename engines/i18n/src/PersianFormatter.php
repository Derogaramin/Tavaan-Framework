<?php

declare(strict_types=1);

namespace Tavaan\Engine\I18n;

/**
 * تَوان | Tavaan — I18n
 * PersianFormatter — ارقام/اعداد/پول فارسی (DOC-069 §5).
 *
 * @api 1.0
 * @see docs/03-engines/i18n.md
 */
final class PersianFormatter
{
    /** @var list<string> */
    private const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const FA_DECIMAL = '٫';    // U+066B

    private const FA_THOUSAND = '٬';   // U+066C

    private function __construct()
    {
    }

    public static function digits(string|int|float $value): string
    {
        return \str_replace(\range('0', '9'), self::FA_DIGITS, (string) $value);
    }

    public static function number(float|int $value, int $decimals = 0): string
    {
        $formatted = \number_format($value, $decimals, '.', ',');
        $formatted = \str_replace([',', '.'], [self::FA_THOUSAND, self::FA_DECIMAL], $formatted);

        return self::digits($formatted);
    }

    /**
     * پول = int minor-unit (ADR-0013) → نمایش با جداکنندهٔ فارسی.
     */
    public static function minorUnits(int $amount, int $decimals = 0, string $symbol = 'ریال'): string
    {
        $value = $decimals > 0 ? $amount / (10 ** $decimals) : $amount;

        return self::number($value, $decimals) . ' ' . $symbol;
    }

    public static function toLatinDigits(string $value): string
    {
        return \str_replace(
            [...self::FA_DIGITS, '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            [...\range('0', '9'), ...\range('0', '9')],
            $value,
        );
    }
}
