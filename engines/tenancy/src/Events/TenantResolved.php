<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Events;

/**
 * تَوان | Tavaan — Tenancy
 * TenantResolved — tenant جاری یک درخواست/عملیات تعیین شد.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantResolved
{
    public function __construct(
        public readonly int|string|null $tenantId,
        public readonly string $identifier,
        public readonly string $via = 'chain',
    ) {
    }
}
