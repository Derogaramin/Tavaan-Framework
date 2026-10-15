<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Container;

/**
 * قرارداد سازگار با PSR-11 (بدون پکیج psr/container — ADR-0004).
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
interface PsrContainerInterface
{
    /** @throws \Tavaan\Engine\Container\Exceptions\BindingResolutionException اگر یافت نشود */
    public function get(string $id): mixed;

    public function has(string $id): bool;
}
