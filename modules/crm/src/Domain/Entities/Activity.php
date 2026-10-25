<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * Activity — تماس/جلسه/ایمیل/یادداشت/کار (DOC-096 §1.2/§2).
 */

namespace Modules\Crm\Domain\Entities;

use Modules\Crm\Domain\Enums\ActivityType;

/**
 * Activity — پلی‌مورف روی contact/lead/opportunity/ticket.
 *
 * @api 1.0
 */
final class Activity
{
    private function __construct(
        private readonly string $id,
        private readonly ActivityType $type,
        private string $subject,
        private ?string $body,
        private readonly ?string $contactId,
        private readonly ?string $leadId,
        private readonly ?string $opportunityId,
        private readonly ?string $ticketId,
        private readonly int $userId,
        private ?\DateTimeImmutable $dueAt,
        private ?\DateTimeImmutable $completedAt,
        private readonly ?\DateTimeImmutable $createdAt,
    ) {
    }

    public static function log(
        ActivityType $type,
        string $subject,
        int $userId,
        ?string $body = null,
        ?string $contactId = null,
        ?string $leadId = null,
        ?string $opportunityId = null,
        ?string $ticketId = null,
        ?\DateTimeImmutable $dueAt = null,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($subject) === '') {
            throw new \DomainException('موضوع فعالیت نمی‌تواند خالی باشد.');
        }
        if ($contactId === null && $leadId === null && $opportunityId === null && $ticketId === null) {
            throw new \DomainException('فعالیت باید به یک موجودیت (مخاطب/سرنخ/فرصت/تیکت) متصل باشد.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $type,
            \trim($subject),
            $body === null || \trim($body) === '' ? null : \trim($body),
            $contactId,
            $leadId,
            $opportunityId,
            $ticketId,
            $userId,
            $dueAt,
            null,
            $now,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            ActivityType::tryFromString((string) ($row['activity_type'] ?? 'note')),
            (string) ($row['subject'] ?? ''),
            isset($row['body']) && $row['body'] !== null ? (string) $row['body'] : null,
            self::nullableKey($row['contact_id'] ?? null),
            self::nullableKey($row['lead_id'] ?? null),
            self::nullableKey($row['opportunity_id'] ?? null),
            self::nullableKey($row['ticket_id'] ?? null),
            (int) ($row['user_id'] ?? 0),
            self::date($row['due_at'] ?? null),
            self::date($row['completed_at'] ?? null),
            self::date($row['created_at'] ?? null),
        );
    }

    /** تکمیل کار/فعالیت — idempotent. */
    public function complete(?\DateTimeImmutable $at = null): void
    {
        $this->completedAt ??= $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    public function isOverdue(?\DateTimeImmutable $now = null): bool
    {
        if ($this->dueAt === null || $this->completedAt !== null) {
            return false;
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now > $this->dueAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): ActivityType
    {
        return $this->type;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function contactId(): ?string
    {
        return $this->contactId;
    }

    public function leadId(): ?string
    {
        return $this->leadId;
    }

    public function opportunityId(): ?string
    {
        return $this->opportunityId;
    }

    public function ticketId(): ?string
    {
        return $this->ticketId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function dueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'activity_type' => $this->type->value,
            'subject' => $this->subject,
            'body' => $this->body,
            'contact_id' => $this->contactId,
            'lead_id' => $this->leadId,
            'opportunity_id' => $this->opportunityId,
            'ticket_id' => $this->ticketId,
            'user_id' => $this->userId,
            'due_at' => self::fmt($this->dueAt),
            'completed_at' => self::fmt($this->completedAt),
            'created_at' => self::fmt($this->createdAt),
        ];
    }

    private static function nullableKey(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
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
