<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول CRM
 * TicketReply — پاسخ تیکت (DOC-096 §2: agent|customer + is_internal).
 */

namespace Modules\Crm\Domain\Entities;

/**
 * @api 1.0
 */
final class TicketReply
{
    private function __construct(
        private readonly string $id,
        private readonly string $ticketId,
        private readonly ?int $authorId,
        private readonly string $authorType,      // agent|customer
        private readonly string $body,
        /** @var list<string> */
        private readonly array $attachments,
        private readonly bool $isInternal,
        private readonly ?\DateTimeImmutable $createdAt,
    ) {
    }

    /** @param list<string> $attachments */
    public static function create(
        string $ticketId,
        string $body,
        string $authorType,
        ?int $authorId = null,
        array $attachments = [],
        bool $isInternal = false,
        ?\DateTimeImmutable $at = null,
    ): self {
        if (\trim($body) === '') {
            throw new \DomainException('متن پاسخ نمی‌تواند خالی باشد.');
        }
        if (!\in_array($authorType, ['agent', 'customer'], true)) {
            throw new \DomainException('نوع نویسنده باید agent یا customer باشد.');
        }
        if ($isInternal && $authorType !== 'agent') {
            throw new \DomainException('یادداشت داخلی فقط از طرف agent است.');
        }

        $now = $at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            \Tavaan\Support\Ulid::generate($now)->toString(),
            $ticketId,
            $authorId,
            $authorType,
            \trim($body),
            \array_values(\array_filter(\array_map('strval', $attachments))),
            $isInternal,
            $now,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $attachments = [];
        $raw = $row['attachments'] ?? null;
        if (\is_string($raw) && $raw !== '') {
            $decoded = \json_decode($raw, true);
            $attachments = \is_array($decoded) ? $decoded : [];
        } elseif (\is_array($raw)) {
            $attachments = $raw;
        }

        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['ticket_id'] ?? ''),
            isset($row['author_id']) && $row['author_id'] !== null ? (int) $row['author_id'] : null,
            (string) ($row['author_type'] ?? 'customer'),
            (string) ($row['body'] ?? ''),
            $attachments,
            (bool) ($row['is_internal'] ?? false),
            self::date($row['created_at'] ?? null),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function ticketId(): string
    {
        return $this->ticketId;
    }

    public function authorId(): ?int
    {
        return $this->authorId;
    }

    public function authorType(): string
    {
        return $this->authorType;
    }

    public function isAgent(): bool
    {
        return $this->authorType === 'agent';
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return list<string> */
    public function attachments(): array
    {
        return $this->attachments;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
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
            'ticket_id' => $this->ticketId,
            'author_id' => $this->authorId,
            'author_type' => $this->authorType,
            'body' => $this->body,
            'attachments' => (string) \json_encode($this->attachments, JSON_UNESCAPED_UNICODE),
            'is_internal' => $this->isInternal,
            'created_at' => self::fmt($this->createdAt),
        ];
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
