<?php

declare(strict_types=1);

namespace Tavaan\Core\Context;

use Tavaan\Core\Contracts\Http\RequestInterface;

/**
 * تَوان | Tavaan — Core
 * RequestStack — پشتهٔ درخواست‌ها (اصلی + زیردرخواست‌های HMVC).
 *
 * 🔒 ممیزی M0–M8: تایپ بر پایهٔ contract هسته (RequestInterface) به‌جای کلاس
 * موتور (Engine\Http\Request) — قانون ARCH-006: هسته هیچ وابستگی به موتورها
 * ندارد (به‌جز composition root در Core\Bootstrap).
 *
 * @api 1.0
 * @see docs/02-architecture/request-lifecycle.md
 */
final class RequestStack
{
    /** @var list<RequestInterface> */
    private array $stack = [];

    public function push(RequestInterface $request): void
    {
        $this->stack[] = $request;
    }

    public function pop(): ?RequestInterface
    {
        return \array_pop($this->stack);
    }

    public function current(): ?RequestInterface
    {
        $last = \end($this->stack);

        return $last === false ? null : $last;
    }

    public function main(): ?RequestInterface
    {
        return $this->stack[0] ?? null;
    }

    public function depth(): int
    {
        return \count($this->stack);
    }

    public function reset(): void
    {
        $this->stack = [];
    }
}
