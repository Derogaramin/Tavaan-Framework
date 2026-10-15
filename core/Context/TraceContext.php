<?php

declare(strict_types=1);

namespace Tavaan\Core\Context;

/**
 * تَوان | Tavaan — Core
 * TraceContext — شناسه‌های ردیابی درخواست (W3C Trace Context lite).
 *
 * نسخهٔ M1: holder ایستای ساده. در M2+ با Fiber/RequestStack ادغام می‌شود
 * (ADR-0011 — Fiber Context).
 *
 * @api 1.0
 * @see docs/02-architecture/request-lifecycle.md
 */
final class TraceContext
{
    private static ?string $traceId = null;

    private static ?string $spanId = null;

    private static ?string $requestId = null;

    private function __construct()
    {
    }

    public static function traceId(): string
    {
        return self::$traceId ??= \bin2hex(\random_bytes(16));
    }

    public static function spanId(): string
    {
        return self::$spanId ??= \bin2hex(\random_bytes(8));
    }

    public static function requestId(): string
    {
        return self::$requestId ??= 'req_' . \bin2hex(\random_bytes(4));
    }

    public static function setTrace(string $traceId, string $spanId): void
    {
        self::$traceId = $traceId;
        self::$spanId = $spanId;
    }

    public static function newSpan(): string
    {
        return self::$spanId = \bin2hex(\random_bytes(8));
    }

    /** ریست برای تست/شروع درخواست جدید در حالت Resident. */
    public static function reset(): void
    {
        self::$traceId = null;
        self::$spanId = null;
        self::$requestId = null;
    }
}
