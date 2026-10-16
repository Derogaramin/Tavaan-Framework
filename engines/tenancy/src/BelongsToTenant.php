<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

/**
 * تَوان | Tavaan — Tenancy
 * BelongsToTenant — trait مدل‌های tenant-aware (استراتژی A).
 *
 * ```php
 * final class Order extends Model
 * {
 *     use BelongsToTenant;
 *
 *     protected static function boot(): void
 *     {
 *         self::bootBelongsToTenant();   // Scope + Observer + Guard
 *     }
 * }
 * ```
 *
 * 🔒 `tenant_id` همیشه guarded است — کاربر هرگز نمی‌تواند آن را تغییر دهد
 * (TEN-SEC-02).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
trait BelongsToTenant
{
    /** نام ستون tenant (قابل override در مدل). */
    protected string $tenantColumn = 'tenant_id';

    /** آیا مدل بدون context هم کوئری‌پذیر است؟ (مدل‌های سراسری) */
    protected bool $allowGlobalTenant = false;

    /**
     * ثبت Scope + Observer + افزودن tenant_id به guarded.
     * از boot() مدل فراخوانی شود.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());
        static::observe(new TenantObserver());
    }

    public function isTenantAware(): bool
    {
        return true;
    }

    public function getTenantColumn(): string
    {
        return $this->tenantColumn;
    }

    public function allowGlobalTenant(): bool
    {
        return $this->allowGlobalTenant;
    }

    /**
     * 🔒 Mass Assignment Guard — tenant_id هیچ‌وقت از fill کاربر پر نمی‌شود.
     * (isGuarded در Model این ستون را حتی اگر در fillable باشد رد می‌کند.)
     */
    public function isFillable(string $key): bool
    {
        if ($key === $this->getTenantColumn()) {
            return false;
        }

        return parent::isFillable($key);
    }
}
