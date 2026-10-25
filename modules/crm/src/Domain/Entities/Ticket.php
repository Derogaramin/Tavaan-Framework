<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * Ticket — تیکت پشتیبانی با SLA (DOC-096 §1.2/§3.3).
 */

namespace Modules\Crm\Domain\Entities;

use Modules\Crm\Domain\Enums\TicketPriority;
use Modules\Crm\Domain\Enums\TicketStatus;
use Modules\Crm\Domain\Events\TicketSlaBreached;
use Modules\Crm\Domain\Events\TicketWasAssigned;
use Modules\Crm\Domain\Events\TicketWasCreated;
use Modules\Crm\Domain\Events\TicketWasResolved;

/**
 * Ticket — open → pending (منتظر مشتری) → resolved → closed.
 *
 * SLA (§3.3): sla_due_at هنگام ساخت بر اساس priority (config)؛ ساعت SLA
 * فقط در وضعیت open می‌دود (pending = منتظر مشتری → توقف). نقض SLA →
 * escalated_at + رویداد crm.ticket.sla_breached (یک‌بار — idempotent).
 *
 * @api 1.0
 */
final class Ticket
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private readonly string $ticketNumber,
        private readonly ?string $contactId,
        private string $subject,
        private string $body,
        private ?string $category,
        private TicketPriority $priority,
        private TicketStatus $status,
        private ?int $assignedTo,
        private ?\DateTimeImmutable $slaDueAt,
        private ?\DateTimeImmutable $escalatedAt,
        private ?\DateTimeImmutable $resolvedAt,
        private ?\DateTimeImmutable $closedAt,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        string $ticketNumber,
        string $subject,
        string $body,
        ?string $contactId = null,
        ?string $category = null,
        TicketPriority $priority = TicketPriority::Normal,
        ?int $assignedTo = null,
        ?\DateTimeImmutable $slaDueAt = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($subject) === '') {
            throw new \DomainException('عنوان تیکت نمی‌تواند خالی باشد.');
        }
        if (\trim($body) === '') {
            throw new \DomainException('متن تیکت نمی‌تواند خالی باشد.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $ticket = new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $ticketNumber,
            $contactId,
            \trim($subject),
            \trim($body),
            $category === null || \trim($category) === '' ? null : \trim($category),
            $priority,
            TicketStatus::Open,
            $assignedTo,
            $slaDueAt,
            null,
            null,
            null,
            $now,
            null,
        );

        $ticket->record(new TicketWasCreated(
            $ticket->id,
            $ticketNumber,
            $ticket->subject,
            $priority->value,
            $contactId,
            $assignedTo,
            $slaDueAt?->format('Y-m-d H:i:s.v'),
        ));

        return $ticket;
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['ticket_number'] ?? ''),
            isset($row['contact_id']) && $row['contact_id'] !== null && $row['contact_id'] !== '' ? (string) $row['contact_id'] : null,
            (string) ($row['subject'] ?? ''),
            (string) ($row['body'] ?? ''),
            isset($row['category']) && $row['category'] !== null ? (string) $row['category'] : null,
            TicketPriority::tryFromString((string) ($row['priority'] ?? 'normal')),
            TicketStatus::tryFrom((string) ($row['status'] ?? 'open')) ?? TicketStatus::Open,
            isset($row['assigned_to']) && $row['assigned_to'] !== null ? (int) $row['assigned_to'] : null,
            self::date($row['sla_due_at'] ?? null),
            self::date($row['escalated_at'] ?? null),
            self::date($row['resolved_at'] ?? null),
            self::date($row['closed_at'] ?? null),
            self::date($row['created_at'] ?? null),
            self::date($row['updated_at'] ?? null),
        );
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    public function assignTo(?int $userId): void
    {
        $this->assertNotClosed();
        $this->assignedTo = $userId;
        $this->touch();

        if ($userId !== null) {
            $this->record(new TicketWasAssigned(
                $this->id,
                $this->ticketNumber,
                $this->subject,
                $this->priority->value,
                $userId,
                $this->slaDueAt?->format('Y-m-d H:i:s.v'),
            ));
        }
    }

    public function changePriority(TicketPriority $priority, ?\DateTimeImmutable $newSlaDue = null): void
    {
        $this->assertNotClosed();
        $this->priority = $priority;
        if ($newSlaDue !== null) {
            $this->slaDueAt = $newSlaDue;
            $this->escalatedAt = null;   // اولویت جدید = SLA جدید
        }
        $this->touch();
    }

    /** پاسخ کارشناس → pending (منتظر مشتری — توقف SLA؛ §3.3). */
    public function markAwaitingCustomer(): void
    {
        $this->transitionTo(TicketStatus::Pending);
        $this->touch();
    }

    /** پاسخ مشتری (در pending) → open (ادامهٔ SLA). */
    public function reopenByCustomer(): void
    {
        if ($this->status !== TicketStatus::Pending) {
            return;   // open که هست؛ resolved/closed → بازگشت صریح
        }
        $this->transitionTo(TicketStatus::Open);
        $this->touch();
    }

    /** حل تیکت (§3.3: resolve → resolved). */
    public function resolve(?int $resolverId = null): void
    {
        $this->transitionTo(TicketStatus::Resolved);
        $this->resolvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($resolverId !== null) {
            $this->assignedTo ??= $resolverId;
        }
        $this->touch();

        $resolutionMinutes = $this->createdAt !== null
            ? (int) \floor(($this->resolvedAt->getTimestamp() - $this->createdAt->getTimestamp()) / 60)
            : null;

        $this->record(new TicketWasResolved($this->id, $this->ticketNumber, $this->contactId, $this->assignedTo, $resolutionMinutes));
    }

    /** ردِ حل توسط مشتری → open. */
    public function reopen(): void
    {
        $this->transitionTo(TicketStatus::Open);
        $this->resolvedAt = null;
        $this->touch();
    }

    /** تأیید مشتری / بسته‌شدن خودکار → closed. */
    public function close(): void
    {
        $this->transitionTo(TicketStatus::Closed);
        $this->closedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->touch();
    }

    /**
     * نقض SLA → escalation (AC-CRM-08) — idempotent.
     *
     * @return bool آیا تازه escalate شد؟
     */
    public function escalateForSla(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($this->escalatedAt !== null || !$this->isSlaBreached($now)) {
            return false;
        }

        $this->escalatedAt = $now;
        $this->touch();

        $overdueMinutes = $this->slaDueAt !== null
            ? (int) \floor(($now->getTimestamp() - $this->slaDueAt->getTimestamp()) / 60)
            : 0;

        $this->record(new TicketSlaBreached(
            $this->id,
            $this->ticketNumber,
            $this->priority->value,
            (string) ($this->slaDueAt?->format('Y-m-d H:i:s.v') ?? ''),
            $this->assignedTo,
            \max(0, $overdueMinutes),
        ));

        return true;
    }

    /** آیا SLA نقض شده؟ (فقط وضعیت open — pending توقف ساعت) */
    public function isSlaBreached(?\DateTimeImmutable $now = null): bool
    {
        if ($this->slaDueAt === null || !$this->status->slaRunning() || $this->status === TicketStatus::Closed) {
            return false;
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now > $this->slaDueAt;
    }

    /** دقیقه‌های باقی‌ماندهٔ SLA (منفی = گذشته). */
    public function slaMinutesLeft(?\DateTimeImmutable $now = null): ?int
    {
        if ($this->slaDueAt === null) {
            return null;
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return (int) \floor(($this->slaDueAt->getTimestamp() - $now->getTimestamp()) / 60);
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function ticketNumber(): string
    {
        return $this->ticketNumber;
    }

    public function contactId(): ?string
    {
        return $this->contactId;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function priority(): TicketPriority
    {
        return $this->priority;
    }

    public function status(): TicketStatus
    {
        return $this->status;
    }

    public function assignedTo(): ?int
    {
        return $this->assignedTo;
    }

    public function slaDueAt(): ?\DateTimeImmutable
    {
        return $this->slaDueAt;
    }

    public function escalatedAt(): ?\DateTimeImmutable
    {
        return $this->escalatedAt;
    }

    public function isEscalated(): bool
    {
        return $this->escalatedAt !== null;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticketNumber,
            'contact_id' => $this->contactId,
            'subject' => $this->subject,
            'body' => $this->body,
            'category' => $this->category,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'assigned_to' => $this->assignedTo,
            'sla_due_at' => self::fmt($this->slaDueAt),
            'escalated_at' => self::fmt($this->escalatedAt),
            'resolved_at' => self::fmt($this->resolvedAt),
            'closed_at' => self::fmt($this->closedAt),
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function transitionTo(TicketStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت تیکت نامجاز: %s → %s (تیکت %s)',
                $this->status->value,
                $target->value,
                $this->ticketNumber,
            ));
        }

        $this->status = $target;
    }

    private function assertNotClosed(): void
    {
        if ($this->status === TicketStatus::Closed) {
            throw new \DomainException('تیکت بسته تغییر نمی‌کند.');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    private static function fmt(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        try {
            return new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
