<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * DurationParser — پارس مدت‌زمان ISO-8601/انسانی (DOC-098 §3/§9).
 */

namespace Modules\Bpms\Domain\Services;

use Modules\Bpms\Domain\Exceptions\BpmsException;

/**
 * DurationParser — `P2D`/`PT30M`/`P1DT2H30M` (ISO-8601) یا `2 days`/`+3 hours`
 * (انسانی) → DateInterval. دامنهٔ خالص (بدون ارجاع به موتور — ARCH-003؛
 * هم‌رفتار با Duration موتور workflow).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class DurationParser
{
    private function __construct()
    {
    }

    /**
     * @throws BpmsException invalid_expression (reason=invalid_duration در context)
     */
    public static function parse(string $after): \DateInterval
    {
        $after = \trim($after);
        if ($after === '') {
            throw self::invalid($after);
        }

        // ISO-8601 (P3D / PT4H / P1W / P1DT2H30M)
        if ($after[0] === 'P' || $after[0] === 'p') {
            try {
                return new \DateInterval($after);
            } catch (\Exception) {
                throw self::invalid($after);
            }
        }

        // انسانی: «3 days» / «+2 hours» / «1 week» — فقط واحد زمانی مجاز (🔒 بدون عبارت تاریخ مطلق)
        if (\preg_match('/^\+?\s*(\d+)\s+(second|minute|hour|day|week|month|year)s?$/i', $after, $m) !== 1) {
            throw self::invalid($after);
        }

        $seconds = (int) $m[1] * match (\strtolower((string) $m[2])) {
            'second' => 1,
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            'week' => 604800,
            'month' => 2592000,     // ۳۰ روز
            'year' => 31536000,     // ۳۶۵ روز
            default => throw self::invalid($after),
        };

        return new \DateInterval('PT' . $seconds . 'S');
    }

    /** افزودن مدت به یک لحظه. */
    public static function add(\DateTimeImmutable $from, string $after): \DateTimeImmutable
    {
        return $from->add(self::parse($after));
    }

    /** پارس‌پذیری بدون خطا (برای اعتبارسنجی تعریف). */
    public static function isValid(string $after): bool
    {
        try {
            self::parse($after);

            return true;
        } catch (BpmsException) {
            return false;
        }
    }

    private static function invalid(string $after): BpmsException
    {
        return BpmsException::invalidExpression($after, 'invalid_duration');
    }
}
