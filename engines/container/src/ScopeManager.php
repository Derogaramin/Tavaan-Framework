<?php

declare(strict_types=1);

namespace Tavaan\Engine\Container;

/**
 * تَوان | Tavaan — Container
 * ScopeManager — مدیریت دامنه‌های عمر (Request/Job/Tenant) به‌صورت پشته‌ای.
 *
 * @api 1.0
 * @see docs/03-engines/container.md
 */
final class ScopeManager
{
    /** @var array<string, array<string, object>> scopeId → (abstract → instance) */
    private array $scopes = [];

    /** @var list<string> */
    private array $stack = [];

    public function begin(string $id): void
    {
        $this->scopes[$id] ??= [];
        $this->stack[] = $id;
    }

    public function end(string $id): void
    {
        unset($this->scopes[$id]);
        $key = \array_search($id, $this->stack, true);
        if ($key !== false) {
            \array_splice($this->stack, (int) $key, 1);
        }
    }

    public function current(): ?string
    {
        $last = \end($this->stack);

        return $last === false ? null : $last;
    }

    public function isActive(): bool
    {
        return $this->stack !== [];
    }

    public function store(string $scopeId, string $abstract, object $instance): void
    {
        $this->scopes[$scopeId][$abstract] = $instance;
    }

    public function find(string $abstract): ?object
    {
        foreach (\array_reverse($this->stack) as $id) {
            if (isset($this->scopes[$id][$abstract])) {
                return $this->scopes[$id][$abstract];
            }
        }

        return null;
    }

    /** پاک‌کردن همهٔ Scopeها (پایان درخواست در Resident). */
    public function flushAll(): void
    {
        $this->scopes = [];
        $this->stack = [];
    }

    /** @return list<string> */
    public function activeScopes(): array
    {
        return $this->stack;
    }
}
