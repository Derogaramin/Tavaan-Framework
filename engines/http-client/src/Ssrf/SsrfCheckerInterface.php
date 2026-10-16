<?php

declare(strict_types=1);

namespace Tavaan\Engine\HttpClient\Ssrf;

/**
 * تَوان | Tavaan — HttpClient
 * SsrfCheckerInterface — قرارداد بررسی SSRF (DOC-064 §3/§7).
 *
 * پیاده‌سازی‌ها: InternalSsrfGuard (درون‌ساز) و adapter روی
 * `Tavaan\Engine\Security\Ssrf\SsrfGuard` (وابستگی نرم).
 *
 * @api 1.0
 * @see docs/03-engines/http-client.md
 */
interface SsrfCheckerInterface
{
    /** @throws \Tavaan\Core\Exceptions\TavaanException اگر URL مجاز نباشد */
    public function assertAllowed(string $url): void;

    public function isAllowed(string $url): bool;
}
