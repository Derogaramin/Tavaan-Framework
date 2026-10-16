<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

/**
 * تَوان | Tavaan — Workflow
 * ProcessContext — زمینهٔ اجرای guard/action: متغیرها + ورودی fire + کاربر جاری.
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class ProcessContext
{
    /**
     * @param array<string, mixed> $input ورودی همان fire (موقتی — پس از actionها در متغیرها ادغام می‌شود)
     */
    public function __construct(
        private readonly ProcessInstance $instance,
        private readonly array $input = [],
    ) {
    }

    public function instance(): ProcessInstance
    {
        return $this->instance;
    }

    /** خواندن متغیر (ابتدا ورودی fire، سپس متغیرهای فرایند). */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $this->instance->getVariable($key, $default);
    }

    /** نوشتن متغیر فرایند (پایدار). */
    public function set(string $key, mixed $value): void
    {
        $this->instance->setVariable($key, $value);
    }

    /** @return array<string, mixed> همهٔ متغیرها + ورودی fire */
    public function all(): array
    {
        return [...$this->instance->variables(), ...$this->input];
    }

    /** @return array<string, mixed> */
    public function input(): array
    {
        return $this->input;
    }

    /** کاربر جاری (از گارد auth — ممکن است null باشد: تایمر/سیستم). */
    public function user(): ?object
    {
        return $this->instance->resolveUser();
    }

    /** بررسی مجوز کاربر جاری (RBAC — DOC-071 §8). */
    public function can(string $permission): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if (\method_exists($user, 'can')) {
            return (bool) $user->can($permission);
        }

        $container = WorkflowResolver::container();
        if ($container !== null && $container->bound('gate')) {
            $gate = $container->make('gate');
            if (\is_object($gate) && \method_exists($gate, 'forUser')) {
                return (bool) $gate->forUser($user)->allows($permission);
            }
        }

        return false;
    }

    public function subjectType(): ?string
    {
        return $this->instance->subjectType();
    }

    public function subjectId(): ?string
    {
        return $this->instance->subjectId();
    }

    /**
     * اطلاع‌رسانی به assignee کار جاری (best-effort — رویداد `workflow.task.notify`).
     */
    public function notifyAssignee(): void
    {
        $this->instance->notifyAssignee();
    }
}
