<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * Lead — سرنخ فروش (DOC-096 §1.2/§3.1-3.2).
 */

namespace Modules\Crm\Domain\Entities;

use Modules\Crm\Domain\Enums\LeadStatus;
use Modules\Crm\Domain\Events\LeadWasConverted;
use Modules\Crm\Domain\Events\LeadWasCreated;

/**
 * Lead — new → qualified → converted (با Opportunity) یا lost.
 *
 * score با LeadScorer (دامنهٔ خالص — §3.2) محاسبه و اینجا ذخیره می‌شود.
 *
 * @api 1.0
 */
final class Lead
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly string $id,
        private readonly string $contactId,
        private string $title,
        private int $valueMinor,
        private readonly string $currency,
        private LeadStatus $status,
        private int $score,
        private ?int $ownerId,
        private ?string $convertedTo,
        private ?string $lostReason,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private readonly string $source,
    ) {
    }

    public static function create(
        string $contactId,
        string $title,
        int $valueMinor = 0,
        string $currency = 'IRR',
        ?int $ownerId = null,
        string $source = 'website',
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان سرنخ نمی‌تواند خالی باشد.');
        }
        if ($valueMinor < 0) {
            throw new \DomainException('ارزش سرنخ نمی‌تواند منفی باشد.');
        }

        $lead = new self(
            \Tavaan\Support\Ulid::generate($at)->toString(),
            $contactId,
            \trim($title),
            $valueMinor,
            $currency,
            LeadStatus::New,
            0,
            $ownerId,
            null,
            null,
            $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            null,
            $source,
        );

        $lead->record(new LeadWasCreated($lead->id, $contactId, $lead->title, $valueMinor, $currency, $source, $ownerId));

        return $lead;
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row, string $contactSource = 'website'): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['contact_id'] ?? ''),
            (string) ($row['title'] ?? ''),
            (int) ($row['value'] ?? 0),
            (string) ($row['currency'] ?? 'IRR'),
            LeadStatus::tryFrom((string) ($row['status'] ?? 'new')) ?? LeadStatus::New,
            (int) ($row['score'] ?? 0),
            isset($row['owner_id']) && $row['owner_id'] !== null ? (int) $row['owner_id'] : null,
            isset($row['converted_to']) && $row['converted_to'] !== null && $row['converted_to'] !== '' ? (string) $row['converted_to'] : null,
            isset($row['lost_reason']) && $row['lost_reason'] !== null ? (string) $row['lost_reason'] : null,
            self::date($row['created_at'] ?? null),
            self::date($row['updated_at'] ?? null),
            (string) ($row['source'] ?? '') !== '' ? (string) $row['source'] : $contactSource,
        );
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    public function revise(string $title, ?int $valueMinor = null, ?int $ownerId = null): void
    {
        if (\trim($title) === '') {
            throw new \DomainException('عنوان سرنخ نمی‌تواند خالی باشد.');
        }
        if (!$this->status->isOpen()) {
            throw new \DomainException('سرنخ بسته ویرایش نمی‌شود.');
        }

        $this->title = \trim($title);
        $this->valueMinor = $valueMinor === null ? $this->valueMinor : \max(0, $valueMinor);
        $this->ownerId = $ownerId ?? $this->ownerId;
        $this->touch();
    }

    /** نتیجهٔ LeadScorer — بدون تصمیم‌گیری در موجودیت (§3.2 دامنهٔ خالص). */
    public function applyScore(int $score): void
    {
        $this->score = \max(0, \min(100, $score));
        $this->touch();
    }

    /** تأیید صلاحیت (new → qualified). */
    public function qualify(): void
    {
        $this->transitionTo(LeadStatus::Qualified);
        $this->touch();
    }

    /** رد صلاحیت (qualified → new). */
    public function disqualify(): void
    {
        $this->transitionTo(LeadStatus::New);
        $this->touch();
    }

    /**
     * تبدیل به فرصت فروش (§3.1) — qualified → converted.
     */
    public function convertTo(string $opportunityId): void
    {
        if ($this->status !== LeadStatus::Qualified) {
            throw new \DomainException('فقط سرنخِ واجد صلاحیت به فرصت تبدیل می‌شود.');
        }

        $this->transitionTo(LeadStatus::Converted);
        $this->convertedTo = $opportunityId;
        $this->touch();

        $this->record(new LeadWasConverted($this->id, $opportunityId, $this->contactId, $this->valueMinor, $this->currency, $this->score, $this->ownerId));
    }

    public function markLost(string $reason = ''): void
    {
        $this->transitionTo(LeadStatus::Lost);
        $this->lostReason = $reason !== '' ? $reason : null;
        $this->touch();
    }

    public function assignTo(int $ownerId): void
    {
        $this->ownerId = $ownerId;
        $this->touch();
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

    public function status(): LeadStatus
    {
        return $this->status;
    }

    public function score(): int
    {
        return $this->score;
    }

    public function ownerId(): ?int
    {
        return $this->ownerId;
    }

    public function convertedTo(): ?string
    {
        return $this->convertedTo;
    }

    public function lostReason(): ?string
    {
        return $this->lostReason;
    }

    public function source(): string
    {
        return $this->source;
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
            'contact_id' => $this->contactId,
            'title' => $this->title,
            'value' => $this->valueMinor,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'score' => $this->score,
            'owner_id' => $this->ownerId,
            'converted_to' => $this->convertedTo,
            'lost_reason' => $this->lostReason,
            'source' => $this->source,
            'created_at' => self::fmt($this->createdAt),
            'updated_at' => self::fmt($this->updatedAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function transitionTo(LeadStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت سرنخ نامجاز: %s → %s (سرنخ %s)',
                $this->status->value,
                $target->value,
                $this->id,
            ));
        }

        $this->status = $target;
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
