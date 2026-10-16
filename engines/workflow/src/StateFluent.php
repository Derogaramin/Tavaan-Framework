<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

/**
 * تَوان | Tavaan — Workflow
 * StateFluent — زنجیرهٔ fluent روی یک state (DOC-071 §3).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class StateFluent
{
    /** @internal */
    public function __construct(
        private readonly ProcessBuilder $builder,
        private readonly string $id,
    ) {
    }

    /** assignee کار انسانی: `user:<id>` یا `role:<name>`. */
    public function assignee(string $assignee): self
    {
        return $this->with(assignee: $assignee);
    }

    /** مسیر فرم فرانت. */
    public function form(string $form): self
    {
        return $this->with(form: $form);
    }

    /**
     * دادهٔ آزاد Designer.
     *
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): self
    {
        return $this->with(meta: $meta);
    }

    private function with(?string $assignee = null, ?string $form = null, ?array $meta = null): self
    {
        $current = $this->builder->stateDefinition($this->id);
        if ($current !== null) {
            $this->builder->replaceState($this->id, new StateDefinition(
                id: $current->id,
                type: $current->type,
                assignee: $assignee ?? $current->assignee,
                form: $form ?? $current->form,
                process: $current->process,
                meta: $meta ?? $current->meta,
            ));
        }

        return $this;
    }
}
