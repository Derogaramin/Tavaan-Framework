<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

/**
 * تَوان | Tavaan — Tenancy
 * TenantContext — زمینهٔ جاری tenant (DOC-028 §2.2).
 *
 * holder ایستای ساده با:
 * - `run()` برای اجرای موقت در زمینهٔ tenant دیگر (CLI/Job)
 * - `withoutCurrentScope()` برای عملیات cross-tenant با مجوز + Audit
 * - `clear()` پایان درخواست/Job (TEN-SEC-05)
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantContext
{
    private static ?object $current = null;

    private static bool $bypassing = false;

    private static int $bypassDepth = 0;

    private function __construct()
    {
    }

    public static function set(?object $tenant): void
    {
        self::$current = $tenant;
    }

    public static function get(): ?object
    {
        return self::$current;
    }

    public static function id(): int|string|null
    {
        return self::$current?->id();
    }

    public static function identifier(): ?string
    {
        return self::$current?->identifier();
    }

    public static function has(): bool
    {
        return self::$current !== null;
    }

    /**
     * اجرای موقت در زمینهٔ یک tenant (با بازگردانی قطعی زمینهٔ قبلی).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function run(object $tenant, callable $callback): mixed
    {
        $previous = self::$current;
        self::$current = $tenant;
        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }

    /**
     * اجرای موقت بدون Scope جاری (cross-tenant).
     *
     * 🔒 فراخوان باید مجوز `tenancy.cross_tenant` را بررسی کرده
     * و رویداد Audit صادر کند (در TenantManager انجام می‌شود).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function withoutCurrentScope(callable $callback): mixed
    {
        $previous = self::$current;
        self::$current = null;
        self::$bypassing = true;
        self::$bypassDepth++;
        try {
            return $callback();
        } finally {
            self::$bypassDepth--;
            if (self::$bypassDepth === 0) {
                self::$bypassing = false;
            }
            self::$current = $previous;
        }
    }

    /** آیا Scopeها باید موقتاً کنار گذاشته شوند؟ */
    public static function isBypassing(): bool
    {
        return self::$bypassing;
    }

    public static function clear(): void
    {
        self::$current = null;
        self::$bypassing = false;
        self::$bypassDepth = 0;
    }
}
