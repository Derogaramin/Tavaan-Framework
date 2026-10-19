<?php

declare(strict_types=1);

namespace Tavaan\Engine\Admin;

use Tavaan\Engine\Rbac\Gate;

/**
 * تَوان | Tavaan — Admin
 * Action — عملیات دستی/گروهی روی رکوردها (DOC-078 §2).
 *
 * ```php
 * Action::make('publish')->permission('shop.product.publish')->handler(fn ($m) => $m->update(['status' => 'active']));
 * Action::bulk('delete')->permission('shop.product.delete');   // handler پیش‌فرض bulk delete
 * ```
 *
 * @api 1.0
 * @see docs/03-engines/admin.md
 */
final class Action
{
    private string $label;

    private ?string $permission = null;

    /** @var \Closure|null fn(object $model, array $context): mixed */
    private ?\Closure $handler = null;

    private bool $confirm = false;

    private string $style = 'secondary';

    private function __construct(
        public readonly string $name,
        public readonly bool $bulk = false,
    ) {
        $this->label = \ucfirst(\str_replace(['_', '-'], ' ', $name));
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public static function bulk(string $name): self
    {
        return new self($name, bulk: true);
    }

    public function permission(?string $permission): self
    {
        $clone = clone $this;
        $clone->permission = $permission;

        return $clone;
    }

    /** @param \Closure(object, array<string, mixed>): mixed $handler */
    public function handler(\Closure $handler): self
    {
        $clone = clone $this;
        $clone->handler = $handler;

        return $clone;
    }

    public function label(string $label): self
    {
        $clone = clone $this;
        $clone->label = $label;

        return $clone;
    }

    public function confirm(bool $confirm = true): self
    {
        $clone = clone $this;
        $clone->confirm = $confirm;

        return $clone;
    }

    /** primary|secondary|danger */
    public function style(string $style): self
    {
        $clone = clone $this;
        $clone->style = \in_array($style, ['primary', 'secondary', 'danger'], true) ? $style : 'secondary';

        return $clone;
    }

    // ─── getters ──────────────────────────────────────────────────────────

    public function labelText(): string
    {
        return $this->label;
    }

    public function permissionName(): ?string
    {
        return $this->permission;
    }

    public function needsConfirm(): bool
    {
        return $this->confirm;
    }

    public function styleName(): string
    {
        return $this->style;
    }

    public function isAllowed(Gate $gate): bool
    {
        return $this->permission === null || $gate->allows($this->permission);
    }

    /**
     * اجرای handler — `delete` بدون handler به‌صورت پیش‌فرض رکورد را حذف می‌کند.
     *
     * @param object               $model
     * @param array<string, mixed> $context
     */
    public function run(object $model, array $context = []): mixed
    {
        if ($this->handler !== null) {
            return ($this->handler)($model, $context);
        }

        if ($this->name === 'delete' && \method_exists($model, 'delete')) {
            return $model->delete();
        }

        throw new \LogicException(\sprintf('Action [%s] has no handler.', $this->name));
    }
}
