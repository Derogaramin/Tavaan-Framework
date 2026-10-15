<?php

declare(strict_types=1);

namespace Tavaan\Core\Provider;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * تَوان | Tavaan — Core
 * ServiceProvider — پایهٔ ثبت سرویس‌ها در کانتینر.
 *
 * 🔒 register(): فقط bind — بدون side effect.
 * ⚠️ boot(): پس از ثبت همهٔ Providerها (می‌تواند به سرویس‌ها وابسته باشد).
 *
 * @api 1.0
 * @see docs/02-architecture/container-di.md
 */
abstract class ServiceProvider
{
    /** @var list<string> مسیرهای publish (asset/config) */
    protected array $publishes = [];

    public function __construct(protected readonly ContainerInterface $app)
    {
    }

    /** ثبت bindingها (بدون side effect). */
    public function register(): void
    {
    }

    /** پس از ثبت همهٔ Providerها اجرا می‌شود. */
    public function boot(): void
    {
    }

    /** کارهای پایانی (flush بافرها و …). */
    public function shutdown(): void
    {
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [];
    }

    public function isDeferrable(): bool
    {
        return false;
    }
}
