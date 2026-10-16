<?php

declare(strict_types=1);

namespace Tavaan\Engine\Tenancy;

use Tavaan\Core\Contracts\Config\RepositoryInterface as ConfigRepository;
use Tavaan\Core\Contracts\Tenancy\TenantInterface;

/**
 * تَوان | Tavaan — Tenancy
 * TenantConfigRepository — overlay تنظیمات per-tenant روی config پایه (DOC-028 §7).
 *
 * اولویت: Tenant Settings → App Config → Framework Defaults.
 * فقط `get/has` overlay می‌شوند؛ نوشتن همیشه روی config پایه است.
 *
 * @api 1.0
 * @see docs/03-engines/tenancy.md
 */
final class TenantConfigRepository implements ConfigRepository
{
    private const SENTINEL = '__TVN_MISSING__';

    public function __construct(private readonly ConfigRepository $base)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $tenant = TenantContext::get();
        if ($tenant instanceof TenantInterface) {
            $value = $tenant->setting($key, self::SENTINEL);
            if ($value !== self::SENTINEL) {
                return $value;
            }
        }

        return $this->base->get($key, $default);
    }

    public function set(array|string $key, mixed $value = null): void
    {
        $this->base->set($key, $value);
    }

    public function has(string $key): bool
    {
        $tenant = TenantContext::get();
        if ($tenant instanceof TenantInterface && $tenant->setting($key, self::SENTINEL) !== self::SENTINEL) {
            return true;
        }

        return $this->base->has($key);
    }

    public function missing(string $key): bool
    {
        return !$this->has($key);
    }

    public function forget(string $key): void
    {
        $this->base->forget($key);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->base->forget($key);

        return $value;
    }

    public function push(string $key, mixed $value): void
    {
        $this->base->push($key, $value);
    }

    public function prepend(string $key, mixed $value): void
    {
        $this->base->prepend($key, $value);
    }

    public function all(): array
    {
        $all = $this->base->all();
        $tenant = TenantContext::get();
        if ($tenant instanceof TenantInterface) {
            $all['tenant'] = $tenant->settings();
        }

        return $all;
    }

    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get((string) $key);
        }

        return $out;
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        foreach ($keys as $key) {
            unset($all[(string) $key]);
        }

        return $all;
    }

    public function namespace(string $ns): array
    {
        return $this->base->namespace($ns);
    }

    public function freeze(): void
    {
        $this->base->freeze();
    }

    public function isFrozen(): bool
    {
        return $this->base->isFrozen();
    }

    public function toJson(bool $maskSecrets = true): string
    {
        return $this->base->toJson($maskSecrets);
    }

    public function requiredKeys(array $keys): void
    {
        $this->base->requiredKeys($keys);
    }

    /** config پایه (برای نوشتن‌های مدیریتی). */
    public function base(): ConfigRepository
    {
        return $this->base;
    }
}
