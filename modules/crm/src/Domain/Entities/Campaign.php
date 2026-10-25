<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * Campaign — کمپین ایمیل/پیامک (DOC-096 §1.2/§2).
 */

namespace Modules\Crm\Domain\Entities;

use Modules\Crm\Domain\Enums\CampaignStatus;
use Modules\Crm\Domain\Events\CampaignWasSent;

/**
 * Campaign — draft → scheduled|sending → sent (+failed).
 *
 * audience_query: فیلتر JSON مخاطبان — {tags:[…], source:…, status:…, has_email:bool}.
 * template: متن با placeholderهای {first_name} {last_name} {company}.
 *
 * @api 1.0
 */
final class Campaign
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private int $sentCount = 0;

    private int $failedCount = 0;

    private function __construct(
        private readonly string $id,
        private string $name,
        private readonly string $type,          // email|sms
        private CampaignStatus $status,
        /** @var array<string, mixed> */
        private array $audienceQuery,
        private string $template,
        private ?\DateTimeImmutable $scheduledAt,
        private ?\DateTimeImmutable $sentAt,
        private readonly ?\DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $audienceQuery */
    public static function create(
        string $name,
        string $template,
        string $type = 'email',
        array $audienceQuery = [],
        ?\DateTimeImmutable $scheduledAt = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($name) === '') {
            throw new \DomainException('نام کمپین نمی‌تواند خالی باشد.');
        }
        if (\trim($template) === '') {
            throw new \DomainException('قالب کمپین نمی‌تواند خالی باشد.');
        }
        if (!\in_array($type, ['email', 'sms'], true)) {
            throw new \DomainException('نوع کمپین باید email یا sms باشد.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            \trim($name),
            $type,
            $scheduledAt !== null ? CampaignStatus::Scheduled : CampaignStatus::Draft,
            $audienceQuery,
            \trim($template),
            $scheduledAt,
            null,
            $now,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $audience = [];
        $raw = $row['audience_query'] ?? null;
        if (\is_string($raw) && $raw !== '') {
            $decoded = \json_decode($raw, true);
            $audience = \is_array($decoded) ? $decoded : [];
        } elseif (\is_array($raw)) {
            $audience = $raw;
        }

        $campaign = new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['type'] ?? 'email'),
            CampaignStatus::tryFrom((string) ($row['status'] ?? 'draft')) ?? CampaignStatus::Draft,
            $audience,
            (string) ($row['template'] ?? ''),
            self::date($row['scheduled_at'] ?? null),
            self::date($row['sent_at'] ?? null),
            self::date($row['created_at'] ?? null),
        );
        $campaign->sentCount = (int) ($row['sent_count'] ?? 0);
        $campaign->failedCount = (int) ($row['failed_count'] ?? 0);

        return $campaign;
    }

    // ─── رفتاردامنه ───────────────────────────────────────────────────────

    /** @param array<string, mixed> $audienceQuery */
    public function revise(string $name, string $template, array $audienceQuery = [], ?\DateTimeImmutable $scheduledAt = null): void
    {
        if ($this->status === CampaignStatus::Sent) {
            throw new \DomainException('کمپین ارسال‌شده ویرایش نمی‌شود.');
        }

        $this->name = \trim($name) !== '' ? \trim($name) : $this->name;
        $this->template = \trim($template) !== '' ? \trim($template) : $this->template;
        $this->audienceQuery = $audienceQuery;
        $this->scheduledAt = $scheduledAt;
    }

    public function markSending(): void
    {
        $this->transitionTo(CampaignStatus::Sending);
    }

    public function markSent(): void
    {
        $this->transitionTo(CampaignStatus::Sent);
        $this->sentAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->record(new CampaignWasSent($this->id, $this->name, $this->type, $this->sentCount, $this->failedCount));
    }

    public function markFailed(): void
    {
        $this->transitionTo(CampaignStatus::Failed);
    }

    public function recordSent(int $count = 1): void
    {
        $this->sentCount += \max(0, $count);
    }

    public function recordFailed(int $count = 1): void
    {
        $this->failedCount += \max(0, $count);
    }

    /** personalize — جای‌گذاری placeholderها. */
    public function renderFor(string $firstName, string $lastName, string $company): string
    {
        return \strtr($this->template, [
            '{first_name}' => $firstName,
            '{last_name}' => $lastName,
            '{company}' => $company,
        ]);
    }

    // ─── پرسش‌ها ──────────────────────────────────────────────────────────

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function status(): CampaignStatus
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function audienceQuery(): array
    {
        return $this->audienceQuery;
    }

    public function template(): string
    {
        return $this->template;
    }

    public function scheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function sentCount(): int
    {
        return $this->sentCount;
    }

    public function failedCount(): int
    {
        return $this->failedCount;
    }

    public function isDueForSending(?\DateTimeImmutable $now = null): bool
    {
        if ($this->status !== CampaignStatus::Scheduled) {
            return false;
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->scheduledAt === null || $now >= $this->scheduledAt;
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
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status->value,
            'audience_query' => (string) \json_encode($this->audienceQuery, JSON_UNESCAPED_UNICODE),
            'template' => $this->template,
            'sent_count' => $this->sentCount,
            'failed_count' => $this->failedCount,
            'scheduled_at' => self::fmt($this->scheduledAt),
            'sent_at' => self::fmt($this->sentAt),
            'created_at' => self::fmt($this->createdAt),
        ];
    }

    // ─── خصوصی ────────────────────────────────────────────────────────────

    private function transitionTo(CampaignStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw new \DomainException(\sprintf(
                'انتقال وضعیت کمپین نامجاز: %s → %s',
                $this->status->value,
                $target->value,
            ));
        }

        $this->status = $target;
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
