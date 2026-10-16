<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\ORM\ModelInterface;
use Tavaan\Engine\Tenancy\Exceptions\TenantNotResolvedException;

/**
 * تَوان | Tavaan — Tenancy
 * TenantObserver — پرکردن خودکار tenant_id در insert (DOC-082E §6).
 *
 * 🔒 اگر context نباشد → Fail (نه اجرا با tenant قبلی — TEN-SEC-06).
 * 🔒 tenant_id از ورودی کاربر پر نمی‌شود (Mass Assignment Guard —
 * در trait BelongsToTenant ستون tenant_id همیشه guarded است).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantObserver
{
    public function creating(ModelInterface $model): void
    {
        if (!\method_exists($model, 'isTenantAware') || !$model->isTenantAware()) {
            return;
        }

        $column = \method_exists($model, 'getTenantColumn') ? (string) $model->getTenantColumn() : 'tenant_id';
        if ($model->getAttribute($column) !== null) {
            return;   // صریحاً ست شده (مثلاً provisioning)
        }

        if (TenantContext::isBypassing()) {
            return;   // در حالت cross-tenant، پرکردن خودکار معنا ندارد
        }

        $tenantId = TenantContext::id();
        if ($tenantId === null) {
            // مدل‌های سراسری (allowGlobalTenant) بدون context هم insert می‌شوند
            if (\method_exists($model, 'allowGlobalTenant') && (bool) $model->allowGlobalTenant()) {
                return;
            }

            throw new TenantNotResolvedException(
                context: ['model' => $model::class],
                hint: 'ایجاد رکورد tenant-aware باید داخل TenantContext انجام شود.',
            );
        }

        $model->setAttribute($column, $tenantId);
    }
}
