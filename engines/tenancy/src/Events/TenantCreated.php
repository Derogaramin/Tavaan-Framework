<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy\Events;

use Tavaan\Core\Contracts\Tenancy\TenantInterface;

/**
 * تَوان | Tavaan — Tenancy
 * TenantCreated — رکورد tenant در DB مرکزی ساخته شد.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantCreated
{
    public function __construct(
        public readonly int|string|null $tenantId,
        public readonly string $identifier,
        public readonly ?TenantInterface $tenant = null,
    ) {
    }
}
