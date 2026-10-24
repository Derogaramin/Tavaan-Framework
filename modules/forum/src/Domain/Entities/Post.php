<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * Post — ریشهٔ Aggregate پست/پاسخ (DOC-094 §1/§2).
 */

namespace Modules\Forum\Domain\Entities;

use Modules\Forum\Domain\Enums\PostStatus;

/**
 * Post — یک پست درون موضوع (پست اول = بدنهٔ موضوع، بقیه = پاسخ).
 *
 * invariantها:
 * - `vote_score` = مجموع value رأی‌ها (توسط PostService بازخوانی/همگام می‌شود).
 * - `is_solution` فقط روی پست visible معنی دارد؛ هر موضوع حداکثر یک solution.
 * - status: visible ↔ hidden (توسط moderator)، deleted یک‌طرفه (نرم).
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class Post
{
    private function __construct(
        private readonly string $id,
        private readonly string $threadId,
        private readonly int $authorId,
        private ?string $parentId,
        private string $body,
        private int $voteScore,
        private bool $isSolution,
        private PostStatus $status,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        string $threadId,
        int $authorId,
        string $body,
        ?string $parentId = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: \Tavaan\Support\Ulid::generate()->toString(),
            threadId: $threadId,
            authorId: $authorId,
            parentId: $parentId,
            body: $body,
            voteScore: 0,
            isSolution: false,
            status: PostStatus::Visible,
            createdAt: $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            updatedAt: null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            threadId: (string) ($row['thread_id'] ?? ''),
            authorId: (int) ($row['author_id'] ?? 0),
            parentId: isset($row['parent_id']) ? (string) $row['parent_id'] : null,
            body: (string) ($row['body'] ?? ''),
            voteScore: (int) ($row['vote_score'] ?? 0),
            isSolution: (bool) ($row['is_solution'] ?? false),
            status: PostStatus::tryFromOrDefault((string) ($row['status'] ?? 'visible')),
            createdAt: new \DateTimeImmutable((string) ($row['created_at'] ?? 'now'), new \DateTimeZone('UTC')),
            updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable((string) $row['updated_at'], new \DateTimeZone('UTC')) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        $fmt = 'Y-m-d H:i:s.v';

        return [
            'id' => $this->id,
            'thread_id' => $this->threadId,
            'author_id' => $this->authorId,
            'parent_id' => $this->parentId,
            'body' => $this->body,
            'vote_score' => $this->voteScore,
            'is_solution' => $this->isSolution ? 1 : 0,
            'status' => $this->status->value,
            'created_at' => $this->createdAt->format($fmt),
            'updated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format($fmt),
        ];
    }

    /** ویرایش بدنه؛ parent فقط وقتی تغییر می‌کند که رشتهٔ غیر-null داده شود. */
    public function revise(string $body, ?string $parentId = null): void
    {
        $this->body = $body;
        if ($parentId !== null) {
            $this->parentId = $parentId !== '' ? $parentId : null;
        }
    }

    public function applyVoteScore(int $score): void
    {
        $this->voteScore = $score;
    }

    public function markSolution(bool $solution = true): void
    {
        $this->isSolution = $solution;
    }

    public function hide(): void
    {
        if ($this->status === PostStatus::Visible) {
            $this->status = PostStatus::Hidden;
            $this->isSolution = false;
        }
    }

    public function unhide(): void
    {
        if ($this->status === PostStatus::Hidden) {
            $this->status = PostStatus::Visible;
        }
    }

    public function softDelete(): void
    {
        $this->status = PostStatus::Deleted;
        $this->isSolution = false;
    }

    public function setStatus(PostStatus $status): void
    {
        $this->status = $status;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function threadId(): string
    {
        return $this->threadId;
    }

    public function authorId(): int
    {
        return $this->authorId;
    }

    public function parentId(): ?string
    {
        return $this->parentId;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function voteScore(): int
    {
        return $this->voteScore;
    }

    public function isSolution(): bool
    {
        return $this->isSolution;
    }

    public function status(): PostStatus
    {
        return $this->status;
    }

    public function isVisible(): bool
    {
        return $this->status === PostStatus::Visible;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
