<?php

declare(strict_types=1);

namespace Tavaan\Engine\Report;

use Tavaan\Engine\I18n\Jalali\Jalali;

/**
 * تَوان | Tavaan — Report
 * Formatter — قالب‌بندی مقدار سلول برای نمایش (تاریخ جلالی/پول/عدد).
 *
 * @api 1.0
 * @see docs/03-engines/report.md
 */
final class Formatter
{
    /**
     * مقدار خام → مقدار نمایشی.
     */
    public function format(mixed $value, Field $field): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($field->isDate()) {
            return $this->formatDate($value, $field->format);
        }

        if ($field->type === 'money') {
            return $this->formatNumber(\is_numeric($value) ? $value + 0 : 0, 0);
        }

        if ($field->type === 'number') {
            $decimals = \is_float($value) ? 2 : 0;

            return $this->formatNumber(\is_numeric($value) ? $value + 0 : 0, $decimals);
        }

        if ($field->type === 'bool') {
            return $value ? '1' : '0';
        }

        return \is_scalar($value) ? (string) $value : (\json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * تاریخ → رشته (جلالی در صورت format=jalali).
     */
    public function formatDate(mixed $value, ?string $format = null): string
    {
        $date = $this->toDate($value);
        if ($date === null) {
            return \is_scalar($value) ? (string) $value : '';
        }

        if ($format === 'jalali') {
            [$jy, $jm, $jd] = Jalali::gregorianToJalali(
                (int) $date->format('Y'),
                (int) $date->format('n'),
                (int) $date->format('j'),
            );

            return \sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        }

        return $date->format($format ?? 'Y-m-d');
    }

    private function toDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (\is_int($value)) {
            return (new \DateTimeImmutable('@' . $value))->setTimezone(new \DateTimeZone('UTC'));
        }
        if (\is_string($value) && $value !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    private function formatNumber(int|float $value, int $decimals): string
    {
        return \number_format($value, $decimals);
    }
}
