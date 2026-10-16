<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

/**
 * تَوان | Tavaan — Workflow
 * TransitionFluent — زنجیرهٔ fluent روی یک گذار (guard/action/permission).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class TransitionFluent
{
    /** @internal */
    public function __construct(
        private readonly ProcessBuilder $builder,
        private readonly string $id,
    ) {
    }

    /**
     * افزودن guard — `callable(ProcessContext): bool` یا رشتهٔ `can:<permission>`.
     */
    public function guard(callable|string $guard): self
    {
        $current = $this->builder->transitionDefinition($this->id);
        if ($current !== null) {
            $this->builder->replaceTransition($this->id, new TransitionDefinition(
                id: $current->id,
                from: $current->from,
                to: $current->to,
                guards: [...$current->guards, $guard],
                actions: $current->actions,
                permission: $current->permission,
                meta: $current->meta,
            ));
        }

        return $this;
    }

    /**
     * افزودن action — `callable(ProcessContext): void` یا `event:<name>`.
     */
    public function action(callable|string $action): self
    {
        $current = $this->builder->transitionDefinition($this->id);
        if ($current !== null) {
            $this->builder->replaceTransition($this->id, new TransitionDefinition(
                id: $current->id,
                from: $current->from,
                to: $current->to,
                guards: $current->guards,
                actions: [...$current->actions, $action],
                permission: $current->permission,
                meta: $current->meta,
            ));
        }

        return $this;
    }

    /** مجوز RBAC مستقل (بررسی کاربر جاری — DOC-071 §8). */
    public function permission(string $permission): self
    {
        $current = $this->builder->transitionDefinition($this->id);
        if ($current !== null) {
            $this->builder->replaceTransition($this->id, new TransitionDefinition(
                id: $current->id,
                from: $current->from,
                to: $current->to,
                guards: $current->guards,
                actions: $current->actions,
                permission: $permission,
                meta: $current->meta,
            ));
        }

        return $this;
    }

    /**
     * دادهٔ آزاد Designer.
     *
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): self
    {
        $current = $this->builder->transitionDefinition($this->id);
        if ($current !== null) {
            $this->builder->replaceTransition($this->id, new TransitionDefinition(
                id: $current->id,
                from: $current->from,
                to: $current->to,
                guards: $current->guards,
                actions: $current->actions,
                permission: $current->permission,
                meta: $meta,
            ));
        }

        return $this;
    }
}
