<?php

declare(strict_types=1);

namespace Tavaan\Core\Context;

/**
 * تَوان | Tavaan — Core
 * IdentityContext — هویت جاری (user/tenant) برای Processorهای لاگ و Observability.
 *
 * نسخهٔ M1: holder ایستای ساده؛ در M4 با موتور auth و در M6 با tenancy یکپارچه می‌شود.
 *
 * @api 1.0
 * @see docs/02-architecture/request-lifecycle.md
 */
final class IdentityContext
{
    private static ?string $userId = null;

    private static ?string $tenantId = null;

    private function __construct()
    {
    }

    public static function userId(): ?string
    {
        return self::$userId;
    }

    public static function tenantId(): ?string
    {
        return self::$tenantId;
    }

    public static function setUser(?string $userId): void
    {
        self::$userId = $userId;
    }

    public static function setTenant(?string $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function reset(): void
    {
        self::$userId = null;
        self::$tenantId = null;
    }
}
