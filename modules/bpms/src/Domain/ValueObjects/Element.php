<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * Element — یک گره در گراف فرایند (DOC-098 §3).
 */

namespace Modules\Bpms\Domain\ValueObjects;

use Modules\Bpms\Domain\Enums\ElementType;
use Modules\Bpms\Domain\Enums\TaskPriority;

/**
 * Element — تغییرناپذیر. فیلدها بر اساس نوع:
 * - userTask: assignee (قالب `user:1`/`role:x`/`initiator`)، form (کلید فرم)، due (ISO-8601)
 * - serviceTask: action (نام اکشن ثبت‌شده — هرگز از ورودی کاربر)
 * - exclusiveGateway: conditions (expr/default → next)
 * - parallelGateway: fork (چند next) یا join (چند ورودی)
 * - startEvent/سایر: next
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final readonly class Element
{
    /**
     * @param list<Condition> $conditions
     * @param list<string>    $fork       خروجی‌های parallel gateway (فهرست `next`ها)
     */
    public function __construct(
        public string $id,
        public ElementType $type,
        public ?string $name = null,
        public ?string $assignee = null,
        public ?string $form = null,
        public ?string $due = null,
        public ?string $action = null,
        public ?string $next = null,
        public array $conditions = [],
        public array $fork = [],
        public TaskPriority $priority = TaskPriority::Normal,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $conditions = [];
        foreach ((array) ($data['conditions'] ?? []) as $raw) {
            if (\is_array($raw)) {
                $conditions[] = Condition::fromArray($raw);
            }
        }

        $fork = [];
        $rawFork = $data['fork'] ?? null;
        if (\is_array($rawFork)) {
            foreach ($rawFork as $target) {
                if (\is_string($target) && $target !== '') {
                    $fork[] = $target;
                }
            }
        }

        $next = isset($data['next']) && \is_scalar($data['next']) ? (string) $data['next'] : null;
        $priorityRaw = isset($data['priority']) && \is_scalar($data['priority']) ? (string) $data['priority'] : '';

        return new self(
            id: (string) ($data['id'] ?? ''),
            type: ElementType::tryFromString((string) ($data['type'] ?? '')) ?? ElementType::UserTask,
            name: isset($data['name']) && \is_scalar($data['name']) ? (string) $data['name'] : null,
            assignee: isset($data['assignee']) && \is_scalar($data['assignee']) ? (string) $data['assignee'] : null,
            form: isset($data['form']) && \is_scalar($data['form']) ? (string) $data['form'] : null,
            due: isset($data['due']) && \is_scalar($data['due']) ? (string) $data['due'] : null,
            action: isset($data['action']) && \is_scalar($data['action']) ? (string) $data['action'] : null,
            next: $next !== '' && $next !== null ? $next : null,
            conditions: $conditions,
            fork: $fork,
            priority: $priorityRaw === '' ? TaskPriority::Normal : TaskPriority::fromString($priorityRaw),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $conditions = [];
        foreach ($this->conditions as $condition) {
            $conditions[] = $condition->toArray();
        }

        return \array_filter([
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'assignee' => $this->assignee,
            'form' => $this->form,
            'due' => $this->due,
            'action' => $this->action,
            'next' => $this->next,
            'conditions' => $conditions === [] ? null : $conditions,
            'fork' => $this->fork === [] ? null : $this->fork,
            'priority' => $this->priority === TaskPriority::Normal ? null : $this->priority->value,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /** همهٔ مقصدهای خروجی (next + conditions + fork) — برای شمارش ورودی/خروجی. @return list<string> */
    public function outgoingTargets(): array
    {
        $targets = [];
        if ($this->next !== null && $this->next !== '') {
            $targets[] = $this->next;
        }
        foreach ($this->conditions as $condition) {
            if ($condition->next !== '') {
                $targets[] = $condition->next;
            }
        }
        foreach ($this->fork as $target) {
            $targets[] = $target;
        }

        return $targets;
    }

    public function label(): string
    {
        return $this->name ?? $this->id;
    }
}
