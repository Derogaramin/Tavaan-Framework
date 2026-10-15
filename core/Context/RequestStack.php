<?php

declare(strict_types=1);

namespace Tavaan\Core\Context;

use Tavaan\Engine\Http\Request;

/**
 * تَوان | Tavaan — Core
 * RequestStack — پشتهٔ درخواست‌ها (اصلی + زیردرخواست‌های HMVC).
 *
 * @api 1.0
 * @see docs/02-architecture/request-lifecycle.md
 */
final class RequestStack
{
    /** @var list<Request> */
    private array $stack = [];

    public function push(Request $request): void
    {
        $this->stack[] = $request;
    }

    public function pop(): ?Request
    {
        return \array_pop($this->stack);
    }

    public function current(): ?Request
    {
        $last = \end($this->stack);

        return $last === false ? null : $last;
    }

    public function main(): ?Request
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
