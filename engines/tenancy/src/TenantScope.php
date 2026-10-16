<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Database\QueryBuilderInterface;
use Tavaan\Core\Contracts\ORM\ModelInterface;
use Tavaan\Core\Contracts\ORM\ScopeInterface;
use Tavaan\Engine\Tenancy\Exceptions\TenantNotResolvedException;

/**
 * تَوان | Tavaan — Tenancy
 * TenantScope — Row-Level Scoping خودکار (استراتژی A — DOC-028 §2.4).
 *
 * 🔒 هرگز یک کوئری بدون Scope روی جدول tenant-aware اجرا نمی‌شود:
 * اگر context جاری نباشد (و مدل global-tenant مجاز نباشد) → exception.
 * در حالت `withoutCurrentScope` (با مجوز + Audit) Scope کنار می‌رود.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantScope implements ScopeInterface
{
    public function apply(QueryBuilderInterface $query, ModelInterface $model): void
    {
        // cross-tenant مجاز → بدون where
        if (TenantContext::isBypassing()) {
            return;
        }

        if ($this->allowsGlobal($model)) {
            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $query->where($this->column($model), $tenantId);
            }

            return;
        }

        $tenantId = TenantContext::id();
        if ($tenantId === null) {
            throw new TenantNotResolvedException(
                context: ['table' => \method_exists($model, 'getTable') ? $model->getTable() : ''],
                hint: 'عملیات در TenantContext::run() یا پس از IdentifyTenantMiddleware اجرا شود.',
            );
        }

        $query->where($this->column($model), $tenantId);
    }

    private function column(ModelInterface $model): string
    {
        if (\method_exists($model, 'getTenantColumn')) {
            return (string) $model->getTenantColumn();
        }

        return 'tenant_id';
    }

    private function allowsGlobal(ModelInterface $model): bool
    {
        return \method_exists($model, 'allowGlobalTenant') && (bool) $model->allowGlobalTenant();
    }
}
