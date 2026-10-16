<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

/**
 * تَوان | Tavaan — Workflow
 * StateDefinition — تعریف تغییرناپذیر یک گره/وضعیت (DOC-071 §2).
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final readonly class StateDefinition
{
    /**
     * @param array<string, mixed> $meta دادهٔ آزاد (form/label/…) برای Designer
     */
    public function __construct(
        public string $id,
        public StateType $type = StateType::Normal,
        /** قالب assignee: `user:<id>` یا `role:<name>` (فقط UserTask) */
        public ?string $assignee = null,
        /** مسیر فرم فرانت (مثلاً `admin/orders/review`) */
        public ?string $form = null,
        /** شناسهٔ فرایند فرزند (فقط SubProcess) */
        public ?string $process = null,
        public array $meta = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            type: StateType::fromString((string) ($data['type'] ?? 'normal')),
            assignee: isset($data['assignee']) ? (string) $data['assignee'] : null,
            form: isset($data['form']) ? (string) $data['form'] : null,
            process: isset($data['process']) ? (string) $data['process'] : null,
            meta: (array) ($data['meta'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return \array_filter([
            'id' => $this->id,
            'type' => $this->type->value,
            'assignee' => $this->assignee,
            'form' => $this->form,
            'process' => $this->process,
            'meta' => $this->meta === [] ? null : $this->meta,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
