<?php

declare(strict_types=1);

namespace Tavaan\Engine\Observability\Tracing;

/**
 * تَوان | Tavaan — Observability
 * RateSampler — نمونه‌گیری قطعی بر اساس trace-id.
 *
 * 🔒 قطعی بودن مهم است: همهٔ Spanهای یک trace یکسان تصمیم می‌گیرند
 * (وگر نه trace ناقص صادر می‌شود).
 *
 * @api 1.0
 * @see docs/03-engines/observability.md
 */
final class RateSampler
{
    private const SCALE = 1_000_000;

    private function __construct()
    {
    }

    /**
     * @param string $traceId هگز ۳۲ کاراکتری
     * @param float  $rate    ۰.۰ تا ۱.۰
     */
    public static function shouldSample(string $traceId, float $rate): bool
    {
        if ($rate >= 1.0) {
            return true;
        }
        if ($rate <= 0.0) {
            return false;
        }

        $hex = \substr(\preg_replace('/[^0-9a-fA-F]/', '', $traceId) ?: '0', 0, 8);
        $bucket = \hexdec($hex) % self::SCALE;

        return $bucket < (int) \round($rate * self::SCALE);
    }
}
