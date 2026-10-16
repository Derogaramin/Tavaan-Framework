<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Cache\RepositoryInterface as CacheRepository;
use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Core\Contracts\Tenancy\TenantInterface;
use Tavaan\Engine\Tenancy\Events\TenantQuotaExceeded;
use Tavaan\Engine\Tenancy\Exceptions\TenantNotResolvedException;
use Tavaan\Engine\Tenancy\Exceptions\TenantQuotaExceededException;
use Tavaan\Support\Time\Clock;

/**
 * تَوان | Tavaan — Tenancy
 * QuotaGuard — محدودسازی منابع هر tenant (DOC-028 §6).
 *
 * سهمیه از رکورد tenant: `{"orders_per_day": {"limit": 1000, "period": "day"}}`.
 * شمارش در cache با کلید پنجره‌دار (minute/hour/day/month یا total).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class QuotaGuard
{
    public function __construct(
        private readonly ?CacheRepository $cache = null,
        private readonly ?DispatcherInterface $events = null,
    ) {
    }

    /**
     * بررسی وجود ظرفیت — در صورت پر بودن، TenantQuotaExceededException.
     *
     * @throws TenantQuotaExceededException
     */
    public function assertAvailable(string $resource, int $amount = 1, ?TenantInterface $tenant = null): void
    {
        $tenant ??= $this->currentOrFail();
        $quota = $tenant->quota($resource);
        if ($quota === null) {
            return;   // بدون سهمیه = نامحدود
        }
        if ($this->cache === null) {
            return;   // بدون cache امکان شمارش نیست — fail-open
        }

        $used = $this->usage($resource, $tenant);
        if ($used + $amount > $quota['limit']) {
            $details = ['resource' => $resource, 'limit' => $quota['limit'], 'used' => $used];
            $this->events?->dispatch(new TenantQuotaExceeded($tenant->id(), $resource, $details));

            throw new TenantQuotaExceededException(context: $details);
        }
    }

    /**
     * مصرف سهمیه — افزایش شمارندهٔ پنجرهٔ جاری.
     *
     * @return int مقدار مصرفی فعلی (پس از افزایش)
     */
    public function consume(string $resource, int $amount = 1, ?TenantInterface $tenant = null): int
    {
        $tenant ??= $this->currentOrFail();
        if ($this->cache === null) {
            return $amount;
        }

        $quota = $tenant->quota($resource);
        $period = $quota['period'] ?? null;
        $key = $this->key($tenant, $resource, $period);
        $ttl = $this->ttl($period);

        if (!$this->cache->add($key, $amount, $ttl)) {
            $used = (int) $this->cache->increment($key, $amount);
        } else {
            $used = $amount;
        }

        return $used;
    }

    /** مصرف فعلی در پنجرهٔ جاری. */
    public function usage(string $resource, ?TenantInterface $tenant = null): int
    {
        $tenant ??= $this->currentOrFail();
        if ($this->cache === null) {
            return 0;
        }
        $period = $tenant->quota($resource)['period'] ?? null;

        return (int) $this->cache->get($this->key($tenant, $resource, $period), 0);
    }

    /** بازنشانی شمارنده (مدیریتی/تست). */
    public function reset(string $resource, ?TenantInterface $tenant = null): void
    {
        $tenant ??= $this->currentOrFail();
        if ($this->cache === null) {
            return;
        }
        $period = $tenant->quota($resource)['period'] ?? null;
        $this->cache->forget($this->key($tenant, $resource, $period));
    }

    private function currentOrFail(): TenantInterface
    {
        $current = TenantContext::get();
        if (!$current instanceof TenantInterface) {
            throw new TenantNotResolvedException(context: ['op' => 'quota']);
        }

        return $current;
    }

    private function key(TenantInterface $tenant, string $resource, ?string $period): string
    {
        $safeResource = \preg_replace('/[^A-Za-z0-9_.-]/', '_', $resource) ?? $resource;

        return 'tvn:quota:' . $tenant->id() . ':' . $safeResource . ':' . $this->window($period);
    }

    /** شناسهٔ پنجرهٔ زمانی (بر اساس Clock — testable). */
    private function window(?string $period): string
    {
        $now = Clock::now();

        return match ($period) {
            'minute' => $now->format('YmdHi'),
            'hour' => $now->format('YmdH'),
            'day' => $now->format('Ymd'),
            'month' => $now->format('Ym'),
            default => 'total',
        };
    }

    private function ttl(?string $period): int
    {
        return match ($period) {
            'minute' => 120,
            'hour' => 7200,
            'day' => 172800,
            'month' => 5356800,   // ~۶۲ روز
            default => 0,         // total → forever
        };
    }
}
