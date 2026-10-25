<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * Opportunity — فرصت فروش در pipeline (DOC-096 §1.2/§3.1).
 */

namespace Modules\Crm\Domain\Entities;

use Modules\Crm\Domain\Enums\OpportunityStage;
use Modules\Crm\Domain\Events\OpportunityStageChanged;
use Modules\Crm\Domain\Events\OpportunityWasLost;
use Modules\Crm\Domain\Events\OpportunityWasWon;

/**
 * Opportunity — هر stage با probability و value → weighted forecast (§3.1).
 *
 * invariantها:
 * - انتقال stage فقط طبق ماتریس OpportunityStage
 * - closed_won/closed_lost پایانی‌اند (closed_at ثبت می‌شود)
 * - lost بدون دلیل بسته نمی‌شود
 * - probability از config مرحله می‌آید (UseCase تزریق می‌کند)
 *
 * @api 1.0
 */
final class Opportunity
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private readonly string $contactId,
        private readonly ?string $leadId,
        private string $title,
        private int $valueMinor,
        private readonly string $currency,
        private OpportunityStage $stage,
        private int $probability,
        private ?string $expectedCloseDate,
        private ?int $ownerId,
        private ?string $lostReason,
        private ?string $workflowInstanceId,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $closedAt,
    ) {
    }

    public static function create(
        string $contactId,
        string $title,
        int $valueMinor = 0,
        string $currency = 'IRR',
        ?string $leadId = null,
        ?int $ownerId = null,
        ?string $expectedCloseDate = null,
        int $initialProbability = 10,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان فرصت نمی‌تواند خالی باشد.');
        }
        if ($valueMinor < 0) {
            throw new \DomainException('ارزش فرصت نمی‌تواند منفی باشد.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $contactId,
            $leadId,
            \trim($title),
            $valueMinor,
            $currency,
            OpportunityStage::Prospecting,
            \max(0, \min(100, $initialProbability)),
            $expectedCloseDate,
            $ownerId,
            null,
            null,
            $now,
            null,
            null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['contact_id'] ?? ''),
            isset($row['lead_id']) && $row['lead_id'] !== null && $row['lead_id'] !== '' ? (string) $row['lead_id'] : null,
            (string) ($row['title'] ?? ''),
            (int) ($row['value'] ?? 0),
            (string) ($row['currency'] ?? 'IRR'),
            OpportunityStage::tryFromString((string) ($row['stage'] ?? 'prospecting')),
            (int) ($row['probability'] ?? 0),
            isset($row['expected_close_date']) && $row['expected_close_date'] !== null && $row['expected_close_date'] !== ''
                ? (string) $row['expected_close_date']
                : null,
            isset($row['owner_id']) && $row['owner_id'] !== null ? (int) $row['owner_id'] : null,
            isset($row['lost_reason']) && $row['lost_reason'] !== null ? (string) $row['lost_reason'] : null,
            isset($row['workflow_instance_id']) && $row['workflow_instance_id'] !== null && $row['workflow_instance_id'] !== ''
                ? (string) $row['workflow_instance_id']
                : null,
            self::date($row['created_at'] ?? null),
            self::date($row['updated_at'] ?? null),
            self::date($row['closed_at'] ?? null),
        );
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    public function revise(string $title, ?int $valueMinor = null, ?string $expectedCloseDate = null): void
    {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان فرصت نمی‌تواند خالی باشد.');
        }
        if ($this->stage->isClosed()) {
            throw new \DomainException('فرصت بسته ویرایش نمی‌شود.');
        }

        $this->title = \trim($title);
        $this->valueMinor = $valueMinor === null ? $this->valueMinor : \max(0, $valueMinor);
        $this->expectedCloseDate = $expectedCloseDate ?? $this->expectedCloseDate;
        $this->touch();
    }

    /**
     * جابجایی مرحله (§3.1) — با احتمال جدید مرحله و رویداد.
     */
    public function moveTo(OpportunityStage $target, int $probability, ?string $lostReason = null): void
    {
        if (!$this->stage->canMoveTo($target)) {
            throw new \DomainException(\sprintf(
                'جابجایی مرحله نامجاز: %s → %s (فرصت %s)',
                $this->stage->value,
                $target->value,
                $this->id,
            ));
        }

        if ($target === OpportunityStage::ClosedLost && ($lostReason === null || \trim($lostReason) === '')) {
            throw new \DomainException('فرصت ناموفق بدون دلیل بسته نمی‌شود.');
        }

        $from = $this->stage;
        $this->stage = $target;
        $this->probability = \max(0, \min(100, $probability));
        $this->lostReason = $target === OpportunityStage::ClosedLost ? \trim((string) $lostReason) : $this->lostReason;

        if ($target->isClosed()) {
            $this->closedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
        $this->touch();

        $this->record(new OpportunityStageChanged(
            $this->id,
            $this->title,
            $from->value,
            $target->value,
            $this->probability,
            $this->valueMinor,
            $this->currency,
            $this->ownerId,
            $this->workflowInstanceId,
        ));

        if ($target->isWon()) {
            $this->record(new OpportunityWasWon($this->id, $this->title, $this->contactId, $this->valueMinor, $this->currency, $this->ownerId, $this->leadId));
        } elseif ($target === OpportunityStage::ClosedLost) {
            $this->record(new OpportunityWasLost($this->id, $this->title, $this->contactId, $this->valueMinor, $this->currency, $this->ownerId, (string) $this->lostReason));
        }
    }

    public function assignTo(?int $ownerId): void
    {
        $this->ownerId = $ownerId;
        $this->touch();
    }

    public function attachWorkflow(string $instanceId): void
    {
        if ($this->workflowInstanceId === null || $this->workflowInstanceId === '') {
            $this->workflowInstanceId = $instanceId;
        }
    }

    /** سهم وزنی این فرصت در forecast (§3.1) — int (بدون float). */
    public function weightedValue(): int
    {
        return (int) \floor($this->valueMinor * $this->probability / 100);
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function contactId(): string
    {
        return $this->contactId;
    }

    public function leadId(): ?string
    {
        return $this->leadId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function valueMinor(): int
    {
        return $this->valueMinor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function stage(): OpportunityStage
    {
        return $this->stage;
    }

    public function probability(): int
    {
        return $this->probability;
    }

    public function expectedCloseDate(): ?string
    {
        return $this->expectedCloseDate;
    }

    public function ownerId(): ?int
    {
        return $this->ownerId;
    }

    public function lostReason(): ?string
    {
        return $this->lostReason;
    }

    public function workflowInstanceId(): ?string
    {
        return $this->workflowInstanceId;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
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
            'contact_id' => $this->contactId,
            'lead_id' => $this->leadId,
            'title' => $this->title,
            'value' => $this->valueMinor,
            'currency' => $this->currency,
            'stage' => $this->stage->value,
            'probability' => $this->probability,
            'expected_close_date' => $this->expectedCloseDate,
            'owner_id' => $this->ownerId,
            'lost_reason' => $this->lostReason,
            'workflow_instance_id' => $this->workflowInstanceId,
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
            'closed_at' => self::fmt($this->closedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

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
