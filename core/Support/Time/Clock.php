<?php

declare(strict_types=1);

namespace Tavaan\Support\Time;

/**
 * تَوان | Tavaan — Support
 * Clock — منبع زمان یگانه (قابل Fake برای تست).
 *
 * 🔒 در کد فریمورک هرگز مستقیماً از `time()`/`new DateTime()` استفاده نشود؛
 * همیشه Clock تا تستها قطعی بمانند (Test-First، DOC-110).
 *
 * @api 1.0
 * @see docs/04-structure/folder-structure.md
 */
final class Clock
{
    private static ?\DateTimeImmutable $testNow = null;

    private static ?string $timezone = null;

    private function __construct()
    {
    }

    public static function utcNow(): \DateTimeImmutable
    {
        return self::now()->setTimezone(new \DateTimeZone('UTC'));
    }

    public static function now(): \DateTimeImmutable
    {
        if (self::$testNow !== null) {
            return self::$testNow;
        }

        return new \DateTimeImmutable('now', new \DateTimeZone(self::$timezone ?? \date_default_timezone_get()));
    }

    public static function timestamp(): int
    {
        return self::now()->getTimestamp();
    }

    /** ISO-8601 با میکروثانیه (UTC). */
    public static function isoNow(): string
    {
        return self::utcNow()->format('Y-m-d\TH:i:s.uP');
    }

    public static function setTimezone(string $timezone): void
    {
        self::$timezone = $timezone;
    }

    /** Fake برای تست. */
    public static function setTestNow(\DateTimeImmutable|string|null $now): void
    {
        self::$testNow = $now === null
            ? null
            : ($now instanceof \DateTimeImmutable ? $now : new \DateTimeImmutable($now));
    }
}
