<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * Flag — گزارش پست در صف moderation (DOC-094 §5: forum.post.flagged).
 */

namespace Modules\Forum\Domain\Entities;

use Modules\Forum\Domain\Enums\FlagStatus;

/**
 * Flag — یک گزارش کاربر روی یک پست.
 *
 * چرخه: pending → resolved (اقدام moderator، معمولاً hide پست)
 * یا dismissed (رد گزارش).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class Flag
{
    private function __construct(
        private ?int $id,
        private readonly string $postId,
        private readonly ?int $userId,
        private readonly ?string $reason,
        private FlagStatus $status,
        private ?int $moderatorId,
        private ?\DateTimeImmutable $resolvedAt,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function report(string $postId, ?int $userId, ?string $reason = null): self
    {
        return new self(
            id: null,
            postId: $postId,
            userId: $userId,
            reason: $reason !== null && \trim($reason) !== '' ? \mb_substr(\trim($reason), 0, 500) : null,
            status: FlagStatus::Pending,
            moderatorId: null,
            resolvedAt: null,
            createdAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            postId: (string) ($row['post_id'] ?? ''),
            userId: isset($row['user_id']) ? (int) $row['user_id'] : null,
            reason: isset($row['reason']) ? (string) $row['reason'] : null,
            status: FlagStatus::tryFromOrDefault((string) ($row['status'] ?? 'pending')),
            moderatorId: isset($row['moderator_id']) ? (int) $row['moderator_id'] : null,
            resolvedAt: isset($row['resolved_at']) ? new \DateTimeImmutable((string) $row['resolved_at'], new \DateTimeZone('UTC')) : null,
            createdAt: new \DateTimeImmutable((string) ($row['created_at'] ?? 'now'), new \DateTimeZone('UTC')),
        );
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        $fmt = 'Y-m-d H:i:s.v';

        return [
            'id' => $this->id,
            'post_id' => $this->postId,
            'user_id' => $this->userId,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'moderator_id' => $this->moderatorId,
            'resolved_at' => $this->resolvedAt?->format($fmt),
            'created_at' => $this->createdAt->format($fmt),
            'updated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format($fmt),
        ];
    }

    public function resolve(int $moderatorId): void
    {
        $this->status = FlagStatus::Resolved;
        $this->moderatorId = $moderatorId;
        $this->resolvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function dismiss(int $moderatorId): void
    {
        $this->status = FlagStatus::Dismissed;
        $this->moderatorId = $moderatorId;
        $this->resolvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id ??= $id;
    }

    public function postId(): string
    {
        return $this->postId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function status(): FlagStatus
    {
        return $this->status;
    }

    public function moderatorId(): ?int
    {
        return $this->moderatorId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
