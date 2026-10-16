<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Tenancy\TenantInterface;
use Tavaan\Engine\Tenancy\Exceptions\TenantNotResolvedException;

/**
 * تَوان | Tavaan — Tenancy
 * RestoresTenantContext — trait برای Jobهای صف‌روند tenant-aware.
 *
 * ```php
 * final class ProcessOrder implements JobInterface
 * {
 *     use RestoresTenantContext;
 *
 *     public function __construct(private readonly int $orderId)
 *     {
 *         $this->captureTenant();     // در dispatch: ذخیرهٔ tenant جاری
 *     }
 *
 *     public function handle(): void
 *     {
 *         $this->withTenantContext(function (): void {
 *             // … کسب‌وکار در زمینهٔ tenant درست
 *         });
 *     }
 * }
 * ```
 *
 * 🔒 TEN-SEC-06: اگر job بدون tenant dispatch شده باشد، اجرا
 * **fail** می‌شود (نه اجرا با tenant قبلی worker).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
trait RestoresTenantContext
{
    /** شناسهٔ tenant در زمان dispatch (سریال‌پذیر — فقط id). */
    public ?int $tenantContextId = null;

    public ?string $tenantContextIdentifier = null;

    /** ذخیرهٔ tenant جاری در job (هنگام dispatch فراخوانی شود). */
    protected function captureTenant(): void
    {
        $current = TenantContext::get();
        if ($current instanceof TenantInterface) {
            $id = $current->id();
            $this->tenantContextId = $id === null ? null : (int) $id;
            $this->tenantContextIdentifier = $current->identifier();
        }
    }

    /**
     * اجرای callback در زمینهٔ tenant ذخیره‌شده.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function withTenantContext(callable $callback): mixed
    {
        if ($this->tenantContextId === null) {
            throw new TenantNotResolvedException(
                context: ['job' => static::class],
                hint: 'Jobهای tenant-aware باید با captureTenant() در context معتبر dispatch شوند (TEN-SEC-06).',
            );
        }

        $tenant = $this->resolveCapturedTenant();
        if ($tenant === null) {
            throw new TenantNotResolvedException(
                context: ['job' => static::class, 'tenant_id' => $this->tenantContextId],
                hint: 'tenant ذخیره‌شده در job دیگر وجود ندارد.',
            );
        }

        return TenantContext::run($tenant, $callback);
    }

    private function resolveCapturedTenant(): ?TenantInterface
    {
        $container = TenancyResolver::container();
        if ($container === null || !$container->bound('tenant')) {
            return null;
        }
        $manager = $container->make('tenant');
        if (!$manager instanceof TenantManager) {
            return null;
        }

        return $manager->find((int) $this->tenantContextId);
    }
}
