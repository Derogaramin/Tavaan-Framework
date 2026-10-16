<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Events;

/**
 * تَوان | Tavaan — Tenancy
 * TenantQuotaExceeded — مصرف از سهمیه گذشت (پیش از exception).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantQuotaExceeded
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly int|string|null $tenantId,
        public readonly string $resource,
        public readonly array $details = [],
    ) {
    }
}
