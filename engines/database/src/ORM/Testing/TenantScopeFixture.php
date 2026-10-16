<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\ORM\Testing;

use Tavaan\Core\Contracts\Database\QueryBuilderInterface;
use Tavaan\Core\Contracts\ORM\ModelInterface;
use Tavaan\Core\Contracts\ORM\ScopeInterface;

/**
 * Fixture تست ORM — Global Scope چنداجاره‌ای (T-08).
 *
 * @api internal
 */
final class TenantScopeFixture implements ScopeInterface
{
    public static int $currentTenant = 1;

    public function apply(QueryBuilderInterface $query, ModelInterface $model): void
    {
        $query->where('tenant_id', self::$currentTenant);
    }
}
