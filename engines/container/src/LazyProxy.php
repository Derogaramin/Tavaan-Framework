<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container;

use Tavaan\Core\Contracts\Container\ContainerInterface;

/**
 * تَوان | Tavaan — Container
 * LazyProxy — Proxy تنبل بدون eval (ADR-0016).
 *
 * اولین فراخوانی متد/property باعث resolve شدن سرویس واقعی می‌شود.
 * ⚠️ برای Interfaceهای تایپ‌شده (type-hint) مناسب نیست؛ برای مصارف dynamic
 * و تولید فایل Proxy در `storage/cache/proxies/` (نقشهٔ 1.1) طراحی شده.
 *
 * @api 1.0
 * @see docs/adr/ADR-0016-lazy-proxy-no-eval.md
 */
final class LazyProxy
{
    private ?object $instance = null;

    public function __construct(
        private readonly string $abstract,
        private readonly ContainerInterface $container,
    ) {
    }

    /** resolve و بازگرداندن نمونهٔ واقعی. */
    public function resolve(): object
    {
        return $this->instance ??= $this->container->make($this->abstract);
    }

    public function resolved(): bool
    {
        return $this->instance !== null;
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->resolve()->{$method}(...$arguments);
    }

    public function __get(string $name): mixed
    {
        return $this->resolve()->{$name};
    }

    public function __set(string $name, mixed $value): void
    {
        $this->resolve()->{$name} = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->resolve()->{$name});
    }
}
