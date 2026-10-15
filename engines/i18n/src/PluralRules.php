<?php

declare(strict_types=1);

namespace Tavaan\Engine\I18n;

/**
 * تَوان | Tavaan — I18n
 * PluralRules — قواعد جمع‌بندی بدون intl (DOC-069 §4.1).
 *
 * @api 1.0
 * @see docs/03-engines/i18n.md
 */
final class PluralRules
{
    /**
     * ایندکس حالت جمع برای عدد در زبان داده‌شده.
     */
    public function indexFor(int $number, string $locale): int
    {
        $lang = \substr($locale, 0, 2);

        return match ($lang) {
            'fa', 'en' => $number === 1 ? 0 : 1,
            'ar' => match (true) {
                $number === 0 => 0,
                $number === 1 => 1,
                $number === 2 => 2,
                $number % 100 >= 3 && $number % 100 <= 10 => 3,
                $number % 100 >= 11 => 4,
                default => 5,
            },
            'ru' => match (true) {
                $number % 10 === 1 && $number % 100 !== 11 => 0,
                $number % 10 >= 2 && $number % 10 <= 4 && ($number % 100 < 10 || $number % 100 >= 20) => 1,
                default => 2,
            },
            default => $number === 1 ? 0 : 1,
        };
    }
}
