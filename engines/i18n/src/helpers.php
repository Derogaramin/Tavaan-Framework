<?php

declare(strict_types=1);

/*
 * تَوان | Tavaan — I18n
 * helpers.php — trans()/__()/trans_number()/jalali().
 */

use Tavaan\Core\Contracts\I18n\TranslatorInterface;
use Tavaan\Core\Facade\Facade;
use Tavaan\Engine\I18n\PersianFormatter;

if (!\function_exists('trans')) {
    /**
     * ترجمهٔ کلید (با placeholder).
     *
     * @param array<string, mixed> $replace
     */
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        /** @var TranslatorInterface $t */
        $t = Facade::getFacadeApplication()->make('translator');

        return $t->get($key, $replace, $locale);
    }
}

if (!\function_exists('__')) {
    /** میان‌بر trans(). @param array<string, mixed> $replace */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return \trans($key, $replace, $locale);
    }
}

if (!\function_exists('trans_number')) {
    /** عدد با ارقام/جداکنندهٔ فارسی. */
    function trans_number(float|int $value, int $decimals = 0): string
    {
        return PersianFormatter::number($value, $decimals);
    }
}

if (!\function_exists('jalali')) {
    /** تاریخ میلادی/زمان → رشتهٔ جلالی فرمت‌شده. */
    function jalali(string|int|\DateTimeInterface|null $date = null, string $format = 'Y/m/d'): string
    {
        if ($date === null) {
            return \Tavaan\Engine\I18n\Jalali\JalaliDate::now()->format($format);
        }
        if ($date instanceof \DateTimeInterface) {
            return \Tavaan\Engine\I18n\Jalali\JalaliDate::fromDateTime(
                \Tavaan\Engine\I18n\Jalali\JalaliDate::fromTimestamp($date->getTimestamp())->time,
            )->format($format);
        }
        if (\is_int($date)) {
            return \Tavaan\Engine\I18n\Jalali\JalaliDate::fromTimestamp($date)->format($format);
        }

        return \Tavaan\Engine\I18n\Jalali\JalaliDate::fromGregorian($date)->format($format);
    }
}

if (!\function_exists('is_rtl')) {
    /** آیا locale جاری RTL است؟ */
    function is_rtl(): bool
    {
        /** @var \Tavaan\Engine\I18n\Translator $t */
        $t = Facade::getFacadeApplication()->make('translator');

        return $t->isRtl();
    }
}
