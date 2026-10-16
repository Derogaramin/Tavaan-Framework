<?php

declare(strict_types=1);

namespace Tavaan\Engine\Workflow;

use Tavaan\Engine\Workflow\Exceptions\TaskException;

/**
 * تَوان | Tavaan — Workflow
 * Task — کار انسانی (DOC-071 §7): claim/complete/delegate.
 *
 * @api 1.0
 * @see docs/03-engines/workflow.md
 */
final class Task
{
    /** @internal — توسط Store/Manager ساخته می‌شود */
    public function __construct(
        private readonly WorkflowManager $manager,
        public readonly string $id,
        public readonly string $instanceId,
        public readonly string $stateId,
        private ?string $assigneeType,
        private ?string $assigneeId,
        private string $status = 'pending',
        private ?\DateTimeImmutable $dueAt = null,
        private ?\DateTimeImmutable $completedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     *
     * @internal
     */
    public static function fromRow(WorkflowManager $manager, array $row): self
    {
        return new self(
            $manager,
            (string) $row['id'],
            (string) $row['instance_id'],
            (string) $row['state_id'],
            isset($row['assignee_type']) ? (string) $row['assignee_type'] : null,
            isset($row['assignee_id']) ? (string) $row['assignee_id'] : null,
            (string) ($row['status'] ?? 'pending'),
            isset($row['due_at']) ? new \DateTimeImmutable((string) $row['due_at']) : null,
            isset($row['completed_at']) ? new \DateTimeImmutable((string) $row['completed_at']) : null,
        );
    }

    /**
     * تصاحب کار توسط کاربر (برای assignee نوع role).
     *
     * @throws TaskException
     */
    public function claim(string|int $userId): void
    {
        $this->assertPending();
        $userId = (string) $userId;

        if ($this->assigneeType === 'user' && $this->assigneeId !== null && $this->assigneeId !== $userId) {
            throw TaskException::notClaimable($this->id);
        }
        if ($this->assigneeType === 'role' && !$this->manager->userHasRole($userId, (string) $this->assigneeId)) {
            throw TaskException::notClaimable($this->id);
        }

        $this->assigneeType = 'user';
        $this->assigneeId = $userId;
        $this->manager->store()->updateTask($this->id, [
            'assignee_type' => 'user',
            'assignee_id' => $userId,
            'status' => 'claimed',
        ]);
        $this->status = 'claimed';
    }

    /**
     * پایان کار و پیش‌برد فرایند.
     *
     * گذار: صریح (`$transitionId`) یا تنها گذار خروجی state؛ اگر چند گذار
     * خروجی وجود دارد و `transition` در `$input` نباشد → خطا.
     *
     * @param array<string, mixed> $input
     *
     * @throws TaskException
     */
    public function complete(array $input = [], ?string $transitionId = null): ProcessInstance
    {
        if ($this->status !== 'pending' && $this->status !== 'claimed') {
            throw TaskException::notPending($this->id, $this->status);
        }

        $instance = $this->manager->instance($this->instanceId);
        $transitionId ??= (string) ($input['transition'] ?? '') ?: null;
        $transitionId ??= $this->resolveOutgoing();

        $this->manager->store()->updateTask($this->id, [
            'status' => 'completed',
            'completed_at' => \Tavaan\Support\Time\Clock::now()->format('Y-m-d H:i:s.v'),
        ]);
        $this->status = 'completed';
        $this->completedAt = \Tavaan\Support\Time\Clock::now();

        unset($input['transition']);
        $instance->fire($transitionId, $input);

        return $instance;
    }

    /** واگذاری کار به کاربر دیگر. */
    public function delegate(string|int $userId): void
    {
        $this->assertPending();
        $this->assigneeType = 'user';
        $this->assigneeId = (string) $userId;
        $this->manager->store()->updateTask($this->id, [
            'assignee_type' => 'user',
            'assignee_id' => (string) $userId,
            'status' => 'pending',
        ]);
        $this->status = 'pending';
    }

    public function status(): string
    {
        return $this->status;
    }

    public function assigneeType(): ?string
    {
        return $this->assigneeType;
    }

    public function assigneeId(): ?string
    {
        return $this->assigneeId;
    }

    public function dueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' || $this->status === 'claimed';
    }

    /** @throws TaskException */
    private function assertPending(): void
    {
        if (!$this->isPending()) {
            throw TaskException::notPending($this->id, $this->status);
        }
    }

    /** @throws TaskException */
    private function resolveOutgoing(): string
    {
        $instance = $this->manager->instance($this->instanceId);
        $outgoing = $instance->definition()->outgoing($this->stateId);

        return match (\count($outgoing)) {
            0 => throw TaskException::noOutgoing($this->id, $this->stateId),
            1 => $outgoing[0]->id,
            default => throw TaskException::ambiguousOutgoing($this->id, $this->stateId),
        };
    }
}
