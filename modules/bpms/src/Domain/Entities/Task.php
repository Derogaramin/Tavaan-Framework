<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول BPMS
 * Task — کار انسانی (DOC-098 §2/§7) — موجودیت.
 */

namespace Modules\Bpms\Domain\Entities;

use Modules\Bpms\Domain\Enums\AssigneeType;
use Modules\Bpms\Domain\Enums\TaskPriority;
use Modules\Bpms\Domain\Enums\TaskStatus;
use Modules\Bpms\Domain\Exceptions\BpmsException;

/**
 * Task — کار انسانی یک نمونه: assignee (user/role/group/initiator)،
 * form_data، due_at (از `due` عنصر) و چرخهٔ pending→claimed→completed.
 *
 * 🔒 claim رقابتی: repository باید UPDATE … WHERE status='pending' اتمیک
 * بزند (ادعای همزمان برنده می‌شود و بازنده already_claimed می‌گیرد).
 *
 * @api 1.0
 * @see docs/05-applications/bpms.md
 */
final class Task
{
    /**
     * @param array<string, mixed> $formData
     */
    public function __construct(
        private readonly string $id,
        private readonly string $instanceId,
        private readonly string $stateId,
        private readonly string $title,
        private AssigneeType $assigneeType,
        private ?string $assigneeId,
        private array $formData,
        private TaskStatus $status,
        private readonly TaskPriority $priority,
        private readonly ?string $formId,
        private readonly ?string $dueAt,
        private ?int $claimedBy,
        private ?int $completedBy,
        private readonly ?string $createdAt,
        private ?string $claimedAt,
        private ?string $completedAt,
        private ?string $escalatedAt = null,
        private ?string $escalatedTo = null,
        private ?string $comment = null,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            instanceId: (string) ($row['instance_id'] ?? ''),
            stateId: (string) ($row['state_id'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            assigneeType: AssigneeType::fromString((string) ($row['assignee_type'] ?? 'anyone')),
            assigneeId: isset($row['assignee_id']) ? (string) $row['assignee_id'] : null,
            formData: (array) ($row['form_data'] ?? []),
            status: TaskStatus::fromString((string) ($row['status'] ?? 'pending')),
            priority: TaskPriority::fromString((string) ($row['priority'] ?? 'normal')),
            formId: isset($row['form_id']) ? (string) $row['form_id'] : null,
            dueAt: isset($row['due_at']) ? (string) $row['due_at'] : null,
            claimedBy: isset($row['claimed_by']) ? (int) $row['claimed_by'] : null,
            completedBy: isset($row['completed_by']) ? (int) $row['completed_by'] : null,
            createdAt: isset($row['createdAt']) ? (string) $row['createdAt'] : (isset($row['created_at']) ? (string) $row['created_at'] : null),
            claimedAt: isset($row['claimed_at']) ? (string) $row['claimed_at'] : null,
            completedAt: isset($row['completed_at']) ? (string) $row['completed_at'] : null,
            escalatedAt: isset($row['escalated_at']) ? (string) $row['escalated_at'] : null,
            escalatedTo: isset($row['escalated_to']) ? (string) $row['escalated_to'] : null,
            comment: isset($row['comment']) ? (string) $row['comment'] : null,
        );
    }

    // ─── خوانندگان ─────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function instanceId(): string
    {
        return $this->instanceId;
    }

    public function stateId(): string
    {
        return $this->stateId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function assigneeType(): AssigneeType
    {
        return $this->assigneeType;
    }

    public function assigneeId(): ?string
    {
        return $this->assigneeId;
    }

    /** @return array<string, mixed> */
    public function formData(): array
    {
        return $this->formData;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function priority(): TaskPriority
    {
        return $this->priority;
    }

    public function formId(): ?string
    {
        return $this->formId;
    }

    public function dueAt(): ?string
    {
        return $this->dueAt;
    }

    public function claimedBy(): ?int
    {
        return $this->claimedBy;
    }

    public function completedBy(): ?int
    {
        return $this->completedBy;
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    public function claimedAt(): ?string
    {
        return $this->claimedAt;
    }

    public function completedAt(): ?string
    {
        return $this->completedAt;
    }

    public function escalatedAt(): ?string
    {
        return $this->escalatedAt;
    }

    public function escalatedTo(): ?string
    {
        return $this->escalatedTo;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function isEscalated(): bool
    {
        return $this->escalatedAt !== null;
    }

    /** سررسید گذشته و هنوز باز است؟ */
    public function isOverdue(\DateTimeImmutable $now): bool
    {
        if ($this->dueAt === null || !$this->status->isOpen()) {
            return false;
        }

        try {
            $due = new \DateTimeImmutable($this->dueAt);
        } catch (\Exception) {
            return false;
        }

        return $due <= $now;
    }

    // ─── رفتار ─────────────────────────────────────────────────────────────

    /**
     * تصاحب کار (role/group/anyone → کاربر مشخص).
     *
     * @throws BpmsException task_not_open / already_claimed
     */
    public function claim(int $userId, string $claimedAt): void
    {
        if (!$this->status->isOpen()) {
            throw BpmsException::taskNotOpen($this->id, $this->status->value);
        }
        if ($this->status === TaskStatus::Claimed && $this->claimedBy !== $userId) {
            throw BpmsException::alreadyClaimed($this->id);
        }
        $this->status = TaskStatus::Claimed;
        $this->assigneeType = AssigneeType::User;
        $this->assigneeId = (string) $userId;
        $this->claimedBy = $userId;
        $this->claimedAt = $claimedAt;
    }

    /**
     * تکمیل کار با دادهٔ فرم (validate پیش‌تر در ProcessEngine انجام شده — §5).
     *
     * @param array<string, mixed> $formData
     *
     * @throws BpmsException task_not_open
     */
    public function complete(array $formData, int $actorId, string $completedAt, ?string $comment = null): void
    {
        if (!$this->status->isOpen()) {
            throw BpmsException::taskNotOpen($this->id, $this->status->value);
        }
        $this->formData = $formData;
        $this->status = TaskStatus::Completed;
        $this->completedBy = $actorId;
        $this->completedAt = $completedAt;
        $this->comment = $comment;
    }

    /** واگذاری به دیگری (assignee جایگزین — کار به صف بازمی‌گردد). */
    public function delegate(AssigneeType $type, string $assigneeId): void
    {
        if (!$this->status->isOpen()) {
            throw BpmsException::taskNotOpen($this->id, $this->status->value);
        }
        $this->assigneeType = $type;
        $this->assigneeId = $assigneeId;
        $this->status = TaskStatus::Pending;
        $this->claimedBy = null;
        $this->claimedAt = null;
    }

    /**
     * escalation (تایمر §9): assignee جدید + مهر escalated.
     * idempotency در سطح سرویس (بررسی escalatedAt پیش از فراخوانی).
     */
    public function escalate(AssigneeType $type, string $assigneeId, string $escalatedAt): void
    {
        if (!$this->status->isOpen()) {
            throw BpmsException::taskNotOpen($this->id, $this->status->value);
        }
        $this->assigneeType = $type;
        $this->assigneeId = $assigneeId;
        $this->escalatedAt = $escalatedAt;
        $this->escalatedTo = $type->value . ':' . $assigneeId;
        // پس از escalation کار دوباره در صف قرار می‌گیرد
        $this->status = TaskStatus::Pending;
        $this->claimedBy = null;
        $this->claimedAt = null;
    }

    /**
     * مهر escalation بدون تغییر assignee (idempotency برای اکشن سفارشی
     * که خودش کار را مدیریت می‌کند — مثلاً auto-reject).
     */
    public function stampEscalation(string $escalatedAt, string $escalatedTo): void
    {
        if (!$this->status->isOpen()) {
            return;
        }
        $this->escalatedAt = $escalatedAt;
        $this->escalatedTo = $escalatedTo;
    }

    /** لغو (نمونه terminate شد). */
    public function cancel(): void
    {
        if ($this->status->isTerminal()) {
            return;
        }
        $this->status = TaskStatus::Canceled;
    }
}
