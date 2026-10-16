<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Cache\StoreInterface;

/**
 * تَوان | Tavaan — Tenancy
 * TenantScopeStore — دکوراتور Store با کلید tenant-scoped (DOC-028 §5).
 *
 * همهٔ کلیدها با `tvn:t{tenantId}:` (یا `tvn:tg:` بدون context) prefix
 * می‌شوند — جداسازی کامل cache بین tenantها (TEN-SEC-03).
 *
 * از طریق `Cache::store('tenant')` در دسترس است (driver ثبت‌شده توسط
 * TenancyServiceProvider).
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantScopeStore implements StoreInterface
{
    public function __construct(private readonly StoreInterface $inner)
    {
    }

    public function get(string $key): mixed
    {
        return $this->inner->get($this->key($key));
    }

    public function many(array $keys): array
    {
        $mapped = [];
        foreach ($keys as $k) {
            $mapped[$this->key((string) $k)] = (string) $k;
        }
        $values = $this->inner->many(\array_keys($mapped));
        $out = [];
        foreach ($mapped as $scoped => $original) {
            $out[$original] = $values[$scoped] ?? null;
        }

        return $out;
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->inner->put($this->key($key), $value, $ttl);
    }

    public function putMany(array $values, int $ttl = 0): bool
    {
        $mapped = [];
        foreach ($values as $k => $v) {
            $mapped[$this->key((string) $k)] = $v;
        }

        return $this->inner->putMany($mapped, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($this->key($key));
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->inner->increment($this->key($key), $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->inner->decrement($this->key($key), $value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->inner->forever($this->key($key), $value);
    }

    public function add(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->inner->add($this->key($key), $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->inner->forget($this->key($key));
    }

    public function flush(): bool
    {
        // ⚠️ flush سراسری — scoped flush عمومی در storeها ممکن نیست
        return $this->inner->flush();
    }

    public function getPrefix(): string
    {
        return $this->inner->getPrefix() . $this->scopePrefix();
    }

    /** کلید scope‌شده برای tenant جاری. */
    public function key(string $key): string
    {
        return $this->scopePrefix() . $key;
    }

    /** 🔒 identifier خام در کلید نمی‌آید — فقط id سانیتایزشده. */
    private function scopePrefix(): string
    {
        $id = TenantContext::id();
        $scope = $id === null
            ? 'tg'
            : 't' . (\preg_replace('/[^A-Za-z0-9_-]/', '', (string) $id) ?: 'x');

        return 'tvn:' . $scope . ':';
    }
}
