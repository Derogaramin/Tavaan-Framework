<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Events;

/**
 * تَوان | Tavaan — Tenancy
 * TenantConnected — اتصال DB مخصوص tenant برقرار شد (استراتژی B/C).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantConnected
{
    public function __construct(
        public readonly int|string|null $tenantId,
        public readonly string $strategy,
    ) {
    }
}
